import { router } from '@inertiajs/vue3';
import { render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import Delivery from '@/pages/settings/Delivery.vue';
import { http, page } from '../../support/inertia';
import { deliveryProps, destinationProps } from '../../support/settingsProps';

function renderPage(props = deliveryProps()) {
    page.url = '/settings/delivery';
    page.props = { ...props, errors: {} };
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    const put = vi.spyOn(router, 'put').mockImplementation(() => {});
    const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
    const rendered = render(Delivery, { props });
    return { post, put, destroy, rendered, user: userEvent.setup() };
}

describe('Delivery settings', () => {
    it('uses the install link and keeps in-app delivery always on without destination actions', () => {
        renderPage();
        expect(
            screen.getByRole('link', { name: 'Add to Discord' }),
        ).toHaveAttribute('href', '/discord/install');
        expect(
            screen.getByText(
                'Always on. Every digest appears on your Today page.',
            ),
        ).toBeVisible();
        expect(screen.getByText('…abcd')).toBeVisible();
        expect(
            screen.getAllByRole('button', { name: /^Delete$/ }),
        ).toHaveLength(2);
        expect(screen.getAllByRole('switch')).toHaveLength(2);
    });

    it('explains why a forum cannot be added when the server has none', () => {
        const props = deliveryProps();
        props.guilds[0].forums = [];
        renderPage(props);

        expect(
            screen.getByRole('button', { name: 'Add a forum channel' }),
        ).toBeDisabled();
        expect(screen.getByText(/No forum channels found/)).toBeVisible();
        expect(screen.getByText(/Community server/)).toBeVisible();
    });

    it('does not show the no forum hint when forums exist or loading failed', () => {
        const props = deliveryProps();
        renderPage(props);
        expect(
            screen.queryByText(/No forum channels found/),
        ).not.toBeInTheDocument();
    });

    it('describes the email section', () => {
        renderPage();

        expect(
            screen.getByText('Receive your digests by email.'),
        ).toBeVisible();
    });

    it('hides installation when unavailable and explains why', () => {
        const props = deliveryProps();
        props.install_url = null;
        renderPage(props);
        expect(
            screen.queryByRole('link', { name: 'Add to Discord' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByText(/Discord.*not configured|Discord.*unavailable/i),
        ).toBeVisible();
    });

    it('keeps destinations editable when forum discovery fails and explains the failure', () => {
        const props = deliveryProps();
        props.guilds[0].forums = [];
        props.guilds[0].forums_error = true;
        renderPage(props);
        expect(screen.getByText('Digest forum')).toBeVisible();
        expect(
            screen.getByText(
                /could not.*channels|could not.*forums|unable to.*channels/i,
            ),
        ).toBeVisible();
        expect(
            screen.getByRole('button', { name: 'Add a forum channel' }),
        ).toBeDisabled();
    });

    it('adds a selected forum with receive kinds for the correct guild', async () => {
        const view = renderPage();
        await view.user.click(
            screen.getByRole('button', { name: 'Add a forum channel' }),
        );
        const dialog = await screen.findByRole('dialog');
        const select = within(dialog).getByRole('combobox', {
            name: /^Forum channel$/,
        });
        if (select instanceof HTMLSelectElement) {
            await view.user.selectOptions(select, '790');
        } else {
            await view.user.click(select);
            await view.user.click(
                await screen.findByRole('option', { name: /^Learning$/ }),
            );
        }
        await view.user.click(
            within(dialog).getByRole('button', { name: /^Add$/ }),
        );
        expect(view.post).toHaveBeenCalledWith(
            '/settings/delivery',
            expect.objectContaining({
                type: 'discord_bot',
                guild_id: '456',
                channel_id: '790',
                kinds: ['news'],
            }),
            expect.any(Object),
        );
    });

    it('adds a webhook through a masked input and sends its name and kinds', async () => {
        const view = renderPage();
        await view.user.click(
            screen.getByRole('button', { name: 'Add webhook' }),
        );
        const dialog = await screen.findByRole('dialog');
        const url = within(dialog).getByLabelText('Webhook URL');
        expect(url).toHaveAttribute('type', 'password');
        expect(
            within(dialog).getByRole('button', { name: /^Add$/ }),
        ).toBeDisabled();
        await view.user.type(
            within(dialog).getByRole('textbox', { name: /^Name$/ }),
            'Team hook',
        );
        await view.user.type(
            url,
            'https://discord.com/api/webhooks/123456789/fake-token',
        );
        await view.user.click(
            within(dialog).getByRole('checkbox', { name: /^Learning$/ }),
        );
        await view.user.click(
            within(dialog).getByRole('button', { name: /^Add$/ }),
        );
        expect(view.post).toHaveBeenCalledWith(
            '/settings/delivery',
            expect.objectContaining({
                type: 'discord_webhook',
                name: 'Team hook',
                webhook_url:
                    'https://discord.com/api/webhooks/123456789/fake-token',
                kinds: ['news', 'learning'],
            }),
            expect.any(Object),
        );
    });

    it('prefills account email when adding and saves a custom destination address', async () => {
        const view = renderPage();
        await view.user.click(
            screen.getByRole('button', { name: 'Add email' }),
        );
        const dialog = await screen.findByRole('dialog');
        const address = within(dialog).getByRole('textbox', {
            name: /^Email address$/,
        });
        expect(address).toHaveValue('reader@example.test');
        await view.user.clear(address);
        await view.user.type(address, 'destination@example.test');
        await view.user.click(
            within(dialog).getByRole('button', { name: /^Add$/ }),
        );
        expect(view.post).toHaveBeenCalledWith(
            '/settings/delivery',
            expect.objectContaining({
                type: 'email',
                address: 'destination@example.test',
                kinds: ['news'],
            }),
            expect.any(Object),
        );
    });

    it('updates an email enable switch without offering a second email', async () => {
        const props = deliveryProps();
        props.email = {
            ...destinationProps(),
            id: 23,
            type: 'email',
            name: 'Email',
            target: { address: 'destination@example.test' },
        };
        const view = renderPage(props);
        expect(
            screen.queryByRole('button', { name: 'Add email' }),
        ).not.toBeInTheDocument();
        await view.user.click(
            screen.getByRole('switch', { name: /Enable.*Email/i }),
        );
        expect(view.put).toHaveBeenCalledWith(
            '/settings/delivery/23',
            expect.objectContaining({ enabled: false, kinds: ['news'] }),
            expect.any(Object),
        );
    });

    it('clears a replaced webhook URL once it is saved', async () => {
        const props = deliveryProps();
        const view = renderPage(props);
        const field = screen.getByLabelText('Replace webhook URL');
        await view.user.type(field, 'https://example.com/new');
        await view.user.tab();
        expect(view.put).toHaveBeenCalledOnce();
        const [, data, options] = view.put.mock.calls[0];
        expect(data).toMatchObject({ webhook_url: 'https://example.com/new' });
        options?.onSuccess?.({} as never);
        await view.rendered.rerender({
            webhooks: [{ ...props.webhooks[0], name: 'Renamed' }],
        });
        expect(field).toHaveValue('');
        await view.user.click(field);
        await view.user.tab();
        expect(view.put).toHaveBeenCalledOnce();
    });

    it('keeps a rejected webhook URL in the field', async () => {
        const view = renderPage();
        const field = screen.getByLabelText('Replace webhook URL');
        await view.user.type(field, 'https://example.com/new');
        await view.user.tab();
        view.put.mock.calls[0][2]?.onError?.({ webhook_url: 'Invalid.' });
        expect(field).toHaveValue('https://example.com/new');
    });

    it('tests a saved destination through its settings endpoint', async () => {
        http.submit.mockResolvedValueOnce({ ok: true, message: 'Test sent.' });
        const props = deliveryProps();
        props.guilds = [];
        const view = renderPage(props);
        await view.user.click(
            screen.getByRole('button', { name: 'Send a test' }),
        );
        expect(await screen.findByText('Test sent.')).toBeVisible();
        const [first, second] = http.submit.mock.calls[0];
        expect(
            typeof first === 'string' ? { method: first, url: second } : first,
        ).toMatchObject({ method: 'post', url: '/settings/delivery/21/test' });
        expect(view.post).not.toHaveBeenCalled();
    });

    it('deletes a webhook only after confirming the destination dialog', async () => {
        const props = deliveryProps();
        props.guilds = [];
        const view = renderPage(props);
        await view.user.click(screen.getByRole('button', { name: /^Delete$/ }));
        const dialog = await screen.findByRole('alertdialog');
        expect(view.destroy).not.toHaveBeenCalled();
        await view.user.click(
            within(dialog).getByRole('button', { name: /^Delete$/ }),
        );
        expect(view.destroy).toHaveBeenCalledWith(
            '/settings/delivery/21',
            expect.any(Object),
        );
    });

    it('warns that disconnect leaves the bot in the server and requires confirmation', async () => {
        const view = renderPage();
        const disconnect = screen.getByRole('button', { name: /^Disconnect$/ });
        expect(disconnect).toHaveClass(
            'text-destructive',
            'hover:bg-destructive/10',
            'hover:text-destructive',
            'dark:hover:bg-destructive/20',
        );
        await view.user.click(disconnect);
        const dialog = await screen.findByRole('alertdialog');
        expect(
            within(dialog).getByText(/bot.*stay|bot.*remain/i),
        ).toBeVisible();
        expect(view.destroy).not.toHaveBeenCalled();
        await view.user.click(
            within(dialog).getByRole('button', { name: /^Cancel$/ }),
        );
        expect(view.destroy).not.toHaveBeenCalled();
        await view.user.click(disconnect);
        const confirmation = await screen.findByRole('alertdialog');
        await view.user.click(
            within(confirmation).getByRole('button', { name: /^Disconnect$/ }),
        );
        expect(view.destroy).toHaveBeenCalledWith(
            '/settings/delivery/guilds/31',
            expect.any(Object),
        );
    });
});
