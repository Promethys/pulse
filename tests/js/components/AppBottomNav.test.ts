import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { nextTick } from 'vue';
import AppBottomNav from '@/components/AppBottomNav.vue';
import { navItems } from '@/lib/navigation';
import { page } from '../support/inertia';

describe('AppBottomNav', () => {
    it('offers labelled links only for available mobile destinations', () => {
        render(AppBottomNav);

        expect(
            screen.getAllByRole('link').map((link) => link.textContent?.trim()),
        ).toEqual(['Today', 'Archive', 'Search', 'Learning', 'Settings']);
        expect(screen.getByRole('link', { name: 'Today' })).toHaveAttribute(
            'href',
            '/',
        );
        expect(screen.getByRole('link', { name: 'Archive' })).toHaveAttribute(
            'href',
            '/archive',
        );
        expect(screen.getByRole('link', { name: 'Settings' })).toHaveAttribute(
            'href',
            '/settings',
        );
        expect(
            screen.queryByRole('link', {
                name: /Sources|Catalog review/,
            }),
        ).not.toBeInTheDocument();
    });

    it('keeps desktop-only sources out even when available', () => {
        const sources = navItems.find((item) => item.title === 'Sources')!;
        const available = sources.available;
        try {
            sources.available = true;
            render(AppBottomNav);

            expect(screen.getByRole('link', { name: 'Today' })).toBeVisible();
            expect(
                screen.queryByRole('link', { name: 'Sources' }),
            ).not.toBeInTheDocument();
        } finally {
            sources.available = available;
        }
    });

    it.each(['/'])('marks Today current on %s', (path) => {
        page.url = path;
        render(AppBottomNav);

        expect(screen.getByRole('link', { name: 'Today' })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(
            screen.getByRole('link', { name: 'Settings' }),
        ).not.toHaveAttribute('aria-current', 'page');
        const today = screen.getByRole('link', { name: 'Today' });
        expect(
            [today, ...today.querySelectorAll('*')].some(
                (element) =>
                    element.classList.contains('bg-primary') &&
                    element.classList.contains('text-primary-foreground'),
            ),
        ).toBe(true);
    });

    it('updates the current tab after Inertia navigation to nested settings', async () => {
        render(AppBottomNav);
        page.url = '/settings/security?tab=password#form';
        await nextTick();

        expect(screen.getByRole('link', { name: 'Settings' })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(screen.getByRole('link', { name: 'Today' })).not.toHaveAttribute(
            'aria-current',
            'page',
        );
    });

    it('does not mark a tab current for an unrelated path', () => {
        page.url = '/archived';
        render(AppBottomNav);

        expect(screen.getAllByRole('link')).toHaveLength(5);
        screen.getAllByRole('link').forEach((link) => {
            expect(link).not.toHaveAttribute('aria-current', 'page');
        });
    });
});

describe('AppBottomNav mobile settings destination', () => {
    it.each([
        '/settings',
        '/settings/profile',
        '/settings/security?tab=password#form',
    ])(
        'links to the settings index and keeps Settings current on %s',
        (path) => {
            page.url = path;
            render(AppBottomNav);

            expect(
                screen.getByRole('link', { name: 'Settings' }),
            ).toHaveAttribute('href', '/settings');
            expect(
                screen.getByRole('link', { name: 'Settings' }),
            ).toHaveAttribute('aria-current', 'page');
            expect(screen.getByRole('link', { name: 'Today' })).toHaveAttribute(
                'href',
                '/',
            );
            expect(
                screen.getByRole('link', { name: 'Today' }),
            ).not.toHaveAttribute('aria-current', 'page');
        },
    );
});

describe('AppBottomNav archive destination', () => {
    it('marks Archive current after navigation and clears Today', async () => {
        render(AppBottomNav);
        page.url = '/archive?page=2';
        await nextTick();
        expect(screen.getByRole('link', { name: 'Archive' })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(screen.getByRole('link', { name: 'Today' })).not.toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(
            screen.getByRole('link', { name: 'Settings' }),
        ).not.toHaveAttribute('aria-current', 'page');
    });

    it('marks Archive current on a past day', () => {
        page.url = '/days/2026-10-01';
        render(AppBottomNav);

        expect(screen.getByRole('link', { name: 'Archive' })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(screen.getByRole('link', { name: 'Today' })).not.toHaveAttribute(
            'aria-current',
            'page',
        );
    });
});

describe('AppBottomNav search destination', () => {
    it('links Search and marks it current after navigation', async () => {
        render(AppBottomNav);
        page.url = '/search?q=compiler&page=2';
        await nextTick();
        expect(screen.getByRole('link', { name: 'Search' })).toHaveAttribute(
            'href',
            '/search',
        );
        expect(screen.getByRole('link', { name: 'Search' })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(screen.getByRole('link', { name: 'Today' })).not.toHaveAttribute(
            'aria-current',
            'page',
        );
    });
});

describe('AppBottomNav learning destination', () => {
    it.each(['/learning?tag=php&page=2', '/learning/21'])(
        'links Learning and marks it current on %s',
        async (path) => {
            render(AppBottomNav);
            page.url = path;
            await nextTick();
            expect(
                screen.getByRole('link', { name: 'Learning' }),
            ).toHaveAttribute('href', '/learning');
            expect(
                screen.getByRole('link', { name: 'Learning' }),
            ).toHaveAttribute('aria-current', 'page');
            expect(
                screen.getByRole('link', { name: 'Today' }),
            ).not.toHaveAttribute('aria-current', 'page');
            expect(
                screen.getByRole('link', { name: 'Search' }),
            ).not.toHaveAttribute('aria-current', 'page');
        },
    );
});
