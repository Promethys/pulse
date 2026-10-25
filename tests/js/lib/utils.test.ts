import { describe, expect, it } from 'vite-plus/test';
import { cn, toUrl } from '@/lib/utils';

describe('cn', () => {
    it('joins classes and drops falsy ones', () => {
        expect(
            cn('a', false, null, undefined, 'b', { c: true, d: false }),
        ).toBe('a b c');
    });

    it('lets the last conflicting Tailwind class win', () => {
        expect(cn('px-2 py-1', 'px-4')).toBe('py-1 px-4');
        expect(cn('text-sm', ['text-lg'])).toBe('text-lg');
    });

    it('returns an empty string without classes', () => {
        expect(cn()).toBe('');
    });
});

describe('toUrl', () => {
    it('returns a string href as is', () => {
        expect(toUrl('/settings/profile')).toBe('/settings/profile');
    });

    it('returns the url of a route object', () => {
        expect(toUrl({ url: '/dashboard', method: 'get' })).toBe('/dashboard');
    });
});
