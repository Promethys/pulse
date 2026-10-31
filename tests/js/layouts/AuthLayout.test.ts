import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { h } from 'vue';
import AuthLayout from '@/layouts/AuthLayout.vue';

describe('AuthLayout', () => {
    it('links the Pulse brand home', () => {
        render(AuthLayout, { props: { title: 'Welcome back' } });

        expect(screen.getByRole('link', { name: 'Pulse' })).toHaveAttribute(
            'href',
            '/',
        );
    });

    it('offers a working theme toggle before authentication', async () => {
        localStorage.setItem('appearance', 'light');
        render(AuthLayout);
        const user = userEvent.setup();

        await user.click(
            screen.getByRole('button', { name: 'Switch to dark mode' }),
        );

        expect(document.documentElement).toHaveClass('dark');
        expect(localStorage.getItem('appearance')).toBe('dark');
        await user.click(
            screen.getByRole('button', { name: 'Switch to light mode' }),
        );
        expect(document.documentElement).not.toHaveClass('dark');
        expect(localStorage.getItem('appearance')).toBe('light');
    });

    it('styles the primary title and preserves description and form content', async () => {
        render(AuthLayout, {
            props: {
                title: 'Welcome back',
                description: 'Sign in to your Pulse account.',
            },
            slots: {
                default: () =>
                    h('label', [
                        'Email address',
                        h('input', { type: 'email' }),
                    ]),
            },
        });

        expect(
            screen.getByRole('heading', { level: 1, name: 'Welcome back' }),
        ).toHaveClass('text-2xl', 'font-semibold');
        expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
        expect(
            screen.getByText('Sign in to your Pulse account.'),
        ).toBeVisible();
        const email = screen.getByRole('textbox', { name: 'Email address' });
        await userEvent.setup().type(email, 'taylor@example.com');
        expect(email).toHaveValue('taylor@example.com');
    });

    it('preserves an optional footer link beneath the form', () => {
        render(AuthLayout, {
            slots: {
                default: () => h('button', { type: 'submit' }, 'Log in'),
                footer: () => h('a', { href: '/register' }, 'Create one'),
            },
        });

        const footer = screen.getByRole('link', { name: 'Create one' });
        expect(footer).toHaveAttribute('href', '/register');
        expect(
            screen
                .getByRole('button', { name: 'Log in' })
                .compareDocumentPosition(footer) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
    });
});
