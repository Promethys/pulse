import userEvent from '@testing-library/user-event';
import { render, screen } from '@testing-library/vue';
import { defineComponent, h, nextTick, ref } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { useCodeCopy } from '@/composables/useCodeCopy';

const Harness = defineComponent({
    props: { html: { type: String, required: true } },
    setup(props) {
        const container = ref<HTMLElement>();
        useCodeCopy(container, () => props.html);

        return () => h('div', { ref: container, innerHTML: props.html });
    },
});

function renderHarness(html: string) {
    return render(Harness, { props: { html } });
}

function expectOneButtonPerPre(root: Element) {
    root.querySelectorAll('pre').forEach((pre) => {
        const wrapper = pre.parentElement as HTMLElement;
        expect(wrapper).toHaveClass('code-block');
        expect(wrapper.children).toHaveLength(2);
        expect(wrapper.firstElementChild).toBe(pre);
        expect(wrapper.querySelectorAll(':scope > .code-copy')).toHaveLength(1);
        expect(pre.querySelector('.code-copy')).toBeNull();
    });
    expect(root.querySelectorAll('.code-block')).toHaveLength(
        root.querySelectorAll('pre').length,
    );
    expect(root.querySelectorAll('.code-block .code-block')).toHaveLength(0);
}

describe('useCodeCopy', () => {
    afterEach(() => {
        vi.restoreAllMocks();
        vi.useRealTimers();
    });

    it('adds one labelled icon button with a tooltip to every pre', () => {
        const view = renderHarness(
            '<p>Intro <code>inline</code></p><pre><code>one</code></pre><pre><code>two</code></pre>',
        );
        const buttons = screen.getAllByRole('button', { name: 'Copy code' });

        expect(buttons).toHaveLength(2);
        for (const button of buttons) {
            expect(button).toHaveClass('code-copy');
            expect(button).toHaveAttribute('type', 'button');
            expect(button.textContent).toBe('');
            expect(button).toHaveAttribute('data-tooltip', 'Copy code');
            expect(button).not.toHaveAttribute('title');
            const icon = button.querySelector('svg');
            expect(icon).toHaveAttribute('viewBox', '0 0 24 24');
            expect(icon).toHaveAttribute('aria-hidden', 'true');
            expect(icon).toHaveAttribute('stroke', 'currentColor');
            expect(icon).toHaveAttribute('fill', 'none');
            expect(icon).toHaveAttribute('stroke-width', '2');
            expect(icon).toHaveAttribute('stroke-linecap', 'round');
            expect(icon).toHaveAttribute('stroke-linejoin', 'round');
            expect(icon?.namespaceURI).toBe('http://www.w3.org/2000/svg');
            expect(icon?.querySelector('rect')).not.toBeNull();
        }
        expectOneButtonPerPre(view.container);
        expect(view.container.querySelector('p .code-copy')).toBeNull();
    });

    it('adds nothing when there is no code block', () => {
        renderHarness('<p>Just <code>inline</code> code</p>');

        expect(screen.queryByRole('button')).not.toBeInTheDocument();
        expect(screen.queryByText('inline')?.closest('.code-block')).toBeNull();
    });

    it('keeps one wrapper per pre when the same HTML is rendered again', async () => {
        const html = '<p>Intro</p><pre><code>same</code></pre>';
        const view = renderHarness(html);

        await view.rerender({ html });
        await view.rerender({ html: `${html}<p>More</p>` });

        expectOneButtonPerPre(view.container);
        expect(view.container.querySelectorAll('.code-block')).toHaveLength(1);
        expect(
            screen.getAllByRole('button', { name: 'Copy code' }),
        ).toHaveLength(1);
    });

    it('copies the code text with the trailing newline trimmed', async () => {
        const user = userEvent.setup();
        const writeText = vi
            .spyOn(navigator.clipboard, 'writeText')
            .mockResolvedValue();
        renderHarness(
            '<pre><code>first\nsecond\n</code></pre><pre><code>other</code></pre>',
        );
        const [first, second] = screen.getAllByRole('button', {
            name: 'Copy code',
        });

        await user.click(first);
        expect(writeText).toHaveBeenLastCalledWith('first\nsecond');

        await user.click(second);
        expect(writeText).toHaveBeenLastCalledWith('other');
    });

    it('copies the pre text when it has no code element', async () => {
        const user = userEvent.setup();
        const writeText = vi
            .spyOn(navigator.clipboard, 'writeText')
            .mockResolvedValue();
        renderHarness('<pre>plain text\n</pre>');

        await user.click(screen.getByRole('button', { name: 'Copy code' }));

        expect(writeText).toHaveBeenCalledWith('plain text');
    });

    it('shows the check icon and Copied tooltip for 1.5 seconds, then resets', async () => {
        userEvent.setup();
        vi.spyOn(navigator.clipboard, 'writeText').mockResolvedValue();
        vi.useFakeTimers();
        renderHarness('<pre><code>hello</code></pre>');

        const button = screen.getByRole('button', { name: 'Copy code' });
        const copyIcon = button.querySelector('svg')?.outerHTML;
        button.click();
        await nextTick();

        const copied = screen.getByRole('button', { name: 'Copied' });
        expect(copied.textContent).toBe('');
        expect(copied).toHaveAttribute('data-tooltip', 'Copied');
        expect(copied.querySelector('svg')).toHaveAttribute(
            'aria-hidden',
            'true',
        );
        expect(copied.querySelector('svg rect')).toBeNull();
        expect(copied.querySelector('svg path')).toHaveAttribute(
            'd',
            'M20 6 9 17l-5-5',
        );

        await vi.advanceTimersByTimeAsync(1499);
        expect(screen.getByRole('button', { name: 'Copied' })).toBe(copied);

        await vi.advanceTimersByTimeAsync(1);
        const copy = screen.getByRole('button', { name: 'Copy code' });
        expect(copy.textContent).toBe('');
        expect(copy).toHaveAttribute('data-tooltip', 'Copy code');
        expect(copy.querySelector('svg')?.outerHTML).toBe(copyIcon);
        expect(vi.getTimerCount()).toBe(0);
    });

    it('restarts the 1.5 second delay on a second click', async () => {
        userEvent.setup();
        vi.spyOn(navigator.clipboard, 'writeText').mockResolvedValue();
        vi.useFakeTimers();
        renderHarness('<pre><code>hello</code></pre>');
        const button = screen.getByRole('button', { name: 'Copy code' });

        button.click();
        await nextTick();
        await vi.advanceTimersByTimeAsync(1000);
        button.click();
        await nextTick();
        await vi.advanceTimersByTimeAsync(1000);

        expect(button).toHaveAccessibleName('Copied');
        expect(vi.getTimerCount()).toBe(1);

        await vi.advanceTimersByTimeAsync(500);
        expect(button).toHaveAccessibleName('Copy code');
    });

    it('replaces the buttons on re-render without duplicates', async () => {
        const user = userEvent.setup();
        const writeText = vi
            .spyOn(navigator.clipboard, 'writeText')
            .mockResolvedValue();
        const view = renderHarness('<pre><code>old</code></pre>');

        await view.rerender({
            html: '<pre><code>new</code></pre><pre><code>other</code></pre>',
        });

        expect(
            screen.getAllByRole('button', { name: 'Copy code' }),
        ).toHaveLength(2);
        expectOneButtonPerPre(view.container);
        expect(view.container.querySelectorAll('pre')).toHaveLength(2);

        await user.click(
            screen.getAllByRole('button', { name: 'Copy code' })[0],
        );
        expect(writeText).toHaveBeenCalledTimes(1);
        expect(writeText).toHaveBeenCalledWith('new');
    });

    it('clears a pending Copied timer when the source changes', async () => {
        userEvent.setup();
        vi.spyOn(navigator.clipboard, 'writeText').mockResolvedValue();
        vi.useFakeTimers();
        const view = renderHarness('<pre><code>old</code></pre>');

        screen.getByRole('button', { name: 'Copy code' }).click();
        await nextTick();
        expect(vi.getTimerCount()).toBe(1);

        await view.rerender({ html: '<pre><code>new</code></pre>' });

        expect(vi.getTimerCount()).toBe(0);
        expect(screen.getByRole('button', { name: 'Copy code' })).toBeVisible();
    });

    it('removes its buttons, listeners and timers on unmount', async () => {
        userEvent.setup();
        const writeText = vi
            .spyOn(navigator.clipboard, 'writeText')
            .mockResolvedValue();
        vi.useFakeTimers();
        const view = renderHarness(
            '<p>Before</p><pre><code>hello</code></pre><p>After</p>',
        );
        const root = view.container.firstElementChild as HTMLElement;
        const pre = view.container.querySelector('pre') as HTMLElement;
        const button = screen.getByRole('button', { name: 'Copy code' });

        button.click();
        await nextTick();
        expect(vi.getTimerCount()).toBe(1);

        view.unmount();

        expect(vi.getTimerCount()).toBe(0);
        expect(pre.querySelector('.code-copy')).toBeNull();
        expect(button.isConnected).toBe(false);
        expect(root.querySelector('.code-block')).toBeNull();
        expect(pre.parentElement).toBe(root);
        expect(Array.from(root.children, (el) => el.tagName)).toEqual([
            'P',
            'PRE',
            'P',
        ]);

        writeText.mockClear();
        button.click();
        await nextTick();
        expect(writeText).not.toHaveBeenCalled();
    });

    it('does not throw or show Copied without a clipboard API', async () => {
        const user = userEvent.setup();
        vi.spyOn(navigator, 'clipboard', 'get').mockReturnValue(
            undefined as unknown as Clipboard,
        );
        renderHarness('<pre><code>hello</code></pre>');

        await user.click(screen.getByRole('button', { name: 'Copy code' }));

        expect(
            screen.queryByRole('button', { name: 'Copied' }),
        ).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Copy code' })).toBeVisible();
    });

    it('does not show Copied when the clipboard rejects', async () => {
        const user = userEvent.setup();
        vi.spyOn(navigator.clipboard, 'writeText').mockRejectedValue(
            new Error('Denied'),
        );
        renderHarness('<pre><code>hello</code></pre>');

        await user.click(screen.getByRole('button', { name: 'Copy code' }));

        expect(
            screen.queryByRole('button', { name: 'Copied' }),
        ).not.toBeInTheDocument();
    });
});
