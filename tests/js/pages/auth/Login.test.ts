import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import Login from '@/pages/auth/Login.vue';
import { register } from '@/routes';
import { page } from '../../support/inertia';

function renderLogin(canRegister: boolean) {
    render(Login, {
        props: { canRegister, canResetPassword: true },
        global: { stubs: { Head: true, PasskeyVerify: true } },
    });
}

describe('Login', () => {
    it('offers the enabled social sign-in providers', () => {
        page.props.socialProviders = ['google', 'github'];
        renderLogin(true);

        expect(
            screen.getByRole('link', { name: 'Continue with Google' }),
        ).toHaveAttribute('href', '/auth/google/redirect');
        expect(
            screen.getByRole('link', { name: 'Continue with GitHub' }),
        ).toHaveAttribute('href', '/auth/github/redirect');
    });

    it('displays the callback error from the shared validation errors', () => {
        const message = "Sign-in with Google didn't work. Try again.";
        page.props.errors = { email: message };
        renderLogin(true);

        expect(screen.getByText(message)).toBeVisible();
    });

    it('links to registration when it is enabled', () => {
        renderLogin(true);

        expect(
            screen.getByRole('link', { name: 'Create one' }),
        ).toHaveAttribute('href', register.url());
    });

    it('hides the registration link when it is disabled', () => {
        renderLogin(false);

        expect(screen.queryByText('No account?')).not.toBeInTheDocument();
    });
});
