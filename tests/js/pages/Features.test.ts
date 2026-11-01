import { render, screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import Features from '@/pages/Features.vue';
import { register } from '@/routes';
import { page } from '../support/inertia';

describe('Features', () => {
    it.each([
        [true, false],
        [false, false],
        [true, true],
        [false, true],
    ])('respects registration=%s and signed in=%s', (canRegister, signedIn) => {
        page.props = {
            auth: { user: signedIn ? { id: 1 } : null },
            canRegister,
        };
        render(Features);

        const registration = screen.queryAllByRole('link', {
            name: 'Create an account',
        });
        expect(registration).toHaveLength(canRegister && !signedIn ? 2 : 0);
        registration.forEach((link) =>
            expect(link).toHaveAttribute('href', register.url()),
        );
        expect(
            screen.queryAllByRole('link', { name: 'Open Pulse' }),
        ).toHaveLength(signedIn ? 1 : 0);
        expect(screen.queryAllByRole('link', { name: 'Log in' })).toHaveLength(
            signedIn ? 0 : 1,
        );
    });

    it('links every section and shows accurate product details', () => {
        page.props = { auth: { user: null }, canRegister: true };
        const { container } = render(Features);
        const navigation = within(
            screen.getByRole('navigation', { name: 'Sections' }),
        );
        [
            'interests',
            'digest',
            'tips-topics',
            'reading',
            'delivery',
            'keys',
            'security',
        ].forEach((id) => {
            expect(
                container.querySelector(`section#${id}`),
            ).toBeInTheDocument();
            expect(
                navigation
                    .getAllByRole('link')
                    .some((link) => link.getAttribute('href') === `#${id}`),
            ).toBe(true);
        });
        expect(container).toHaveTextContent('50+ interests');
        expect(container).toHaveTextContent('09:00');
        expect(container).toHaveTextContent('09:30');
        [
            'Gemini',
            'Anthropic',
            'OpenAI',
            'Groq',
            'OpenRouter',
            'Z.AI',
            'GitHub trending',
            'webhook',
        ].forEach((text) => expect(container).toHaveTextContent(text));
        expect(container).not.toHaveTextContent(
            /52 interests|data export|usually enough/,
        );
        expect(
            container.querySelector('a[href^="/learning/"]'),
        ).not.toBeInTheDocument();
    });

    it('shows a product screenshot for each theme in every section', () => {
        page.props = { auth: { user: null }, canRegister: true };
        const { container } = render(Features);

        const sources = [...container.querySelectorAll('section img')].map(
            (image) => image.getAttribute('src'),
        );
        expect(sources).toHaveLength(14);
        expect(sources).toContain('/images/shots/sources-mobile.webp');
        expect(sources).toContain('/images/shots/sources-mobile-dark.webp');
        container
            .querySelectorAll('section img')
            .forEach((image) => expect(image.getAttribute('alt')).not.toBe(''));
    });
});
