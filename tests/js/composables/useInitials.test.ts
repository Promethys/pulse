import { describe, expect, it } from 'vite-plus/test';
import { getInitials, useInitials } from '@/composables/useInitials';

describe('getInitials', () => {
    it.each([
        [undefined, ''],
        ['', ''],
        ['   ', ''],
    ])('returns nothing for %j', (name, expected) => {
        expect(getInitials(name)).toBe(expected);
    });

    it('uses the first letter of a single name', () => {
        expect(getInitials('ada')).toBe('A');
    });

    it('uses the first and last names, ignoring middle names', () => {
        expect(getInitials('Ada Lovelace')).toBe('AL');
        expect(getInitials('Ada King Lovelace')).toBe('AL');
    });

    it('ignores extra whitespace', () => {
        expect(getInitials('  ada \t  lovelace \n')).toBe('AL');
    });

    it('keeps accented letters and emoji whole', () => {
        expect(getInitials('émile zola')).toBe('ÉZ');
        expect(getInitials('😀 Smile')).toBe('😀S');
    });

    it('is what useInitials exposes', () => {
        expect(useInitials().getInitials).toBe(getInitials);
    });
});
