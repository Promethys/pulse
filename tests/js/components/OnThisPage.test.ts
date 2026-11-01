import { render, screen } from '@testing-library/vue';
import { nextTick } from 'vue';
import { afterEach, expect, it, vi } from 'vite-plus/test';
import OnThisPage from '@/components/pulse/OnThisPage.vue';
import OnThisPageCollapsible from '@/components/pulse/OnThisPageCollapsible.vue';

afterEach(() => vi.unstubAllGlobals());

it('tracks the heading above the reading line and stops on unmount', async () => {
    vi.stubGlobal('requestAnimationFrame', (run: FrameRequestCallback) => {
        run(0);

        return 0;
    });
    const remove = vi.spyOn(window, 'removeEventListener');
    const one = document.createElement('h2');
    one.id = 'one';
    const two = document.createElement('h3');
    two.id = 'two';
    document.body.append(one, two);
    vi.spyOn(one, 'getBoundingClientRect').mockReturnValue({
        top: 100,
    } as DOMRect);
    const twoTop = vi
        .spyOn(two, 'getBoundingClientRect')
        .mockReturnValue({ top: 900 } as DOMRect);
    const view = render(OnThisPage, {
        props: {
            items: [
                { id: 'one', text: 'One', level: 2 },
                { id: 'two', text: 'Two', level: 3 },
            ],
        },
    });
    await nextTick();
    expect(screen.getByRole('link', { name: 'One' })).toHaveAttribute(
        'aria-current',
        'location',
    );
    twoTop.mockReturnValue({ top: 50 } as DOMRect);
    window.dispatchEvent(new Event('scroll'));
    await nextTick();
    expect(screen.getByRole('link', { name: 'Two' })).toHaveAttribute(
        'href',
        '#two',
    );
    expect(screen.getByRole('link', { name: 'Two' })).toHaveAttribute(
        'aria-current',
        'location',
    );
    view.unmount();
    expect(remove).toHaveBeenCalledWith('scroll', expect.any(Function));
    one.remove();
    two.remove();
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
