import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import Login from '@/pages/auth/Login.vue';
import { register } from '@/routes';

function renderLogin(canRegister: boolean) {
    render(Login, {
        props: { canRegister, canResetPassword: true },
        global: { stubs: { Head: true, PasskeyVerify: true } },
    });
}

describe('Login', () => {
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
