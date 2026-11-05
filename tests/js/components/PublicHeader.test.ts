import {
    fireEvent,
    render,
    screen,
    waitFor,
    within,
} from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it } from 'vite-plus/test';
import PublicHeader from '@/components/pulse/PublicHeader.vue';
import {
    dashboard,
    features,
    home,
    login,
    openSource,
    register,
} from '@/routes';
import { index as docs } from '@/routes/docs';
import { page } from '../support/inertia';

describe('PublicHeader', () => {
    it('marks the current section in the navigation', () => {
        page.url = `${docs.url()}/delivery/email`;
        render(PublicHeader);

        const main = screen.getByRole('navigation', { name: 'Main' });
        expect(
            within(main).getByRole('link', { name: 'Docs' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(
            within(main).getByRole('link', { name: 'Features' }),
        ).not.toHaveAttribute('aria-current');
        page.url = '/';
    });

    it('keeps the current page when the URL has a hash or query', () => {
        page.url = `${features.url()}#keys`;
        render(PublicHeader);

        expect(
            within(screen.getByRole('navigation', { name: 'Main' })).getByRole(
                'link',
                { name: 'Features' },
            ),
        ).toHaveAttribute('aria-current', 'page');
        page.url = '/';
    });

    beforeEach(() => {
        page.props = { auth: { user: null }, canRegister: true };
    });

    it('links the brand, navigation and guest actions through generated routes', () => {
        render(PublicHeader);

        expect(screen.getByRole('link', { name: 'Pulse' })).toHaveAttribute(
            'href',
            home.url(),
        );
        const nav = within(screen.getByRole('navigation', { name: 'Main' }));
        for (const [name, href] of [
            ['Features', features.url()],
            ['Docs', docs.url()],
            ['Open source', openSource.url()],
        ]) {
            expect(nav.getByRole('link', { name })).toHaveAttribute(
                'href',
                href,
            );
        }
        expect(screen.getByRole('link', { name: 'Log in' })).toHaveAttribute(
            'href',
            login.url(),
        );
        expect(
            screen.getByRole('link', { name: 'Create an account' }),
        ).toHaveAttribute('href', register.url());
        expect(
            screen.queryByRole('link', { name: 'Open Pulse' }),
        ).not.toBeInTheDocument();
    });

    it.each([true, false])(
        'offers only Open Pulse to a signed-in reader with registration=%s',
        (canRegister) => {
            page.props = { auth: { user: { id: 1 } }, canRegister };
            render(PublicHeader);

            expect(screen.getByRole('link', { name: 'Pulse' })).toHaveAttribute(
                'href',
                home.url(),
            );
            expect(
                screen.getAllByRole('link', { name: 'Open Pulse' }),
            ).toHaveLength(1);
            expect(
                screen.getByRole('link', { name: 'Open Pulse' }),
            ).toHaveAttribute('href', dashboard.url());
            expect(
                screen.queryByRole('link', { name: 'Log in' }),
            ).not.toBeInTheDocument();
            expect(
                screen.queryByRole('link', { name: 'Create an account' }),
            ).not.toBeInTheDocument();
        },
    );

    it.each([
        ['guest', null, true],
        ['registration closed', null, false],
        ['signed in', { id: 1 }, true],
        ['signed in with registration closed', { id: 1 }, false],
    ])(
        'shows the correct actions in the mobile menu for %s',
        async (_state, user, canRegister) => {
            page.props = { auth: { user }, canRegister };
            const interaction = userEvent.setup();
            render(PublicHeader);

            await interaction.click(
                screen.getByRole('button', { name: 'Open menu' }),
            );

            const dialog = within(
                await screen.findByRole('dialog', { name: 'Menu' }),
            );
            const nav = within(
                dialog.getByRole('navigation', { name: 'Mobile' }),
            );
            expect(nav.getByRole('link', { name: 'Features' })).toHaveAttribute(
                'href',
                features.url(),
            );
            expect(nav.getByRole('link', { name: 'Docs' })).toHaveAttribute(
                'href',
                docs.url(),
            );
            expect(
                nav.getByRole('link', { name: 'Open source' }),
            ).toHaveAttribute('href', openSource.url());
            if (user) {
                expect(
                    nav.getByRole('link', { name: 'Open Pulse' }),
                ).toHaveAttribute('href', dashboard.url());
                expect(
                    nav.queryByRole('link', { name: 'Log in' }),
                ).not.toBeInTheDocument();
            } else {
                expect(
                    nav.getByRole('link', { name: 'Log in' }),
                ).toHaveAttribute('href', login.url());
                expect(
                    nav.queryByRole('link', { name: 'Open Pulse' }),
                ).not.toBeInTheDocument();
            }
            if (!user && canRegister) {
                expect(
                    nav.getByRole('link', { name: 'Create an account' }),
                ).toHaveAttribute('href', register.url());
            } else {
                expect(
                    screen.queryByRole('link', {
                        name: 'Create an account',
                        hidden: true,
                    }),
                ).not.toBeInTheDocument();
            }
        },
    );

    it('hides registration on desktop when it is closed', () => {
        page.props.canRegister = false;
        render(PublicHeader);

        expect(
            screen.queryByRole('link', { name: 'Create an account' }),
        ).not.toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Log in' })).toBeVisible();
    });

    it.each(['Features', 'Docs', 'Open source', 'Log in', 'Create an account'])(
        'closes the mobile menu after choosing %s',
        async (name) => {
            const user = userEvent.setup();
            render(PublicHeader);
            await user.click(screen.getByRole('button', { name: 'Open menu' }));
            const dialog = within(
                await screen.findByRole('dialog', { name: 'Menu' }),
            );

            await fireEvent.click(dialog.getByRole('link', { name }), {
                ctrlKey: true,
            });

            await waitFor(() =>
                expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
            );
        },
    );

    it('closes the mobile menu with Escape and restores focus to its trigger', async () => {
        const user = userEvent.setup();
        render(PublicHeader);
        await user.click(screen.getByRole('button', { name: 'Open menu' }));
        await screen.findByRole('dialog');

        await user.keyboard('{Escape}');

        await waitFor(() =>
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument(),
        );
        await waitFor(() =>
            expect(
                screen.getByRole('button', { name: 'Open menu' }),
            ).toHaveFocus(),
        );
    });
});
