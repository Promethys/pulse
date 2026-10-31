import { render, screen } from '@testing-library/vue';
import { beforeEach, describe, expect, it } from 'vite-plus/test';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { page } from '../support/inertia';

const passthrough = { template: '<div><slot /></div>' };

function renderMenu() {
    render(UserMenuContent, {
        props: {
            user: {
                id: 1,
                name: 'Ada',
                email: 'ada@example.com',
                avatar: '',
                email_verified_at: null,
                created_at: '',
                updated_at: '',
            },
        },
        global: {
            stubs: {
                DropdownMenuGroup: passthrough,
                DropdownMenuItem: passthrough,
                DropdownMenuLabel: passthrough,
                DropdownMenuSeparator: true,
            },
        },
    });
}

describe('UserMenuContent', () => {
    beforeEach(() => {
        page.props = { auth: { user: {}, canAccessAdmin: false } };
    });

    it('links admins to the panel with a full page load', () => {
        page.props = { auth: { user: {}, canAccessAdmin: true } };
        renderMenu();

        expect(
            screen.getByRole('link', { name: 'Admin panel' }),
        ).toHaveAttribute('href', '/admin');
    });

    it('hides the panel link from other users', () => {
        renderMenu();

        expect(
            screen.queryByRole('link', { name: 'Admin panel' }),
        ).not.toBeInTheDocument();
    });
});
