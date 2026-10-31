import { fireEvent, render, screen, waitFor } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { defineComponent, ref } from 'vue';
import { describe, expect, it } from 'vite-plus/test';
import HelpTip from '@/components/pulse/HelpTip.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';

function renderTip() {
    render(HelpTip, {
        props: { label: 'Feeds', text: 'Feed definition.' },
    });
    return screen.getByRole('button', { name: 'Help: Feeds' });
}

async function pointer(
    trigger: HTMLElement,
    type: string,
    pointerType: string,
) {
    const event = new Event(type, { bubbles: false });
    Object.defineProperty(event, 'pointerType', { value: pointerType });
    await fireEvent(trigger, event);
}

describe('HelpTip', () => {
    it('keeps its labeled button focusable without opening on focus', async () => {
        const trigger = renderTip();
        expect(trigger).toHaveAttribute('type', 'button');
        trigger.focus();
        expect(trigger).toHaveFocus();
        await waitFor(() =>
            expect(trigger).toHaveAttribute('aria-expanded', 'false'),
        );
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('toggles on click and closes on Escape without moving focus', async () => {
        const trigger = renderTip();
        const user = userEvent.setup();
        await user.click(trigger);
        expect(await screen.findByRole('dialog')).toHaveTextContent(
            'Feed definition.',
        );
        expect(trigger).toHaveFocus();
        await user.click(trigger);
        await waitFor(() =>
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
        );
        await user.click(trigger);
        await screen.findByRole('dialog');
        await user.keyboard('{Escape}');
        await waitFor(() =>
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
        );
    });

    it.each(['{Enter}', ' '])('opens with keyboard %s', async (key) => {
        const trigger = renderTip();
        trigger.focus();
        await userEvent.setup().keyboard(key);
        expect(await screen.findByRole('dialog')).toHaveTextContent(
            'Feed definition.',
        );
    });

    it('opens on mouse hover and closes on mouse leave', async () => {
        const trigger = renderTip();
        await pointer(trigger, 'pointerenter', 'mouse');
        expect(await screen.findByRole('dialog')).toHaveTextContent(
            'Feed definition.',
        );
        await pointer(trigger, 'pointerleave', 'mouse');
        await waitFor(() =>
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
        );
    });

    it('keeps a hover-opened tip open when clicked, until the next click', async () => {
        const trigger = renderTip();
        const user = userEvent.setup();
        await pointer(trigger, 'pointerenter', 'mouse');
        await screen.findByRole('dialog');
        await user.click(trigger);
        await pointer(trigger, 'pointerleave', 'mouse');
        expect(await screen.findByRole('dialog')).toBeVisible();
        await user.click(trigger);
        await waitFor(() =>
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
        );
    });

    it('opens on the first click after Escape closed a hover-opened tip', async () => {
        const trigger = renderTip();
        const user = userEvent.setup();
        await pointer(trigger, 'pointerenter', 'mouse');
        await screen.findByRole('dialog');
        await user.keyboard('{Escape}');
        await waitFor(() =>
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
        );
        await user.click(trigger);
        expect(await screen.findByRole('dialog')).toBeVisible();
    });

    it.each(['touch', 'pen'])('ignores %s hover', async (pointerType) => {
        const trigger = renderTip();
        await pointer(trigger, 'pointerenter', pointerType);
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        await userEvent.setup().click(trigger);
        expect(await screen.findByRole('dialog')).toBeVisible();
        await pointer(trigger, 'pointerleave', pointerType);
        expect(screen.getByRole('dialog')).toBeVisible();
    });

    it('closes a dialog on the first Escape with a focused closed HelpTip', async () => {
        render(
            defineComponent({
                components: {
                    Dialog,
                    DialogContent,
                    DialogDescription,
                    DialogTitle,
                    HelpTip,
                },
                setup: () => ({ open: ref(true) }),
                template: `
                <Dialog v-model:open="open">
                    <DialogContent>
                        <DialogTitle>Source settings</DialogTitle>
                        <DialogDescription>Edit your source.</DialogDescription>
                        <HelpTip label="Feeds" text="Feed definition." />
                    </DialogContent>
                </Dialog>
            `,
            }),
        );
        await screen.findByRole('dialog', { name: 'Source settings' });
        const trigger = screen.getByRole('button', { name: 'Help: Feeds' });
        trigger.focus();
        expect(trigger).toHaveAttribute('aria-expanded', 'false');
        await userEvent.setup().keyboard('{Escape}');
        await waitFor(() =>
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
        );
    });
});
