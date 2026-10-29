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
    mobileHref?: string;
};

export const navItems: NavigationItem[] = [
    { title: 'Today', href: '/', icon: Sun, available: true },
    { title: 'Archive', href: '/archive', icon: Archive, available: false },
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
        href: '/settings/profile',
        mobileHref: '/settings',
        icon: Settings,
        available: true,
    },
];

export function isActive(currentPath: string, href: string): boolean {
    const path = currentPath.split(/[?#]/)[0];

    if (href === '/') {
        return path === '/' || path.startsWith('/days/');
    }

    const prefix = href.startsWith('/settings/') ? '/settings' : href;

    return path === prefix || path.startsWith(`${prefix}/`);
}
