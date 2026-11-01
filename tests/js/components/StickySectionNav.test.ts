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
});
