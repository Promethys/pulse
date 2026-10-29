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
        ).toEqual(['Today', 'Settings']);
        expect(screen.getByRole('link', { name: 'Today' })).toHaveAttribute(
            'href',
            '/',
        );
        expect(screen.getByRole('link', { name: 'Settings' })).toHaveAttribute(
            'href',
            '/settings',
        );
        expect(
            screen.queryByRole('link', {
                name: /Archive|Search|Learning|Sources|Catalog review/,
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

    it.each(['/', '/days/2026-10-01'])('marks Today current on %s', (path) => {
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

        expect(screen.getAllByRole('link')).toHaveLength(2);
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
