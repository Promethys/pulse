import { render, screen } from '@testing-library/vue';
import { defineComponent, h, nextTick } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import StickySectionNav from '@/components/pulse/StickySectionNav.vue';

const sections = [
    { id: 'digest', label: 'The digest' },
    { id: 'delivery', label: 'Delivery' },
];

describe('StickySectionNav', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('provides fragment links and marks the first section without observer support', () => {
        render(StickySectionNav, { props: { sections } });
        expect(
            screen.getByRole('navigation', { name: 'Sections' }),
        ).toBeVisible();
        expect(
            screen.getByRole('link', { name: 'The digest' }),
        ).toHaveAttribute('href', '#digest');
        expect(
            screen.getByRole('link', { name: 'The digest' }),
        ).toHaveAttribute('aria-current', 'location');
        expect(screen.getByRole('link', { name: 'Delivery' })).toHaveAttribute(
            'href',
            '#delivery',
        );
        expect(
            screen.getByRole('link', { name: 'Delivery' }),
        ).not.toHaveAttribute('aria-current');
    });

    it('follows the reading line in both directions and at the page bottom', async () => {
        vi.stubGlobal('requestAnimationFrame', (run: FrameRequestCallback) => {
            run(0);

            return 0;
        });
        const remove = vi.spyOn(window, 'removeEventListener');
        const view = render(
            defineComponent({
                setup: () => () =>
                    h('div', [
                        h(StickySectionNav, { sections }),
                        ...sections.map(({ id }) => h('section', { id })),
                    ]),
            }),
        );
        const scrollTo = async (digest: number, delivery: number) => {
            for (const [id, top] of [
                ['digest', digest],
                ['delivery', delivery],
            ] as const) {
                vi.spyOn(
                    document.getElementById(id)!,
                    'getBoundingClientRect',
                ).mockReturnValue({ top } as DOMRect);
            }
            window.dispatchEvent(new Event('scroll'));
            await nextTick();
        };
        const current = (name: string) =>
            expect(screen.getByRole('link', { name })).toHaveAttribute(
                'aria-current',
                'location',
            );

        await scrollTo(-500, 100);
        current('Delivery');
        await scrollTo(100, 900);
        current('The digest');
        await scrollTo(-3000, -2000);
        current('Delivery');

        vi.spyOn(
            document.documentElement,
            'scrollHeight',
            'get',
        ).mockReturnValue(window.innerHeight + 100);
        vi.spyOn(window, 'scrollY', 'get').mockReturnValue(100);
        await scrollTo(100, 900);
        current('Delivery');

        window.history.replaceState(null, '', '#digest');
        window.dispatchEvent(new Event('hashchange'));
        await nextTick();
        current('The digest');
        window.history.replaceState(null, '', '#');

        view.unmount();
        expect(remove).toHaveBeenCalledWith('scroll', expect.any(Function));
    });

    it('replaces observed sections when its props change and skips missing elements', async () => {
        const target = document.createElement('section');
        target.id = 'digest';
        document.body.append(target);
        const view = render(StickySectionNav, {
            props: { sections },
        });
        await view.rerender({
            sections: [{ id: 'missing', label: 'Missing section' }],
        });
        expect(
            screen.getByRole('link', { name: 'Missing section' }),
        ).toHaveAttribute('aria-current', 'location');
        expect(
            screen.queryByRole('link', { name: 'The digest' }),
        ).not.toBeInTheDocument();
        view.unmount();
        target.remove();
    });

    it('handles empty sections', () => {
        render(StickySectionNav, { props: { sections: [] } });
        expect(screen.queryByRole('link')).not.toBeInTheDocument();
    });

    it.each([
        ['below', 180, 32, 20, 112],
        ['above', 20, 32, 100, 20],
        ['inside', 70, 20, 20, 20],
    ] as const)(
        'keeps the active link visible when it is %s the nav viewport without scrolling the window',
        async (_, offsetTop, offsetHeight, scrollTop, expected) => {
            vi.stubGlobal(
                'requestAnimationFrame',
                (run: FrameRequestCallback) => {
                    run(0);

                    return 0;
                },
            );
            const scrollWindow = vi.spyOn(window, 'scrollTo');
            const scrollIntoView = vi.spyOn(
                Element.prototype,
                'scrollIntoView',
            );
            const view = render(
                defineComponent({
                    setup: () => () =>
                        h('div', [
                            h(StickySectionNav, { sections }),
                            ...sections.map(({ id }) => h('section', { id })),
                        ]),
                }),
            );
            await nextTick();
            const delivery = vi.spyOn(
                document.getElementById('delivery')!,
                'getBoundingClientRect',
            );
            delivery.mockReturnValue({ top: 900 } as DOMRect);
            window.dispatchEvent(new Event('scroll'));
            await nextTick();
            const nav = screen.getByRole('navigation', { name: 'Sections' });
            const link = screen.getByRole('link', { name: 'Delivery' });
            Object.defineProperty(nav, 'clientHeight', { value: 100 });
            Object.defineProperties(link, {
                offsetTop: { value: offsetTop },
                offsetHeight: { value: offsetHeight },
            });
            nav.scrollTop = scrollTop;
            const windowScrollTop = window.scrollY;
            delivery.mockReturnValue({ top: 100 } as DOMRect);

            window.dispatchEvent(new Event('scroll'));
            await nextTick();

            expect(link).toHaveAttribute('aria-current', 'location');
            expect(nav.scrollTop).toBe(expected);
            expect(window.scrollY).toBe(windowScrollTop);
            expect(scrollWindow).not.toHaveBeenCalled();
            expect(scrollIntoView).not.toHaveBeenCalled();
            view.unmount();
        },
    );
});

it('caps the sticky navigation to the space below the public header', () => {
    render(StickySectionNav, { props: { sections } });
    expect(screen.getByRole('navigation', { name: 'Sections' })).toHaveClass(
        'lg:max-h-[calc(100svh-7rem)]',
        'lg:overflow-y-auto',
        'lg:top-24',
        'lg:self-start',
    );
});

it('shrinks within the remaining parent space as the footer enters view', async () => {
    const view = render(
        defineComponent({
            setup: () => () => h('div', [h(StickySectionNav, { sections })]),
        }),
    );
    const nav = screen.getByRole('navigation', { name: 'Sections' });
    nav.style.top = '96px';
    let clientHeight = 404;
    Object.defineProperty(nav, 'clientHeight', {
        get: () => clientHeight,
    });
    const link = screen.getByRole('link', { name: 'The digest' });
    Object.defineProperties(link, {
        offsetTop: { value: 300 },
        offsetHeight: { value: 32 },
    });
    const parent = nav.parentElement!;
    vi.spyOn(parent, 'getBoundingClientRect').mockReturnValue({
        bottom: 500,
    } as DOMRect);
    window.dispatchEvent(new Event('scroll'));
    await nextTick();
    expect(nav.style.maxHeight).toMatch(/^min\(.*100svh.*, 404px\)$/);
    expect(nav.scrollTop).toBe(0);
    vi.spyOn(parent, 'getBoundingClientRect').mockReturnValue({
        bottom: 350,
    } as DOMRect);
    clientHeight = 254;
    window.dispatchEvent(new Event('resize'));
    await nextTick();
    expect(nav.style.maxHeight).toMatch(/^min\(.*100svh.*, 254px\)$/);
    expect(nav.scrollTop).toBe(78);
    view.unmount();
});
