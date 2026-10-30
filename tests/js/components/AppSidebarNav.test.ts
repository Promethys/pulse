import { render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { computed, defineComponent, h, nextTick, ref } from 'vue';
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import * as appearanceModule from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import { navItems } from '@/lib/navigation';
import type { Appearance } from '@/types';
import { page } from '../support/inertia';
import { mockMatchMedia } from '../support/matchMedia';

function renderSidebar() {
    return render(
        defineComponent({
            setup: () => () =>
                h(SidebarProvider, null, { default: () => h(AppSidebar) }),
        }),
    );
}

describe('AppSidebar', () => {
    beforeEach(() => {
        page.props = {
            auth: {
                user: { id: 1, name: 'Taylor', email: 'taylor@example.com' },
            },
        };
    });

    it('links the brand home and renders only available destinations', () => {
        renderSidebar();

        expect(screen.getByRole('link', { name: 'Pulse' })).toHaveAttribute(
            'href',
            '/',
        );
        expect(screen.getByRole('link', { name: 'Today' })).toHaveAttribute(
            'href',
            '/',
        );
        expect(screen.getByRole('link', { name: 'Archive' })).toHaveAttribute(
            'href',
            '/archive',
        );
        expect(
            screen
                .getAllByRole('link')
                .filter((link) =>
                    ['Today', 'Archive', 'Search'].includes(
                        link.textContent?.trim() ?? '',
                    ),
                )
                .map((link) => link.textContent?.trim()),
        ).toEqual(['Today', 'Archive', 'Search']);
        expect(
            screen.queryByRole('link', {
                name: /Settings|Learning|Sources|Catalog review|Dashboard|Repository|Documentation/,
            }),
        ).not.toBeInTheDocument();
    });

    it('includes Sources on desktop once it becomes available', () => {
        const sources = navItems.find((item) => item.title === 'Sources')!;
        const available = sources.available;
        try {
            sources.available = true;
            renderSidebar();

            expect(
                screen.getByRole('link', { name: 'Sources' }),
            ).toHaveAttribute('href', '/sources');
            expect(
                screen.getByRole('link', { name: 'Archive' }),
            ).toHaveAttribute('href', '/archive');
        } finally {
            sources.available = available;
        }
    });

    it.each(['/'])(
        'highlights Today on %s and clears it after navigation to settings',
        async (path) => {
            page.url = path;
            renderSidebar();

            expect(screen.getByRole('link', { name: 'Today' })).toHaveClass(
                'bg-sidebar-primary',
                'text-sidebar-primary-foreground',
            );

            page.url = '/settings/security';
            await nextTick();

            expect(screen.getByRole('link', { name: 'Today' })).not.toHaveClass(
                'bg-sidebar-primary',
            );
        },
    );

    it.each([
        ['light', false, 'dark', 'light'],
        ['dark', false, 'light', 'dark'],
        ['system', false, 'dark', 'light'],
        ['system', true, 'light', 'dark'],
    ] as const)(
        'toggles %s with system dark=%s and switches its label',
        async (initial, systemDark, next, previous) => {
            mockMatchMedia(systemDark);
            const appearance = ref<Appearance>(initial);
            const updateAppearance = vi.fn((value: Appearance) => {
                appearance.value = value;
            });
            vi.spyOn(appearanceModule, 'useAppearance').mockReturnValue({
                appearance,
                resolvedAppearance: computed(() =>
                    appearance.value === 'system'
                        ? window.matchMedia('(prefers-color-scheme: dark)')
                              .matches
                            ? 'dark'
                            : 'light'
                        : appearance.value,
                ),
                updateAppearance,
            });
            const user = userEvent.setup();
            renderSidebar();
            const label = (mode: string) =>
                `${mode === 'dark' ? 'Dark' : 'Light'} mode`;
            const button = screen.getByRole('button', { name: label(next) });
            expect(button).toHaveAttribute('type', 'button');

            await user.click(button);

            expect(updateAppearance).toHaveBeenNthCalledWith(1, next);
            await user.click(
                screen.getByRole('button', { name: label(previous) }),
            );
            expect(updateAppearance).toHaveBeenNthCalledWith(2, previous);
            expect(
                screen.getByRole('button', { name: label(next) }),
            ).toBeVisible();
        },
    );

    it('keeps the kit user menu and logout in the Pulse sidebar', async () => {
        const user = userEvent.setup();
        renderSidebar();

        expect(screen.getByRole('link', { name: 'Pulse' })).toHaveAttribute(
            'href',
            '/',
        );
        await user.click(screen.getByRole('button', { name: /Taylor/ }));

        expect(
            await screen.findByRole('menuitem', { name: 'Log out' }),
        ).toBeVisible();
    });

    it('preserves layout slots and accepts ignored breadcrumbs without a breadcrumb header', () => {
        render(AppLayout, {
            props: {
                breadcrumbs: [{ title: 'Legacy breadcrumb', href: '/legacy' }],
            },
            slots: { default: () => h('h1', 'Digest content') },
        });

        const main = screen.getByRole('main');
        expect(
            within(main).getByRole('heading', { name: 'Digest content' }),
        ).toBeVisible();
        expect(main).toHaveClass(
            'mx-auto',
            'w-full',
            'max-w-3xl',
            'px-4',
            'pt-6',
            'pb-safe',
            'md:px-8',
            'md:pt-10',
            'md:pb-12',
        );
        expect(screen.queryByText('Legacy breadcrumb')).not.toBeInTheDocument();
    });
});

describe('AppSidebar archive destination', () => {
    it('highlights Archive after navigation and clears Today', async () => {
        page.props = {
            auth: {
                user: { id: 1, name: 'Taylor', email: 'taylor@example.com' },
            },
        };
        renderSidebar();
        page.url = '/archive?page=2';
        await nextTick();
        expect(screen.getByRole('link', { name: 'Archive' })).toHaveClass(
            'bg-sidebar-primary',
            'text-sidebar-primary-foreground',
        );
        expect(screen.getByRole('link', { name: 'Today' })).not.toHaveClass(
            'bg-sidebar-primary',
        );
    });
});

describe('AppSidebar search destination', () => {
    it('links Search and highlights it after navigation', async () => {
        page.props = {
            auth: {
                user: { id: 1, name: 'Taylor', email: 'taylor@example.com' },
            },
        };
        renderSidebar();
        page.url = '/search?q=compiler';
        await nextTick();
        expect(screen.getByRole('link', { name: 'Search' })).toHaveAttribute(
            'href',
            '/search',
        );
        expect(screen.getByRole('link', { name: 'Search' })).toHaveClass(
            'bg-sidebar-primary',
            'text-sidebar-primary-foreground',
        );
        expect(screen.getByRole('link', { name: 'Today' })).not.toHaveClass(
            'bg-sidebar-primary',
        );
    });
});
