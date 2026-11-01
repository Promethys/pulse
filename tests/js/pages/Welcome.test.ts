import { render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import Welcome from '@/pages/Welcome.vue';
import { login, register } from '@/routes';
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
        expect(links).toHaveLength(2);
        links.forEach((link) =>
            expect(link).toHaveAttribute('href', login.url()),
        );
    });

    it('hides account creation when registration is disabled', () => {
        renderWelcome(false);

        expect(
            screen.queryByRole('link', { name: 'Create an account' }),
        ).not.toBeInTheDocument();
        expect(screen.getAllByRole('link', { name: 'Log in' })).toHaveLength(2);
    });

    it('offers one Open Pulse action to signed-in readers', () => {
        renderWelcome(true, true);

        expect(
            screen.getAllByRole('link', { name: 'Open Pulse' }),
        ).toHaveLength(1);
        expect(
            screen.queryByRole('link', { name: 'Log in' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Create an account' }),
        ).not.toBeInTheDocument();
    });

    it('explains the digest and its three setup steps without preview or deferred features', () => {
        const { container } = renderWelcome();

        expect(
            screen.getByRole('heading', {
                level: 1,
                name: 'One calm daily digest of the news you actually follow.',
            }),
        ).toBeVisible();
        expect(
            screen.getByText(
                'Pulse reads your sources overnight and sends you a short, grouped summary each morning. Free and open source. You bring your own AI key.',
            ),
        ).toBeVisible();
        [
            [
                'Pick your interests',
                'AI, cooking, finance, football… choose what you follow. Tech interests also get a daily tip and topic.',
            ],
            [
                'Add your AI key',
                'Gemini, Anthropic, OpenAI, Groq, OpenRouter or Z.AI. Some providers have free tiers, and yours may charge beyond them.',
            ],
            [
                'Choose where to receive it',
                'In the app, in as many Discord servers as you like, or by email.',
            ],
        ].forEach(([title, body], index) => {
            expect(
                screen.getByRole('heading', { level: 2, name: title }),
            ).toBeVisible();
            expect(screen.getByText(body)).toBeVisible();
            expect(screen.getByText(`Step ${index + 1}`)).toBeVisible();
        });
        expect(container).not.toHaveTextContent(
            /\bask\b|quiz|rating|too basic|not useful|what a morning looks like/i,
        );
        expect(
            screen.queryByRole('button', {
                name: /ask|quiz|rate|useful|too basic/i,
            }),
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
