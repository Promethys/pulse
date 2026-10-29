import { render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import Welcome from '@/pages/Welcome.vue';
import { login, register } from '@/routes';

function renderWelcome() {
    return render(Welcome, { global: { stubs: { Head: true } } });
}

describe('Welcome', () => {
    it('offers account creation and login through the generated routes', () => {
        renderWelcome();

        expect(
            screen.getByRole('link', { name: 'Create an account' }),
        ).toHaveAttribute('href', register.url());
        const links = screen.getAllByRole('link', { name: 'Log in' });
        expect(links).toHaveLength(2);
        links.forEach((link) =>
            expect(link).toHaveAttribute('href', login.url()),
        );
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
                'Pulse reads your sources overnight and sends you a short, grouped summary each morning. Free and open source.',
            ),
        ).toBeVisible();
        [
            [
                'Pick your interests',
                'AI, cooking, finance, football… choose what you follow. Tech interests also get a daily tip and topic.',
            ],
            [
                'Add your AI key',
                'Gemini, Anthropic, OpenAI, Groq or OpenRouter. Free tiers are usually enough.',
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
