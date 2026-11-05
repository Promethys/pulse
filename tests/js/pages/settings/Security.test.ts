import { router } from '@inertiajs/vue3';
import userEvent from '@testing-library/user-event';
import { render, screen } from '@testing-library/vue';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import Security from '@/pages/settings/Security.vue';
import { page } from '../../support/inertia';

describe('Security settings', () => {
    beforeEach(() => {
        page.props.auth = { user: { has_password: true } };
        page.props.socialProviders = [];
    });

    it('lets passwordless readers set a password without asking for a current one', () => {
        page.props.auth = { user: { has_password: false } };
        render(Security, {
            props: {
                passwordRules: 'minlength: 8;',
                passwordHint: 'Use at least 8 characters.',
                connectedProviders: [],
            },
        });

        expect(
            screen.queryByLabelText('Current password'),
        ).not.toBeInTheDocument();
        expect(screen.getByLabelText('New password')).toBeVisible();
        expect(screen.getByLabelText('Confirm password')).toBeVisible();
    });

    it.each(['google', 'github'])(
        'offers a full page link to connect enabled %s',
        (provider) => {
            page.props.socialProviders = [provider];
            render(Security, {
                props: {
                    passwordRules: 'minlength: 8;',
                    passwordHint: 'Use at least 8 characters.',
                    connectedProviders: [],
                },
            });

            expect(screen.getByText('Connected accounts')).toBeVisible();
            expect(
                screen.getByText(provider === 'google' ? 'Google' : 'GitHub'),
            ).toBeVisible();
            expect(
                screen.queryByText(provider === 'google' ? 'GitHub' : 'Google'),
            ).not.toBeInTheDocument();
            expect(
                screen.getByRole('link', { name: /^connect$/i }),
            ).toHaveAttribute('href', `/auth/${provider}/redirect?intent=link`);
        },
    );

    it('disconnects an enabled linked provider while hiding disabled providers', async () => {
        const user = userEvent.setup();
        const visit = vi.spyOn(router, 'delete').mockImplementation(() => {});
        page.props.socialProviders = ['google'];
        render(Security, {
            props: {
                passwordRules: 'minlength: 8;',
                passwordHint: 'Use at least 8 characters.',
                connectedProviders: [
                    {
                        id: 1,
                        provider: 'google',
                        created_at: '2026-11-05T12:00:00Z',
                    },
                    {
                        id: 2,
                        provider: 'github',
                        created_at: '2026-11-05T12:00:00Z',
                    },
                ],
            },
        });

        expect(screen.getByText('Google')).toBeVisible();
        expect(screen.queryByText('GitHub')).not.toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: /^connect$/i }),
        ).not.toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: /^disconnect$/i }));
        expect(visit).toHaveBeenCalledWith(
            '/auth/google/unlink',
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('prevents disconnecting the only sign-in method', () => {
        page.props.auth = { user: { has_password: false } };
        page.props.socialProviders = ['google'];
        render(Security, {
            props: {
                passwordRules: 'minlength: 8;',
                passwordHint: 'Use at least 8 characters.',
                connectedProviders: [
                    {
                        id: 1,
                        provider: 'google',
                        created_at: '2026-11-05T12:00:00Z',
                    },
                ],
            },
        });

        expect(screen.getByText('Google')).toBeVisible();
        const disconnect = screen.queryByRole('button', {
            name: /^disconnect$/i,
        });
        if (disconnect) expect(disconnect).toBeDisabled();
        expect(
            screen.queryByRole('link', { name: /^disconnect$/i }),
        ).not.toBeInTheDocument();
    });

    it('allows a passwordless reader to disconnect when another provider remains', () => {
        page.props.auth = { user: { has_password: false } };
        page.props.socialProviders = ['google', 'github'];
        const view = render(Security, {
            props: {
                passwordRules: 'minlength: 8;',
                passwordHint: 'Use at least 8 characters.',
                connectedProviders: [
                    {
                        id: 1,
                        provider: 'google',
                        created_at: '2026-11-05T12:00:00Z',
                    },
                    {
                        id: 2,
                        provider: 'github',
                        created_at: '2026-11-05T12:00:00Z',
                    },
                ],
            },
        });

        const buttons = screen.getAllByRole('button', {
            name: /^disconnect$/i,
        });
        expect(buttons).toHaveLength(2);
        buttons.forEach((button) => expect(button).toBeEnabled());
        view.unmount();
        page.props.socialProviders = [];
        render(Security, {
            props: {
                passwordRules: 'minlength: 8;',
                passwordHint: 'Use at least 8 characters.',
                connectedProviders: [
                    {
                        id: 1,
                        provider: 'google',
                        created_at: '2026-11-05T12:00:00Z',
                    },
                ],
            },
        });
        expect(
            screen.queryByText('Connected accounts'),
        ).not.toBeInTheDocument();
        expect(screen.queryByText('Google')).not.toBeInTheDocument();
    });

    it.each([
        'Use at least 8 characters.',
        'Use at least 12 characters, including uppercase and lowercase letters, a number and a symbol. Choose a password that has not appeared in a data breach.',
    ])('renders the server password hint: %s', (passwordHint) => {
        render(Security, {
            props: { passwordRules: 'minlength: 8;', passwordHint },
        });

        expect(screen.getByLabelText('Current password')).toBeVisible();
        expect(screen.getByText(passwordHint)).toBeVisible();
        expect(screen.getByLabelText('New password')).toHaveAttribute(
            'passwordrules',
            'minlength: 8;',
        );
    });
});
