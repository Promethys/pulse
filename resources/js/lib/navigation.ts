import {
    Activity,
    Archive,
    GraduationCap,
    Search,
    Settings,
    Sun,
} from '@lucide/vue';
import type { Component } from 'vue';

export type NavigationItem = {
    title: string;
    href: string;
    icon: Component;
    available: boolean;
    desktopOnly?: boolean;
    mobileOnly?: boolean;
};

export const navItems: NavigationItem[] = [
    { title: 'Today', href: '/', icon: Sun, available: true },
    { title: 'Archive', href: '/archive', icon: Archive, available: true },
    { title: 'Search', href: '/search', icon: Search, available: false },
    {
        title: 'Learning',
        href: '/learning',
        icon: GraduationCap,
        available: false,
    },
    {
        title: 'Sources',
        href: '/sources',
        icon: Activity,
        available: false,
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

    if (href === '/') {
        return path === '/';
    }

    if (href === '/archive' && path.startsWith('/days/')) {
        return true;
    }

    return path === href || path.startsWith(`${href}/`);
}
