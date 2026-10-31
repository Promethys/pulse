import { router } from '@inertiajs/vue3';
import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import SettingsIndex from '@/pages/settings/Index.vue';
import { settingsSections } from '@/lib/settingsSections';
import { logout } from '@/routes';
import { page } from '../../support/inertia';

beforeEach(() => {
    page.props = { auth: { user: {}, canAccessAdmin: false } };
});

describe('settings/Index', () => {
    it('links admins to the panel with a full page load', () => {
        page.props = { auth: { user: {}, canAccessAdmin: true } };
        render(SettingsIndex);

        const link = screen.getByRole('link', { name: 'Admin panel' });
        expect(link).toHaveAttribute('href', '/admin');
        expect(link).not.toHaveAttribute('data-inertia');
    });

    it('hides the panel link from other users', () => {
        render(SettingsIndex);

        expect(
            screen.queryByRole('link', { name: 'Admin panel' }),
        ).not.toBeInTheDocument();
    });

    it('offers exactly one link per section and a Settings page heading', () => {
        render(SettingsIndex);

        expect(
            screen.getByRole('heading', { level: 1, name: 'Settings' }),
        ).toBeVisible();
        expect(screen.getAllByRole('link')).toHaveLength(
            settingsSections.length + 1,
        );
        settingsSections.forEach(({ label, href }) => {
            expect(
                screen.getByRole('link', {
                    name: (accessibleName) => accessibleName === label,
                }),
            ).toHaveAttribute('href', href);
        });
        expect(
            screen.queryByRole('link', { name: 'Log out' }),
        ).not.toBeInTheDocument();
    });

    it('posts to the existing logout action when the logout button is clicked', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        render(SettingsIndex);

        expect(visit).not.toHaveBeenCalled();
        await userEvent
            .setup()
            .click(screen.getByRole('button', { name: 'Log out' }));

        expect(visit).toHaveBeenCalledOnce();
        const [url, options] = visit.mock.calls[0];
        expect(
            typeof url === 'string'
                ? url
                : url instanceof URL
                  ? url.pathname
                  : url.url,
        ).toBe(logout.url());
        expect(options).toMatchObject({ method: 'post' });
    });
});

describe('settings/Index Sources destination', () => {
    it('offers Sources so mobile users can manage their sources', () => {
        render(SettingsIndex);
        expect(screen.getByRole('link', { name: /^Sources$/ })).toHaveAttribute(
            'href',
            '/sources',
        );
    });
});
