import { render, screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import Terms from '@/pages/legal/Terms.vue';
import { page } from '../../support/inertia';

describe('Terms', () => {
    it('renders the terms with linked sections, its date and shared contact', () => {
        page.props = {
            auth: { user: null },
            canRegister: true,
            supportEmail: 'hello@example.test',
        };
        const { container } = render(Terms);
        expect(
            screen.getByRole('heading', { level: 1, name: 'Terms of use' }),
        ).toBeVisible();
        expect(
            screen.getByText('Last updated: November 1, 2026'),
        ).toBeVisible();
        const headings = screen.getAllByRole('heading', { level: 2 });
        expect(headings).toHaveLength(10);
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

    it('explains service limits, provider charges and the code license', () => {
        page.props = { auth: { user: null }, canRegister: false };
        const { container } = render(Terms);
        for (const text of [
            'free service',
            'do not guarantee availability',
            'your API keys',
            'Those charges are yours',
            'Acceptable use',
            'AI summaries can be wrong',
            'third-party',
            'suspend accounts',
            'delete your account',
            'MIT license',
        ]) {
            expect(container).toHaveTextContent(text);
        }
    });
});
