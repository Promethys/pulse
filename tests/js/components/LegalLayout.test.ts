import { render, screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import LegalLayout from '@/components/pulse/LegalLayout.vue';
import { page } from '../support/inertia';

describe('LegalLayout', () => {
    const props = {
        title: 'Privacy',
        lastUpdated: 'November 1, 2026',
        sections: [
            { id: 'storage', label: 'Stored data' },
            { id: 'contact', label: 'Contact' },
        ],
    };

    it('renders the date, optional intro and section content with matching navigation', () => {
        page.props = { auth: { user: null }, canRegister: true };
        const { container } = render(LegalLayout, {
            props: { ...props, intro: 'How we handle your data.' },
            slots: {
                storage: '<p>Your saved preferences.</p>',
                contact: '<p>Ask us a question.</p>',
            },
        });
        expect(
            screen.getByRole('heading', { level: 1, name: 'Privacy' }),
        ).toBeVisible();
        expect(
            screen.getByText('Last updated: November 1, 2026'),
        ).toBeVisible();
        expect(screen.getByText('How we handle your data.')).toBeVisible();
        expect(container.querySelector('.prose-pulse')).toHaveTextContent(
            'Your saved preferences.',
        );
        expect(container.querySelector('.prose-pulse')).toHaveTextContent(
            'Ask us a question.',
        );
        for (const name of ['Table of contents', 'Sections']) {
            const nav = screen.getByRole('navigation', { name });
            expect(within(nav).getAllByRole('link')).toHaveLength(
                props.sections.length,
            );
            for (const section of props.sections) {
                expect(
                    within(nav).getByRole('link', { name: section.label }),
                ).toHaveAttribute('href', `#${section.id}`);
                const target = container.querySelector(`#${section.id}`)!;
                expect(
                    within(target as HTMLElement).getByRole('heading', {
                        level: 2,
                        name: section.label,
                    }),
                ).toBeVisible();
            }
        }
    });

    it('renders without an intro or sections', () => {
        page.props = { auth: { user: null }, canRegister: false };
        const { container } = render(LegalLayout, {
            props: { ...props, sections: [] },
        });
        expect(
            screen.getByRole('heading', { level: 1, name: 'Privacy' }),
        ).toBeVisible();
        expect(
            screen.queryByText('How we handle your data.'),
        ).not.toBeInTheDocument();
        expect(container.querySelector('.prose-pulse')?.children).toHaveLength(
            0,
        );
        expect(
            screen.queryByRole('navigation', { name: 'Table of contents' }),
        ).not.toBeInTheDocument();
    });
});
