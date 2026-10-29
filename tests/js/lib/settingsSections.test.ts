import { describe, expect, it } from 'vite-plus/test';
import { settingsSections } from '@/lib/settingsSections';

describe('settingsSections', () => {
    it('defines the ordered settings sections and their destinations once', () => {
        expect(settingsSections).toEqual([
            { label: 'Profile', href: '/settings/profile' },
            { label: 'Security', href: '/settings/security' },
            { label: 'Appearance', href: '/settings/appearance' },
        ]);
    });
});
