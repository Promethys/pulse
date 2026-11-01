import { render, screen } from '@testing-library/vue';
import { nextTick } from 'vue';
import { afterEach, expect, it, vi } from 'vite-plus/test';
import OnThisPage from '@/components/pulse/OnThisPage.vue';
import OnThisPageCollapsible from '@/components/pulse/OnThisPageCollapsible.vue';

afterEach(() => vi.unstubAllGlobals());

it('tracks visible headings and disconnects on unmount', async () => {
    let callback: IntersectionObserverCallback = () => {};
    const disconnect = vi.fn();
    vi.stubGlobal(
        'IntersectionObserver',
        class {
            constructor(handler: IntersectionObserverCallback) {
                callback = handler;
            }
            observe = vi.fn();
            disconnect = disconnect;
        },
    );
    const { unmount } = render(OnThisPage, {
        props: {
            items: [
                { id: 'one', text: 'One', level: 2 },
                { id: 'two', text: 'Two', level: 3 },
            ],
        },
    });
    expect(screen.getByRole('link', { name: 'One' })).toHaveAttribute(
        'aria-current',
        'location',
    );
    callback(
        [
            { target: { id: 'two' }, isIntersecting: true },
        ] as unknown as IntersectionObserverEntry[],
        {} as IntersectionObserver,
    );
    await nextTick();
    expect(screen.getByRole('link', { name: 'Two' })).toHaveAttribute(
        'href',
        '#two',
    );
    expect(screen.getByRole('link', { name: 'Two' })).toHaveAttribute(
        'aria-current',
        'location',
    );
    unmount();
    expect(disconnect).toHaveBeenCalledOnce();
});

it('handles an empty heading list', () => {
    render(OnThisPage, { props: { items: [] } });
    expect(
        screen.queryByRole('navigation', { name: 'On this page' }),
    ).not.toBeInTheDocument();
});

it('offers a native collapsible heading list on mobile', () => {
    const { container } = render(OnThisPageCollapsible, {
        props: { items: [{ id: 'one', text: 'One', level: 2 }] },
    });
    expect(container.querySelector('details')).not.toHaveAttribute('open');
    expect(container.querySelector('summary')).toHaveTextContent(
        'On this page',
    );
    expect(container.querySelector('a')).toHaveAttribute('href', '#one');
});
