import { router } from '@inertiajs/vue3';
import { render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import AiKeys from '@/pages/settings/AiKeys.vue';
import { http, page } from '../../support/inertia';
import { aiKeysProps } from '../../support/settingsProps';

function renderPage(props = aiKeysProps()) {
    page.url = '/settings/ai-keys';
    page.props = { ...props, errors: {} };
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    const put = vi.spyOn(router, 'put').mockImplementation(() => {});
    const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
    const rendered = render(AiKeys, { props });
    return { post, put, destroy, rendered, user: userEvent.setup() };
}

describe('AI keys settings', () => {
    it('masks keys and marks only the first as default with bounded ordering controls', async () => {
        const view = renderPage();
        expect(screen.getAllByText('Default')).toHaveLength(1);
        expect(screen.getByText(/••••\s*abcd/)).toBeVisible();
        expect(screen.getByText('gemini-3.8-flash')).toBeVisible();
        const up = screen.getAllByRole('button', { name: /Move up/i });
        const down = screen.getAllByRole('button', { name: /Move down/i });
        expect(up[0]).toBeDisabled();
        expect(down[1]).toBeDisabled();
        await view.user.click(down[0]);
        expect(view.put).toHaveBeenCalledWith(
            '/settings/ai-keys/order',
            { ids: [12, 11] },
            expect.any(Object),
        );
    });

    it('moves the second key up using the full ordered id list', async () => {
        const view = renderPage();
        await view.user.click(
            screen.getAllByRole('button', { name: /Move up/i })[1],
        );
        expect(view.put).toHaveBeenCalledWith(
            '/settings/ai-keys/order',
            { ids: [12, 11] },
            expect.any(Object),
        );
    });

    it.each([false, true])(
        'shows the empty account message with instance notice %s',
        (instance) => {
            const props = aiKeysProps();
            props.keys = [];
            props.uses_instance_ai_notice = instance;
            renderPage(props);
            if (instance) {
                expect(
                    screen.getByText(
                        "Pulse's own key is used for your digests.",
                    ),
                ).toBeVisible();
                expect(
                    screen.queryByText(
                        "Your digest won't run until you add one.",
                    ),
                ).not.toBeInTheDocument();
            } else {
                expect(screen.getByText('No AI key yet')).toBeVisible();
                expect(
                    screen.getByText(
                        "Your digest won't run until you add one.",
                    ),
                ).toBeVisible();
            }
        },
    );

    it('adds a key through the dialog with consent and preserves the key explainer', async () => {
        const view = renderPage();
        await view.user.click(
            screen.getByRole('button', { name: 'Add a key' }),
        );
        const dialog = await screen.findByRole('dialog');
        expect(
            within(dialog).queryByText('Why your own key?'),
        ).not.toBeInTheDocument();
        expect(
            within(dialog).getByRole('button', { name: /^Add$/ }),
        ).toBeDisabled();
        await view.user.type(
            within(dialog).getByLabelText('Gemini API key'),
            'fake-private-key',
        );
        await view.user.click(
            within(dialog).getByRole('checkbox', {
                name: /stored encrypted.*own digests/i,
            }),
        );
        await view.user.click(
            within(dialog).getByRole('button', { name: /^Add$/ }),
        );
        expect(view.post).toHaveBeenCalledWith(
            '/settings/ai-keys',
            {
                provider: 'gemini',
                model: 'gemini-3.8-flash',
                api_key: 'fake-private-key',
                consent: true,
            },
            expect.any(Object),
        );
    });

    it('uses the settings endpoint to test a new key without saving or consenting', async () => {
        http.submit.mockResolvedValueOnce({ ok: true, message: 'Key works.' });
        const view = renderPage();
        await view.user.click(
            screen.getByRole('button', { name: 'Add a key' }),
        );
        const dialog = await screen.findByRole('dialog');
        await view.user.type(
            within(dialog).getByLabelText('Gemini API key'),
            'fake-new-key',
        );
        await view.user.click(
            within(dialog).getByRole('button', { name: 'Test the key' }),
        );
        expect(await within(dialog).findByText('Key works.')).toBeVisible();
        const [first, second] = http.submit.mock.calls[0];
        expect(
            typeof first === 'string' ? { method: first, url: second } : first,
        ).toMatchObject({ method: 'post', url: '/settings/ai-keys/test' });
        expect(view.post).not.toHaveBeenCalled();
    });

    it('tests a saved key and displays a transient failure without a save request', async () => {
        http.submit.mockResolvedValueOnce({
            ok: false,
            message: 'The provider rejected this key.',
        });
        const view = renderPage();
        await view.user.click(
            screen.getAllByRole('button', { name: /^Test$/ })[0],
        );
        expect(
            await screen.findByText('The provider rejected this key.'),
        ).toBeVisible();
        const [first, second] = http.submit.mock.calls[0];
        expect(
            typeof first === 'string' ? { method: first, url: second } : first,
        ).toMatchObject({ method: 'post', url: '/settings/ai-keys/11/test' });
        expect(view.post).not.toHaveBeenCalled();
    });

    it('shows the key test result on its own line, outside the actions', async () => {
        http.submit.mockResolvedValueOnce({
            ok: false,
            message: 'The provider rejected this key.',
        });
        const view = renderPage();
        const button = screen.getAllByRole('button', { name: /^Test$/ })[0];
        await view.user.click(button);
        const result = await screen.findByRole('status');
        expect(button.parentElement?.parentElement).not.toContainElement(
            result,
        );
        expect(button.closest('li')).toContainElement(result);
    });

    it('clears a key test result when the key is replaced', async () => {
        http.submit.mockResolvedValueOnce({ ok: true, message: 'Key works.' });
        const props = aiKeysProps();
        const view = renderPage(props);
        await view.user.click(
            screen.getAllByRole('button', { name: /^Test$/ })[0],
        );
        await screen.findByRole('status');
        const [first, ...others] = props.keys;
        await view.rendered.rerender({
            keys: [{ ...first, key_suffix: 'wxyz' }, ...others],
        });
        expect(screen.queryByRole('status')).not.toBeInTheDocument();
    });

    it('requires delete confirmation and lets cancellation keep the key', async () => {
        const view = renderPage();
        await view.user.click(
            screen.getAllByRole('button', { name: /^Delete$/ })[0],
        );
        const dialog = await screen.findByRole('alertdialog');
        expect(view.destroy).not.toHaveBeenCalled();
        await view.user.click(
            within(dialog).getByRole('button', { name: /^Cancel$/ }),
        );
        expect(view.destroy).not.toHaveBeenCalled();
        await view.user.click(
            screen.getAllByRole('button', { name: /^Delete$/ })[0],
        );
        const confirmation = await screen.findByRole('alertdialog');
        const remove = within(confirmation).getByRole('button', {
            name: /^Delete$/,
        });
        expect(remove).toHaveClass(
            'text-destructive',
            'hover:bg-destructive/10',
            'hover:text-destructive',
            'dark:hover:bg-destructive/20',
        );
        await view.user.click(remove);
        expect(view.destroy).toHaveBeenCalledWith(
            '/settings/ai-keys/11',
            expect.any(Object),
        );
    });
});
