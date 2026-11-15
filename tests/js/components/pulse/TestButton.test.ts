import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import TestButton from '@/components/pulse/TestButton.vue';
import type { KeyTestResult } from '@/types/onboarding';

describe('TestButton', () => {
    it('reserves both labels while exposing only the active accessible name', async () => {
        let resolve!: (result: KeyTestResult) => void;
        const onTest = vi.fn(
            () =>
                new Promise<KeyTestResult>((done) => {
                    resolve = done;
                }),
        );
        render(TestButton, {
            props: { label: 'Test this destination', onTest },
        });
        const button = screen.getByRole('button', {
            name: 'Test this destination',
        });
        const idle = screen.getByText('Test this destination');
        const loading = screen.getByText('Testing…');
        expect(button.querySelector('.lucide-zap')).toBeInTheDocument();
        expect(idle).toHaveClass('[grid-area:1/1]');
        expect(loading).toHaveClass('[grid-area:1/1]', 'invisible');
        expect(loading).toHaveAttribute('aria-hidden', 'true');
        expect(idle).not.toHaveAttribute('aria-hidden', 'true');
        expect(idle.parentElement).toHaveClass('grid', 'justify-items-start');

        await userEvent.setup().click(button);
        expect(screen.getByRole('button', { name: 'Testing…' })).toBeDisabled();
        expect(
            screen.queryByRole('button', { name: 'Test this destination' }),
        ).not.toBeInTheDocument();
        expect(idle).toHaveClass('invisible');
        expect(idle).toHaveAttribute('aria-hidden', 'true');
        expect(loading).not.toHaveClass('invisible');
        expect(loading).not.toHaveAttribute('aria-hidden', 'true');
        expect(
            button.querySelector('.lucide-loader-circle'),
        ).toBeInTheDocument();

        resolve({ ok: true, message: 'Test sent.' });
        expect(
            await screen.findByRole('button', {
                name: 'Test this destination',
            }),
        ).toBeEnabled();
        expect(button.querySelector('.lucide-check')).toBeInTheDocument();
    });

    it.each([true, false])('shows the result icon for ok=%s', async (ok) => {
        render(TestButton, {
            props: { onTest: async () => ({ ok, message: 'Result.' }) },
        });
        const button = screen.getByRole('button', { name: 'Send a test' });
        await userEvent.setup().click(button);
        expect(
            button.querySelector(ok ? '.lucide-check' : '.lucide-x'),
        ).toHaveAttribute('aria-hidden', 'true');
        expect(button).toHaveAccessibleName('Send a test');
    });
});
