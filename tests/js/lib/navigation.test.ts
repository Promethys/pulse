import {
    Archive,
    GraduationCap,
    Rss,
    Search,
    Settings,
    Sun,
} from '@lucide/vue';
import { describe, expect, it } from 'vite-plus/test';
import { isActive, navItems } from '@/lib/navigation';

describe('navigation', () => {
    it.each(['/dashboard', '/dashboard?date=today#news', '/', '/archive'])(
        'marks the Today destination current only on the dashboard for %s',
        (path) => {
            const today = navItems.find((item) => item.title === 'Today')!;
            expect(isActive(path, today.href)).toBe(
                path.startsWith('/dashboard'),
            );
        },
    );

    it('defines the ordered navigation, icons and availability', () => {
        expect(navItems).toHaveLength(6);
        expect(navItems).toMatchObject([
            { title: 'Today', href: '/dashboard', icon: Sun, available: true },
            {
                title: 'Archive',
                href: '/archive',
                icon: Archive,
                available: true,
            },
            {
                title: 'Search',
                href: '/search',
                icon: Search,
                available: true,
            },
            {
                title: 'Learning',
                href: '/learning',
                icon: GraduationCap,
                available: true,
            },
            {
                title: 'Sources',
                href: '/sources',
                icon: Rss,
                available: true,
                desktopOnly: true,
            },
            {
                title: 'Settings',
                href: '/settings',
                icon: Settings,
                available: true,
                mobileOnly: true,
            },
        ]);
        expect(
            navItems.filter((item) => item.available).map((item) => item.title),
        ).toEqual([
            'Today',
            'Archive',
            'Search',
            'Learning',
            'Sources',
            'Settings',
        ]);
        expect(
            navItems
                .filter((item) => item.desktopOnly)
                .map((item) => item.title),
        ).toEqual(['Sources']);
    });

    it.each([
        ['/dashboard', '/dashboard', true],
        ['/dashboard?date=today#news', '/dashboard', true],
        ['/', '/dashboard', false],
        ['/dashboard-old', '/dashboard', false],
        ['/days/2026-10-01', '/dashboard', false],
        ['/days/2026-10-01', '/archive', true],
        ['/tags/laravel', '/search', true],
        ['/tags/laravel', '/archive', false],
        ['/days/2026-10-01/items', '/archive', true],
        ['/daydream', '/dashboard', false],
        ['/days-old/2026-10-01', '/dashboard', false],
        ['/archive', '/dashboard', false],
        ['/settings', '/settings', true],
        ['/settings/security', '/settings', true],
        ['/settings/profile/edit', '/settings', true],
        ['/settings-old/profile', '/settings', false],
        ['/archive', '/archive', true],
        ['/archive/2026/10', '/archive', true],
        ['/archived', '/archive', false],
        ['/search', '/search', true],
        ['/search/results', '/search', true],
        ['/searching', '/search', false],
        ['/learning/topic', '/learning', true],
        ['/learning-tools', '/learning', false],
        ['/sources/1', '/sources', true],
        ['/sources-old', '/sources', false],
    ])('matches %s against %s as %s', (path, href, active) => {
        expect(isActive(path, href)).toBe(active);
    });
});

describe('navigation mobile destinations', () => {
    it('shows settings only in the mobile bar, pointing to the settings index', () => {
        expect(
            navItems.find((item) => item.title === 'Settings'),
        ).toMatchObject({
            href: '/settings',
            mobileOnly: true,
        });
    });
});
