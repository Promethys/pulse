import { render, screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import ArchiveRow from '@/components/pulse/ArchiveRow.vue';
import { tagProps } from '../../support/dayProps';

function archiveDay() {
    return {
        date: '2026-10-28',
        item_count: 2,
        tags: [tagProps()],
        news_status: 'generated' as 'generated' | 'pending' | 'failed' | null,
        learning_status: null as 'generated' | 'pending' | 'failed' | null,
    };
}

function renderRow(day = archiveDay(), today = '2026-10-30') {
    return render(ArchiveRow, { props: { day, today } });
}

describe('ArchiveRow', () => {
    it.each([
        [2, 'generated', '2 items'],
        [1, 'generated', '1 item'],
        [0, 'generated', 'Quiet night'],
        [0, 'pending', 'In progress'],
        [0, 'failed', 'News failed'],
        [0, null, 'No news'],
        [2, 'pending', '2 items'],
        [1, 'failed', '1 item'],
        [1, null, '1 item'],
    ] as const)(
        'labels %i items with news status %s as %s',
        (count, status, label) => {
            const day = archiveDay();
            day.item_count = count;
            day.news_status = status;
            renderRow(day);
            const link = screen.getByRole('link', { name: /Wednesday/ });
            expect(link).toHaveTextContent(
                new RegExp(`Wednesday\\s*·\\s*${label}`),
            );
            for (const alternative of [
                '2 items',
                '1 item',
                'Quiet night',
                'In progress',
                'News failed',
                'No news',
            ]) {
                if (alternative !== label)
                    expect(link).not.toHaveTextContent(alternative);
            }
        },
    );

    it.each([
        ['2026-10-30', '/'],
        ['2026-10-28', '/days/2026-10-28'],
    ])('links %s to %s', (date, href) => {
        const day = archiveDay();
        day.date = date;
        renderRow(day);
        expect(screen.getByRole('link')).toHaveAttribute('href', href);
        expect(screen.getAllByRole('link')).toHaveLength(1);
    });

    it('renders the short month and day in a primary date badge', () => {
        renderRow();
        const link = screen.getByRole('link', { name: /Wednesday/ });
        const month = within(link).getByText(/^oct$/i);
        expect(
            month.textContent === 'OCT' ||
                month.classList.contains('uppercase'),
        ).toBe(true);
        expect(month.closest('.bg-primary')).toContainElement(
            within(link).getByText('28'),
        );
        expect(within(link).getByText('28')).toHaveClass('font-display');
    });

    it.each([
        ['2026-10-28', 'Wednesday', false],
        ['2025-10-28', 'Tuesday', true],
    ] as const)(
        'shows the year for %s only when it differs from today',
        (date, weekday, showYear) => {
            const day = archiveDay();
            day.date = date;
            renderRow(day);
            const link = screen.getByRole('link', {
                name: new RegExp(weekday),
            });
            if (showYear) expect(link).toHaveTextContent('2025');
            else expect(link).not.toHaveTextContent('2026');
        },
    );

    it('keeps all tag names in the clipped single-line row and its title', () => {
        const day = archiveDay();
        day.tags = [
            tagProps(),
            { id: 2, slug: 'databases', name: 'Databases' },
            { id: 3, slug: 'security', name: 'Security' },
        ];
        renderRow(day);
        const first = screen.getByText('Programming');
        const tags = first.closest('[title]');
        expect(tags).toHaveClass(
            'flex',
            'flex-wrap',
            'max-h-7',
            'overflow-hidden',
        );
        for (const tag of day.tags) {
            expect(tags).toContainElement(screen.getByText(tag.name));
            expect(tags?.getAttribute('title')).toContain(tag.name);
        }
        expect(screen.getAllByRole('link')).toHaveLength(1);
    });

    it('omits the tags row for a learning-only day with no tags', () => {
        const day = archiveDay();
        day.tags = [];
        day.news_status = null;
        day.learning_status = 'generated';
        day.item_count = 0;
        const view = renderRow(day);
        expect(screen.getByRole('link')).toHaveTextContent('No news');
        expect(screen.queryByText('Programming')).not.toBeInTheDocument();
        expect(
            view.container.querySelector('.max-h-7'),
        ).not.toBeInTheDocument();
    });

    it.each([
        [3, null, null],
        [5, '+2', null],
        [8, '+5', '+2'],
    ])(
        'marks the hidden tags of a day with %i tags',
        (count, mobile, desktop) => {
            const day = archiveDay();
            day.tags = Array.from({ length: count }, (_, index) => ({
                ...tagProps(),
                id: index + 1,
                slug: `tag-${index}`,
                name: `Tag ${index}`,
            }));
            render(ArchiveRow, { props: { day, today: '2026-10-30' } });

            expect(screen.getByText('Tag 2')).not.toHaveClass('max-md:hidden');
            if (count > 3) {
                expect(screen.getByText('Tag 3')).toHaveClass('max-md:hidden');
            }
            if (mobile) {
                expect(screen.getByText(mobile)).toHaveClass('md:hidden');
            }
            if (desktop) {
                expect(screen.getByText(desktop)).toHaveClass(
                    'hidden',
                    'md:inline-flex',
                );
                expect(screen.queryByText('Tag 7')).not.toBeInTheDocument();
            }
            expect(screen.queryAllByText(/^\+/)).toHaveLength(
                [mobile, desktop].filter(Boolean).length,
            );
        },
    );
});

describe('ArchiveRow tag chips', () => {
    it('keeps the tag a plain span inside the single row link', () => {
        renderRow();
        const chip = screen.getByText('Programming');
        expect(chip.tagName).toBe('SPAN');
        expect(screen.getAllByRole('link')).toHaveLength(1);
        expect(screen.getByRole('link')).toContainElement(chip);
        expect(
            screen.queryByRole('link', { name: 'Programming' }),
        ).not.toBeInTheDocument();
    });
});
