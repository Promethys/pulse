import { render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import Welcome from '@/pages/Welcome.vue';
import { features, login, register } from '@/routes';
import { page } from '../support/inertia';

function renderWelcome(canRegister = true, signedIn = false) {
    page.props = { auth: { user: signedIn ? { id: 1 } : null }, canRegister };
    return render(Welcome, {
        global: { stubs: { Head: true } },
    });
}

describe('Welcome', () => {
    it('offers account creation and login through the generated routes', () => {
        renderWelcome();

        screen
            .getAllByRole('link', { name: 'Create an account' })
            .forEach((link) =>
                expect(link).toHaveAttribute('href', register.url()),
            );
        const links = screen.getAllByRole('link', { name: 'Log in' });
        expect(links).toHaveLength(1);
        links.forEach((link) =>
            expect(link).toHaveAttribute('href', login.url()),
        );
    });

    it('hides account creation when registration is disabled', () => {
        renderWelcome(false);

        expect(
            screen.queryByRole('link', { name: 'Create an account' }),
        ).not.toBeInTheDocument();
        expect(screen.getAllByRole('link', { name: 'Log in' })).toHaveLength(1);
    });

    it.each([true, false])(
        'offers one Open Pulse action to signed-in readers with registration=%s',
        (canRegister) => {
            renderWelcome(canRegister, true);

            expect(
                screen.getAllByRole('link', { name: 'Open Pulse' }),
            ).toHaveLength(1);
            expect(
                screen.queryByRole('link', { name: 'Log in' }),
            ).not.toBeInTheDocument();
            expect(
                screen.queryByRole('link', { name: 'Create an account' }),
            ).not.toBeInTheDocument();
        },
    );

    it('shows a sample morning, setup steps, features, costs and questions', () => {
        const { container } = renderWelcome();

        expect(
            screen.getByRole('heading', {
                level: 1,
                name: 'One calm daily digest of the news you actually follow.',
            }),
        ).toBeVisible();
        expect(
            screen.getByRole('region', { name: 'Sample morning in Pulse' }),
        ).toBeVisible();
        expect(screen.getByText('Sample digest')).toBeVisible();
        [
            'Pick your interests',
            'Add your AI key',
            'Choose where to receive it',
        ].forEach((name, index) => {
            expect(screen.getByRole('heading', { name })).toBeVisible();
            expect(screen.getByText(`Step ${index + 1}`)).toBeVisible();
        });
        expect(
            screen.getByRole('link', { name: 'Explore features' }),
        ).toHaveAttribute('href', features.url());
        expect(
            screen.getByRole('link', { name: /A digest written for you/ }),
        ).toHaveAttribute('href', `${features.url()}#digest`);
        expect(
            screen.getByRole('heading', { name: 'Free. You bring your key.' }),
        ).toBeVisible();
        expect(screen.getByText('Which AI providers can I use?')).toBeVisible();
        expect(container).not.toHaveTextContent(
            /usually enough|data export|52 interests/,
        );
        expect(
            screen.queryByRole('button', { name: 'Pick a date' }),
        ).not.toBeInTheDocument();
    });

    it('includes the brand and a working public theme toggle in the header', async () => {
        localStorage.setItem('appearance', 'light');
        const user = userEvent.setup();
        renderWelcome();
        const header = screen.getByRole('banner');

        expect(within(header).getByText('Pulse')).toBeVisible();
        await user.click(
            within(header).getByRole('button', { name: 'Switch to dark mode' }),
        );

        expect(document.documentElement).toHaveClass('dark');
        expect(localStorage.getItem('appearance')).toBe('dark');
        expect(
            within(header).getByRole('button', {
                name: 'Switch to light mode',
            }),
        ).toBeVisible();
    });
});
