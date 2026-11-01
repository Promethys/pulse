import { render, screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import Privacy from '@/pages/legal/Privacy.vue';
import { page } from '../../support/inertia';

describe('Privacy', () => {
    it('renders the policy with linked sections, its date and shared contact', () => {
        page.props = {
            auth: { user: null },
            canRegister: true,
            supportEmail: 'hello@example.test',
        };
        const { container } = render(Privacy);
        expect(
            screen.getByRole('heading', { level: 1, name: 'Privacy' }),
        ).toBeVisible();
        expect(
            screen.getByText('Last updated: November 1, 2026'),
        ).toBeVisible();
        const headings = screen.getAllByRole('heading', { level: 2 });
        expect(headings).toHaveLength(9);
        for (const navName of ['Table of contents', 'Sections']) {
            const nav = screen.getByRole('navigation', { name: navName });
            const links = within(nav).getAllByRole('link');
            expect(links).toHaveLength(headings.length);
            for (const link of links) {
                const section = container.querySelector(
                    link.getAttribute('href')!,
                )!;
                expect(
                    within(section as HTMLElement).getByRole('heading', {
                        level: 2,
                        name: link.textContent!,
                    }),
                ).toBeVisible();
            }
        }
        expect(
            screen.getByRole('link', { name: 'hello@example.test' }),
        ).toHaveAttribute('href', 'mailto:hello@example.test');
    });

    it('explains data handling and deletion limits without promising complete erasure', () => {
        page.props = { auth: { user: null }, canRegister: false };
        const { container } = render(Privacy);
        for (const text of [
            'Passwords are hashed',
            'AI keys and delivery settings are encrypted',
            'IP address and browser user agent',
            'CSRF token',
            'appearance',
            'sidebar_state',
            'recent tip titles',
            'newsletter excerpts',
            'older than 30 days',
            'Your sessions and password reset records are deleted too',
            'Posts already sent to Discord or email stay there',
            'no built-in data export',
        ]) {
            expect(container).toHaveTextContent(text);
        }
    });
});
