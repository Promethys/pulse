import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import Security from '@/pages/settings/Security.vue';

describe('Security settings', () => {
    it.each([
        'Use at least 8 characters.',
        'Use at least 12 characters, including uppercase and lowercase letters, a number and a symbol. Choose a password that has not appeared in a data breach.',
    ])('renders the server password hint: %s', (passwordHint) => {
        render(Security, {
            props: { passwordRules: 'minlength: 8;', passwordHint },
        });

        expect(screen.getByText(passwordHint)).toBeVisible();
        expect(screen.getByLabelText('New password')).toHaveAttribute(
            'passwordrules',
            'minlength: 8;',
        );
    });
});
