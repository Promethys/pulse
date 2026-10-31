import { render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import DestinationRow from '@/components/pulse/DestinationRow.vue';
import { destinationProps } from '../../support/settingsProps';

function renderRow(channel = destinationProps()) {
    const onTest = vi
        .fn()
        .mockResolvedValue({ ok: true, message: 'Test sent.' });
    const onEnabled = vi.fn();
    const onDelete = vi.fn();
    const props = {
        channel,
        title: channel.name,
        subtitle: '…abcd',
        onTest,
        'onUpdate:enabled': onEnabled,
        onDelete,
    };
    const rendered = render(DestinationRow, {
        props,
        slots: { default: '<p>Destination controls</p>' },
    });
    return { onTest, onEnabled, onDelete, rendered, user: userEvent.setup() };
}

describe('DestinationRow', () => {
    it('shows masked destination details and emits the enable change', async () => {
        const view = renderRow();
        expect(screen.getByText('News hook')).toBeVisible();
        expect(screen.getByText('…abcd')).toBeVisible();
        expect(screen.getByText('Destination controls')).toBeVisible();
        expect(screen.getByText('Nothing delivered yet')).toBeVisible();
        const enabled = screen.getByRole('switch', {
            name: /Enable.*News hook/i,
        });
        expect(enabled).toBeChecked();
        await view.user.click(enabled);
        expect(view.onEnabled).toHaveBeenCalledWith(false);
    });

    it.each(['delivered', 'failed'])(
        'shows the latest %s status and the failure reason',
        (status) => {
            const channel = destinationProps();
            channel.latest_delivery = {
                id: 1,
                status,
                attempts: 1,
                last_error: status === 'failed' ? 'Missing access' : null,
                delivered_at:
                    status === 'delivered' ? '2026-10-28T09:02:00Z' : null,
                updated_at: '2026-10-28T09:02:00Z',
            };
            renderRow(channel);
            expect(
                screen.getByText(
                    status === 'delivered'
                        ? /Delivered.*28 Oct.*09:02/
                        : /Failed/,
                ),
            ).toBeVisible();
            if (status === 'failed')
                expect(screen.getByText('Missing access')).toBeVisible();
            expect(
                screen.queryByText('Nothing delivered yet'),
            ).not.toBeInTheDocument();
        },
    );

    it('awaits a test and displays its transient result', async () => {
        const view = renderRow();
        await view.user.click(
            screen.getByRole('button', { name: 'Send a test' }),
        );
        expect(view.onTest).toHaveBeenCalledOnce();
        expect(await screen.findByRole('status')).toHaveTextContent(
            'Test sent.',
        );
        expect(view.onDelete).not.toHaveBeenCalled();
    });

    it('shows the test result on its own line, outside the actions', async () => {
        const view = renderRow();
        const button = screen.getByRole('button', { name: 'Send a test' });
        await view.user.click(button);
        const result = await screen.findByRole('status');
        expect(button.parentElement?.parentElement).not.toContainElement(
            result,
        );
    });

    it('clears the test result when the destination changes', async () => {
        const view = renderRow();
        await view.user.click(
            screen.getByRole('button', { name: 'Send a test' }),
        );
        await screen.findByRole('status');
        await view.rendered.rerender({ subtitle: '…wxyz' });
        expect(screen.queryByRole('status')).not.toBeInTheDocument();
    });

    it('confirms deletion using danger styling', async () => {
        const view = renderRow();
        const remove = screen.getByRole('button', { name: /^Delete$/ });
        expect(remove).toHaveClass(
            'text-destructive',
            'hover:bg-destructive/10',
            'hover:text-destructive',
            'dark:hover:bg-destructive/20',
        );
        await view.user.click(remove);
        const dialog = await screen.findByRole('alertdialog');
        expect(view.onDelete).not.toHaveBeenCalled();
        await view.user.click(
            within(dialog).getByRole('button', { name: /^Cancel$/ }),
        );
        expect(view.onDelete).not.toHaveBeenCalled();
        await view.user.click(remove);
        const confirmation = await screen.findByRole('alertdialog');
        await view.user.click(
            within(confirmation).getByRole('button', { name: /^Delete$/ }),
        );
        expect(view.onDelete).toHaveBeenCalledOnce();
    });
});

describe('Paused delivery guidance', () => {
    it.each([
        ['discord_bot', false, 'failed', true],
        ['discord_webhook', false, 'failed', true],
        ['discord_webhook', true, 'failed', false],
        ['discord_webhook', false, 'delivered', false],
        ['discord_webhook', false, null, false],
        ['email', false, 'failed', false],
    ] as const)(
        'explains type=%s enabled=%s status=%s',
        (type, enabled, status, visible) => {
            const channel = { ...destinationProps(), type, enabled };
            if (status)
                channel.latest_delivery = {
                    id: 1,
                    status,
                    attempts: 1,
                    last_error: null,
                    delivered_at: null,
                    updated_at: null,
                };
            renderRow(channel);
            const message = screen.queryByText(
                'Delivery is paused. Fix the problem, send a test, then turn delivery back on.',
            );
            if (visible) expect(message).toBeVisible();
            else expect(message).not.toBeInTheDocument();
        },
    );
});

it.each(['delivered', 'failed', 'pending'])(
    'labels the %s delivery time in UTC',
    (status) => {
        const channel = destinationProps();
        channel.latest_delivery = {
            id: 1,
            status,
            attempts: 1,
            last_error: null,
            delivered_at:
                status === 'delivered' ? '2026-10-28T11:02:00+02:00' : null,
            updated_at: '2026-10-28T11:02:00+02:00',
        };
        renderRow(channel);
        expect(screen.getByText(/28 Oct, 09:02 UTC/)).toBeVisible();
    },
);
