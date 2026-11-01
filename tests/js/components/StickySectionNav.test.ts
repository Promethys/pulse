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
        vi.stubGlobal('IntersectionObserver', undefined);
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

    it('tracks visible sections, ignores exits and disconnects on unmount', async () => {
        let callback: IntersectionObserverCallback = () => {};
        const observe = vi.fn();
        const disconnect = vi.fn();
        vi.stubGlobal(
            'IntersectionObserver',
            class {
                constructor(handler: IntersectionObserverCallback) {
                    callback = handler;
                }
                observe = observe;
                disconnect = disconnect;
            },
        );
        const { unmount } = render(
            defineComponent({
                setup: () => () =>
                    h('div', [
                        h(StickySectionNav, { sections }),
                        ...sections.map(({ id }) => h('section', { id })),
                    ]),
            }),
        );
        expect(observe).toHaveBeenCalledTimes(2);
        const target = document.getElementById('delivery')!;
        callback(
            [
                { target, isIntersecting: true },
            ] as unknown as IntersectionObserverEntry[],
            {} as IntersectionObserver,
        );
        await nextTick();
        expect(screen.getByRole('link', { name: 'Delivery' })).toHaveAttribute(
            'aria-current',
            'location',
        );
        callback(
            [
                { target, isIntersecting: false },
            ] as unknown as IntersectionObserverEntry[],
            {} as IntersectionObserver,
        );
        await nextTick();
        expect(screen.getByRole('link', { name: 'Delivery' })).toHaveAttribute(
            'aria-current',
            'location',
        );
        unmount();
        expect(disconnect).toHaveBeenCalledOnce();
    });

    it('keeps the lower section active while the one above re-enters the band', async () => {
        let callback: IntersectionObserverCallback = () => undefined;
        vi.stubGlobal(
            'IntersectionObserver',
            class {
                constructor(handler: IntersectionObserverCallback) {
                    callback = handler;
                }
                observe = vi.fn();
                disconnect = vi.fn();
            },
        );
        render(
            defineComponent({
                setup: () => () =>
                    h('div', [
                        h(StickySectionNav, { sections }),
                        ...sections.map(({ id }) => h('section', { id })),
                    ]),
            }),
        );
        const report = (id: string, isIntersecting: boolean) =>
            callback(
                [
                    { target: document.getElementById(id)!, isIntersecting },
                ] as unknown as IntersectionObserverEntry[],
                {} as IntersectionObserver,
            );
        const [upper, lower] = sections;
        report(lower.id, true);
        report(upper.id, true);
        await nextTick();
        expect(screen.getByRole('link', { name: lower.label })).toHaveAttribute(
            'aria-current',
            'location',
        );
        report(lower.id, false);
        await nextTick();
        expect(screen.getByRole('link', { name: upper.label })).toHaveAttribute(
            'aria-current',
            'location',
        );
    });

    it('replaces observed sections when its props change and skips missing elements', async () => {
        const observe = vi.fn();
        const disconnect = vi.fn();
        vi.stubGlobal(
            'IntersectionObserver',
            class {
                observe = observe;
                disconnect = disconnect;
            },
        );
        const target = document.createElement('section');
        target.id = 'digest';
        document.body.append(target);
        const { rerender, unmount } = render(StickySectionNav, {
            props: { sections },
        });
        expect(observe).toHaveBeenCalledTimes(1);
        expect(observe).toHaveBeenCalledWith(target);
        await rerender({
            sections: [{ id: 'missing', label: 'Missing section' }],
        });
        expect(disconnect).toHaveBeenCalledOnce();
        expect(
            screen.getByRole('link', { name: 'Missing section' }),
        ).toHaveAttribute('aria-current', 'location');
        expect(
            screen.queryByRole('link', { name: 'The digest' }),
        ).not.toBeInTheDocument();
        unmount();
        target.remove();
    });

    it('handles empty sections', () => {
        render(StickySectionNav, { props: { sections: [] } });
        expect(screen.queryByRole('link')).not.toBeInTheDocument();
    });
});
