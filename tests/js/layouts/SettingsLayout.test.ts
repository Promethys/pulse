import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { h, nextTick } from 'vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { page } from '../support/inertia';

describe('SettingsLayout', () => {
    it.each([
        ['/settings/profile', 'Profile'],
        ['/settings/security?tab=password#form', 'Security'],
        ['/settings/appearance', 'Appearance'],
    ])('marks only the current desktop section on %s', (path, label) => {
        page.url = path;
        render(SettingsLayout);

        expect(
            screen.getByRole('heading', { level: 1, name: 'Settings' }),
        ).toBeInTheDocument();
        const links = [
            ['Profile', '/settings/profile'],
            ['Security', '/settings/security'],
            ['Appearance', '/settings/appearance'],
        ];
        links.forEach(([name, href]) => {
            const link = screen.getByRole('link', {
                name: (accessibleName) => accessibleName === name,
            });
            expect(link).toHaveAttribute('href', href);
            if (name === label) {
                expect(link).toHaveAttribute('aria-current', 'page');
            } else {
                expect(link).not.toHaveAttribute('aria-current');
            }
        });
    });

    it.each([
        ['/settings/profile', 'Profile'],
        ['/settings/security', 'Security'],
        ['/settings/appearance', 'Appearance'],
    ])(
        'offers the mobile back link and section heading on %s',
        (path, title) => {
            page.url = path;
            render(SettingsLayout, {
                slots: {
                    default: () =>
                        h('input', { 'aria-label': 'Section content' }),
                },
            });

            expect(
                screen.getByRole('link', { name: /^Settings$/ }),
            ).toHaveAttribute('href', '/settings');
            expect(
                screen.getByRole('heading', { level: 1, name: title }),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('textbox', { name: 'Section content' }),
            ).toBeInTheDocument();
        },
    );

    it('updates the active tab and mobile title after Inertia navigation', async () => {
        page.url = '/settings/profile';
        render(SettingsLayout);
        page.url = '/settings/appearance?preview=dark';
        await nextTick();

        expect(
            screen.getByRole('link', { name: /^Appearance$/ }),
        ).toHaveAttribute('aria-current', 'page');
        expect(
            screen.getByRole('link', { name: /^Profile$/ }),
        ).not.toHaveAttribute('aria-current');
        expect(
            screen.getByRole('heading', { level: 1, name: 'Appearance' }),
        ).toBeInTheDocument();
    });
});
