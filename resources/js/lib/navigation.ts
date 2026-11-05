import {
    Archive,
    GraduationCap,
    Rss,
    Search,
    Settings,
    Sun,
} from '@lucide/vue';
import type { Component } from 'vue';
import { dashboard } from '@/routes';

export type NavigationItem = {
    title: string;
    href: string;
    icon: Component;
    available: boolean;
    desktopOnly?: boolean;
    mobileOnly?: boolean;
};

export const navItems: NavigationItem[] = [
    { title: 'Today', href: dashboard.url(), icon: Sun, available: true },
    { title: 'Archive', href: '/archive', icon: Archive, available: true },
    { title: 'Search', href: '/search', icon: Search, available: true },
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
];

export function isActive(currentPath: string, href: string): boolean {
    const path = currentPath.split(/[?#]/)[0];

    if (href === dashboard.url()) {
        return path === dashboard.url();
    }

    if (href === '/archive' && path.startsWith('/days/')) {
        return true;
    }

    if (href === '/search' && path.startsWith('/tags/')) {
        return true;
    }

    return path === href || path.startsWith(`${href}/`);
}
