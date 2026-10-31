import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import ClampedProse from '@/components/pulse/ClampedProse.vue';

function stubHeights(scrollHeight: number, clientHeight: number) {
    vi.spyOn(HTMLElement.prototype, 'scrollHeight', 'get').mockReturnValue(
        scrollHeight,
    );
    vi.spyOn(HTMLElement.prototype, 'clientHeight', 'get').mockReturnValue(
        clientHeight,
    );
}

describe('ClampedProse', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('renders the HTML clamped, without a toggle when nothing is cut', () => {
        stubHeights(100, 100);
        render(ClampedProse, {
            props: { html: '<p>Short <code>text</code></p>' },
        });

        expect(screen.getByText('text').tagName).toBe('CODE');
        expect(screen.getByText('text').closest('.pulse-prose')).toHaveClass(
            'line-clamp-6',
        );
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });

    it('expands and collapses long text in place', async () => {
        stubHeights(300, 120);
        const user = userEvent.setup();
        render(ClampedProse, { props: { html: '<p>Long text</p>' } });
        const prose = screen.getByText('Long text').closest('.pulse-prose');
        const more = await screen.findByRole('button', { name: 'Read more' });

        expect(more).toHaveAttribute('aria-expanded', 'false');

        await user.click(more);

        expect(prose).not.toHaveClass('line-clamp-6');
        expect(
            screen.getByRole('button', { name: 'Show less' }),
        ).toHaveAttribute('aria-expanded', 'true');

        await user.click(screen.getByRole('button', { name: 'Show less' }));

        expect(prose).toHaveClass('line-clamp-6');
    });

    it('reports the expanded state to its parent', async () => {
        stubHeights(300, 120);
        const user = userEvent.setup();
        const view = render(ClampedProse, {
            props: { html: '<p>Long text</p>' },
        });

        await user.click(
            await screen.findByRole('button', { name: 'Read more' }),
        );

        expect(view.emitted('update:expanded')).toEqual([[true]]);
    });
});
