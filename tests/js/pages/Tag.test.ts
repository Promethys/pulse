import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import Tag from '@/pages/Tag.vue';
import { newsItemProps, tagProps } from '../support/dayProps';
import { page } from '../support/inertia';

function tagPageProps() {
    return {
        tag: tagProps(),
        count: 2,
        weeks: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1],
        items: [
            newsItemProps(),
            {
                ...newsItemProps(),
                id: 12,
                title: 'Second item',
                date: '2026-10-20',
            },
        ],
        pagination: {
            current_page: 1,
            last_page: 1,
            total: 2,
            prev_url: null as string | null,
            next_url: null as string | null,
        },
    };
}

function renderPage(props = tagPageProps()) {
    page.url = '/tags/programming';
    page.props = { ...props, errors: {} };
    return render(Tag, { props });
}

describe('Tag', () => {
    it('shows the tag name and item count in the header', () => {
        renderPage();
        expect(
            screen.getByRole('heading', { level: 1, name: 'Programming' }),
        ).toBeVisible();
        expect(screen.getByText('2 items · newest first')).toBeVisible();
    });

    it('lists items in server order with their digest dates', () => {
        renderPage();
        const first = screen.getByRole('heading', {
            name: newsItemProps().title,
        });
        const second = screen.getByRole('heading', { name: 'Second item' });
        expect(
            first.compareDocumentPosition(second) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
        expect(screen.getByText('28 Oct')).toBeVisible();
        expect(screen.getByText('20 Oct')).toBeVisible();
    });

    it('shows the weekly sparkline and caption', () => {
        renderPage();
        expect(
            screen.getByRole('img', {
                name: 'Items per week, last 12 weeks',
            }),
        ).toBeVisible();
        expect(screen.getByText('per week · 12w')).toBeVisible();
    });

    it('uses the singular item subtitle', () => {
        const props = tagPageProps();
        props.count = 1;
        props.items = [newsItemProps()];
        props.pagination.total = 1;
        renderPage(props);
        expect(screen.getByText('1 item · newest first')).toBeVisible();
        expect(
            screen.queryByText('1 items · newest first'),
        ).not.toBeInTheDocument();
    });

    it('shows the empty state and hides the sparkline for zero items', () => {
        const props = tagPageProps();
        props.count = 0;
        props.items = [];
        props.weeks = Array.from({ length: 12 }, () => 0);
        props.pagination.total = 0;
        renderPage(props);
        expect(screen.getByText('No items yet')).toBeVisible();
        expect(
            screen.getByRole('heading', { name: 'Nothing here yet' }),
        ).toBeVisible();
        expect(
            screen.getByText('No saved news uses this tag yet.'),
        ).toBeVisible();
        expect(
            screen.queryByRole('img', {
                name: 'Items per week, last 12 weeks',
            }),
        ).not.toBeInTheDocument();
        expect(screen.queryByText('per week · 12w')).not.toBeInTheDocument();
    });

    it.each([
        [1, null, '/tags/programming?page=2'],
        [2, '/tags/programming?page=1', '/tags/programming?page=3'],
        [3, '/tags/programming?page=2', null],
    ] as const)(
        'uses server pagination URLs on page %i',
        (current, previous, next) => {
            const props = tagPageProps();
            props.count = 61;
            props.pagination = {
                current_page: current,
                last_page: 3,
                total: 61,
                prev_url: previous,
                next_url: next,
            };
            renderPage(props);
            expect(screen.getByText(`Page ${current} of 3`)).toBeVisible();
            for (const [name, href] of [
                ['Previous', previous],
                ['Next', next],
            ] as const) {
                if (href) {
                    expect(screen.getByRole('link', { name })).toHaveAttribute(
                        'href',
                        href,
                    );
                } else {
                    expect(
                        screen.queryByRole('link', { name }),
                    ).not.toBeInTheDocument();
                }
            }
        },
    );

    it('hides pagination for a single page', () => {
        renderPage();
        expect(screen.queryByText(/Page \d+ of/)).not.toBeInTheDocument();
    });
});

it.each([true, false])(
    'links to browsing only for an empty tag=%s',
    (empty) => {
        const props = tagPageProps();
        if (empty) {
            props.count = 0;
            props.items = [];
            props.pagination.total = 0;
        }
        renderPage(props);
        for (const [name, href] of [
            ['Today', '/'],
            ['Search', '/search'],
        ]) {
            const link = screen.queryByRole('link', { name });
            if (empty) expect(link).toHaveAttribute('href', href);
            else expect(link).not.toBeInTheDocument();
        }
    },
);

it('defines the weekly story counts', async () => {
    renderPage();
    screen.getByRole('button', { name: 'Help: Weekly stories' }).focus();
    expect(await screen.findByRole('tooltip')).toHaveTextContent(
        'News stories in your digests with this tag, grouped into weeks over the last 12 weeks.',
    );
});

it('hides weekly story help when no stories use the tag', () => {
    renderPage({ ...tagPageProps(), count: 0, items: [] });
    expect(
        screen.queryByRole('button', { name: 'Help: Weekly stories' }),
    ).not.toBeInTheDocument();
});
