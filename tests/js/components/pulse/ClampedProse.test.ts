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

    it('adds a Copy button to each code block that copies its code', async () => {
        stubHeights(100, 100);
        const user = userEvent.setup();
        const writeText = vi
            .spyOn(navigator.clipboard, 'writeText')
            .mockResolvedValue();
        const view = render(ClampedProse, {
            props: {
                html: '<p>Use <code>inline</code></p><pre><code>npm test\n</code></pre>',
            },
        });
        const copy = screen.getByRole('button', { name: 'Copy code' });

        expect(copy).toHaveClass('code-copy');
        expect(
            copy
                .closest('.pulse-prose .code-block')
                ?.querySelector(':scope > pre'),
        ).toHaveTextContent('npm test');
        expect(view.container.querySelectorAll('.code-copy')).toHaveLength(1);

        await user.click(copy);

        expect(writeText).toHaveBeenCalledWith('npm test');
        expect(screen.getByRole('button', { name: 'Copied' })).toBeVisible();
    });

    it('rebinds Copy buttons when the HTML changes', async () => {
        stubHeights(100, 100);
        const view = render(ClampedProse, {
            props: { html: '<pre><code>one</code></pre>' },
        });

        await view.rerender({
            html: '<pre><code>two</code></pre><pre><code>three</code></pre>',
        });

        expect(
            screen.getAllByRole('button', { name: 'Copy code' }),
        ).toHaveLength(2);
        expect(view.container.querySelectorAll('.code-copy')).toHaveLength(2);
        expect(view.container.querySelectorAll('.code-block')).toHaveLength(2);
        expect(view.container.querySelectorAll('pre')).toHaveLength(2);
    });

    it('removes its Copy buttons when unmounted', () => {
        stubHeights(100, 100);
        const view = render(ClampedProse, {
            props: { html: '<pre><code>one</code></pre>' },
        });
        const prose = view.container.querySelector(
            '.pulse-prose',
        ) as HTMLElement;
        const pre = view.container.querySelector('pre') as HTMLElement;

        expect(
            pre.parentElement?.querySelector(':scope > .code-copy'),
        ).not.toBeNull();

        view.unmount();

        expect(prose.querySelector('.code-copy')).toBeNull();
        expect(prose.querySelector('.code-block')).toBeNull();
        expect(pre.parentElement).toBe(prose);
    });
});
