import { describe, expect, it } from 'vite-plus/test';
import { nextTick } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { page } from '../support/inertia';

describe('useCurrentUrl', () => {
    it('exposes the current path without query string or hash', () => {
        page.url = '/settings/profile?tab=1#top';

        expect(useCurrentUrl().currentUrl.value).toBe('/settings/profile');
    });

    it('follows page changes', async () => {
        const { currentUrl } = useCurrentUrl();
        page.url = '/dashboard';
        await nextTick();

        expect(currentUrl.value).toBe('/dashboard');
    });

    it('matches the exact path only', () => {
        page.url = '/settings/profile';
        const { isCurrentUrl } = useCurrentUrl();

        expect(isCurrentUrl('/settings/profile')).toBe(true);
        expect(isCurrentUrl('/settings')).toBe(false);
        expect(isCurrentUrl('/settings/profile/edit')).toBe(false);
    });

    it('accepts route objects and absolute URLs', () => {
        page.url = '/settings/profile';
        const { isCurrentUrl } = useCurrentUrl();

        expect(isCurrentUrl({ url: '/settings/profile', method: 'get' })).toBe(
            true,
        );
        expect(isCurrentUrl('http://localhost/settings/profile?x=1')).toBe(
            true,
        );
        expect(isCurrentUrl('https://example.com/other')).toBe(false);
    });

    it('treats an unparsable absolute URL as not current', () => {
        page.url = '/';

        expect(useCurrentUrl().isCurrentUrl('http://')).toBe(false);
    });

    it('compares with an explicit current URL when given', () => {
        page.url = '/dashboard';
        const { isCurrentUrl } = useCurrentUrl();

        expect(isCurrentUrl('/archive', '/archive')).toBe(true);
        expect(isCurrentUrl('/dashboard', '/archive')).toBe(false);
    });

    it('matches parent paths with isCurrentOrParentUrl', () => {
        page.url = '/settings/profile';
        const { isCurrentOrParentUrl } = useCurrentUrl();

        expect(isCurrentOrParentUrl('/settings')).toBe(true);
        expect(isCurrentOrParentUrl('/settings/profile')).toBe(true);
        expect(isCurrentOrParentUrl('/dashboard')).toBe(false);
    });

    it('picks a value depending on the current URL', () => {
        page.url = '/dashboard';
        const { whenCurrentUrl } = useCurrentUrl();

        expect(whenCurrentUrl('/dashboard', 'active', 'idle')).toBe('active');
        expect(whenCurrentUrl('/archive', 'active', 'idle')).toBe('idle');
        expect(whenCurrentUrl('/archive', 'active')).toBeNull();
    });
});
