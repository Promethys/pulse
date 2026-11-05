import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import Archive from '@/pages/Archive.vue';
import { tagProps } from '../support/dayProps';
import { page } from '../support/inertia';

function archiveProps() {
    return {
        days: [
            {
                date: '2026-10-30',
                item_count: 1,
                tags: [tagProps()],
                news_status: 'generated' as const,
                learning_status: null,
            },
            {
                date: '2026-10-28',
                item_count: 0,
                tags: [],
                news_status: 'generated' as const,
                learning_status: null,
            },
        ],
        pagination: {
            current_page: 1,
            last_page: 1,
            total: 2,
            prev_url: null as string | null,
            next_url: null as string | null,
        },
        today: '2026-10-30',
    };
}

function renderPage(props = archiveProps()) {
    page.url = '/archive';
    page.props = { ...props, errors: {} };
    return render(Archive, { props });
}

function expectUnavailablePagination(name: 'Previous' | 'Next') {
    const link = screen.queryByRole('link', { name });
    if (link) {
        expect(link).toHaveAttribute('aria-disabled', 'true');
    }
    const button = screen.queryByRole('button', { name });
    if (button) expect(button).toBeDisabled();
}

describe('Archive', () => {
    it('lists digest dates in server order with counts and today-aware links', () => {
        renderPage();
        expect(
            screen.getByRole('heading', { level: 1, name: 'Archive' }),
        ).toBeVisible();
        expect(screen.getByText('2 digests')).toBeVisible();
        const list = screen.getByRole('list');
        expect(list).toHaveClass(
            'divide-y',
            'rounded-2xl',
            'border',
            'bg-card',
        );
        expect(screen.getAllByRole('listitem')).toHaveLength(2);
        const today = screen.getByRole('link', { name: /Friday.*1 item/ });
        const past = screen.getByRole('link', {
            name: /Wednesday.*Quiet night/,
        });
        expect(today).toHaveAttribute('href', '/dashboard');
        expect(past).toHaveAttribute('href', '/days/2026-10-28');
        expect(
            today.compareDocumentPosition(past) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
        expect(screen.getByText('Programming')).toBeVisible();
        expect(screen.queryByText('No digests yet')).not.toBeInTheDocument();
        expect(screen.queryByText(/Page \d+ of/)).not.toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Previous' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Next' }),
        ).not.toBeInTheDocument();
    });

    it('uses the singular digest subtitle', () => {
        const props = archiveProps();
        props.days = props.days.slice(0, 1);
        props.pagination.total = 1;
        renderPage(props);
        expect(screen.getByText('1 digest')).toBeVisible();
        expect(screen.queryByText('1 digests')).not.toBeInTheDocument();
    });

    it('shows the empty state and no pagination for zero total', () => {
        const props = archiveProps();
        props.days = [];
        props.pagination.total = 0;
        renderPage(props);
        expect(screen.getByText('0 digests')).toBeVisible();
        expect(
            screen.getByRole('heading', { name: 'No digests yet' }),
        ).toBeVisible();
        expect(
            screen.getByText('Your past digests will appear here.'),
        ).toBeVisible();
        expect(screen.queryByRole('list')).not.toBeInTheDocument();
        expect(screen.queryByText(/Page \d+ of/)).not.toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Next' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Previous' }),
        ).not.toBeInTheDocument();
    });

    it.each([
        [1, null, '/archive?page=2'],
        [2, '/archive?page=1', '/archive?page=3'],
        [3, '/archive?page=2', null],
    ] as const)(
        'uses server pagination URLs on page %i',
        (current, previous, next) => {
            const props = archiveProps();
            props.pagination = {
                current_page: current,
                last_page: 3,
                total: 61,
                prev_url: previous,
                next_url: next,
            };
            renderPage(props);
            expect(screen.getByText('61 digests')).toBeVisible();
            expect(screen.getByText(`Page ${current} of 3`)).toBeVisible();
            for (const [name, href] of [
                ['Previous', previous],
                ['Next', next],
            ] as const) {
                if (href)
                    expect(screen.getByRole('link', { name })).toHaveAttribute(
                        'href',
                        href,
                    );
                else expectUnavailablePagination(name);
            }
        },
    );
});

it.each([true, false])(
    'links to Today only for an empty archive=%s',
    (empty) => {
        const props = archiveProps();
        if (empty) {
            props.days = [];
            props.pagination.total = 0;
        }
        renderPage(props);
        const link = screen.queryByRole('link', { name: 'Today' });
        if (empty) {
            expect(link).toHaveAttribute('href', '/dashboard');
            expect(screen.getByText(/to check your next digest/)).toBeVisible();
        } else expect(link).not.toBeInTheDocument();
    },
);
