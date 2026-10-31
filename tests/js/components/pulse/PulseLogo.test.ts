import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import PulseLogo from '@/components/pulse/PulseLogo.vue';

describe('PulseLogo', () => {
    it.each([undefined, 'sm', 'md'] as const)(
        'keeps the brand accessible at size %s',
        (size) => {
            const { container } = render(PulseLogo, { props: { size } });

            expect(screen.getByText('Pulse')).toHaveClass('font-display');
            const mark = container.querySelector('svg');
            expect(mark).toHaveAttribute('aria-hidden', 'true');
            expect(mark).not.toHaveClass('lucide-activity');
        },
    );

    it('uses a smaller footprint for sm than md', async () => {
        const result = render(PulseLogo, {
            props: { size: 'sm' },
        });
        const { container } = result;
        const small = container.innerHTML;

        await result.rerender({ size: 'md' });

        expect(screen.getByText('Pulse')).toBeVisible();
        expect(container.innerHTML).not.toBe(small);
    });
});
