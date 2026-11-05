import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import Register from '@/pages/auth/Register.vue';
import { page } from '../../support/inertia';

describe('Register', () => {
    it('offers the enabled social sign-in providers', () => {
        page.props.socialProviders = ['google', 'github'];
        render(Register, {
            props: {
                passwordRules: 'minlength: 8;',
                passwordHint: 'Use at least 8 characters.',
            },
        });

        expect(
            screen.getByRole('link', { name: 'Continue with Google' }),
        ).toHaveAttribute('href', '/auth/google/redirect');
        expect(
            screen.getByRole('link', { name: 'Continue with GitHub' }),
        ).toHaveAttribute('href', '/auth/github/redirect');
    });

    it.each([
        'Use at least 8 characters.',
        'Use at least 12 characters, including uppercase and lowercase letters, a number and a symbol. Choose a password that has not appeared in a data breach.',
    ])('renders the server password hint: %s', (passwordHint) => {
        render(Register, {
            props: { passwordRules: 'minlength: 8;', passwordHint },
        });

        expect(screen.getByText(passwordHint)).toBeVisible();
        expect(screen.getByLabelText('Password')).toHaveAttribute(
            'passwordrules',
            'minlength: 8;',
        );
    });
});
