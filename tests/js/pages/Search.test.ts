import { router } from '@inertiajs/vue3';
import { fireEvent, render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { nextTick } from 'vue';
import Search from '@/pages/Search.vue';
import { newsItemProps } from '../support/dayProps';
import { page } from '../support/inertia';

function searchProps() {
    return {
        results: [newsItemProps()],
        pagination: {
            current_page: 1,
            last_page: 1,
            total: 1,
            prev_url: null as string | null,
            next_url: null as string | null,
        },
        filters: {
            q: null as string | null,
            tags: [] as string[],
            source: null as string | null,
            from: null as string | null,
            to: null as string | null,
        },
        options: {
            tags: [
                { slug: 'programming', name: 'Programming' },
                { slug: 'php', name: 'PHP' },
            ],
            sources: [{ slug: 'compiler-news', name: 'Compiler News' }],
        },
    };
}

function renderPage(
    props = searchProps(),
    errors: Record<string, string> = {},
) {
    page.url = '/search?page=3';
    page.props = { ...props, errors };
    return render(Search, { props });
}

const visitOptions = {
    preserveState: true,
    preserveScroll: true,
    replace: true,
};

afterEach(() => {
    vi.useRealTimers();
});

describe('Search', () => {
    it('waits 300 ms after the last keystroke and returns to page one', async () => {
        vi.useFakeTimers();
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
        renderPage();
        const search = screen.getByRole('searchbox');
        expect(search).toHaveAttribute(
            'placeholder',
            'Search titles and summaries…',
        );
        await user.type(search, 'comp');
        vi.advanceTimersByTime(299);
        expect(get).not.toHaveBeenCalled();
        await user.type(search, 'iler');
        vi.advanceTimersByTime(299);
        expect(get).not.toHaveBeenCalled();
        vi.advanceTimersByTime(1);
        await nextTick();
        expect(get).toHaveBeenCalledExactlyOnceWith(
            '/search',
            { q: 'compiler' },
            visitOptions,
        );
    });

    it('drops cleared and empty parameters instead of sending q or page', async () => {
        vi.useFakeTimers();
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
        const props = searchProps();
        props.filters.q = 'compiler';
        props.filters.tags = ['php'];
        renderPage(props);
        await user.clear(screen.getByRole('searchbox'));
        vi.advanceTimersByTime(300);
        await nextTick();
        expect(get).toHaveBeenLastCalledWith(
            '/search',
            { tags: ['php'] },
            visitOptions,
        );
    });

    it('applies tag changes immediately with existing text source and dates', async () => {
        vi.useFakeTimers();
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
        const props = searchProps();
        props.filters = {
            q: 'compiler',
            tags: ['programming'],
            source: 'compiler-news',
            from: '2026-10-20',
            to: '2026-10-29',
        };
        renderPage(props);
        await user.click(screen.getAllByRole('button', { name: 'PHP' })[0]);
        expect(get).toHaveBeenLastCalledWith(
            '/search',
            { ...props.filters, tags: ['programming', 'php'] },
            visitOptions,
        );
    });

    it('applies source and date changes immediately', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const user = userEvent.setup();
        renderPage();
        await user.click(
            screen.getAllByRole('combobox', { name: 'Source' })[0],
        );
        await user.click(screen.getByRole('option', { name: 'Compiler News' }));
        expect(get).toHaveBeenLastCalledWith(
            '/search',
            { source: 'compiler-news' },
            visitOptions,
        );
        await fireEvent.update(
            screen.getAllByLabelText('From')[0],
            '2026-10-20',
        );
        expect(get).toHaveBeenLastCalledWith(
            '/search',
            { source: 'compiler-news', from: '2026-10-20' },
            visitOptions,
        );
        await fireEvent.update(screen.getAllByLabelText('To')[0], '2026-10-29');
        expect(get).toHaveBeenLastCalledWith(
            '/search',
            { source: 'compiler-news', from: '2026-10-20', to: '2026-10-29' },
            visitOptions,
        );
    });

    it('counts tags source and one date range, opens the sheet and closes on Show results', async () => {
        const user = userEvent.setup();
        const props = searchProps();
        props.filters = {
            q: 'compiler',
            tags: ['programming', 'php'],
            source: 'compiler-news',
            from: '2026-10-20',
            to: '2026-10-29',
        };
        props.pagination.total = 7;
        renderPage(props);
        const trigger = screen.getByRole('button', { name: /^Filters/ });
        expect(within(trigger).getByText('4')).toBeVisible();
        await user.click(trigger);
        const sheet = screen.getByRole('dialog', { name: 'Filters' });
        expect(
            within(sheet).getByRole('button', { name: 'PHP' }),
        ).toHaveAttribute('aria-pressed', 'true');
        await user.click(
            within(sheet).getByRole('button', { name: 'Show 7 results' }),
        );
        expect(
            screen.queryByRole('dialog', { name: 'Filters' }),
        ).not.toBeInTheDocument();
    });

    it('omits the count badge for text alone and closes the sheet with Escape', async () => {
        const props = searchProps();
        props.filters.q = 'compiler';
        renderPage(props);
        const user = userEvent.setup();
        const trigger = screen.getByRole('button', { name: /^Filters/ });
        expect(within(trigger).queryByText(/^\d+$/)).not.toBeInTheDocument();
        await user.click(trigger);
        expect(screen.getByRole('dialog', { name: 'Filters' })).toBeVisible();
        await user.keyboard('{Escape}');
        expect(
            screen.queryByRole('dialog', { name: 'Filters' }),
        ).not.toBeInTheDocument();
    });

    it('Reset clears filters immediately and retains the text query', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const props = searchProps();
        props.filters = {
            q: 'compiler',
            tags: ['php'],
            source: 'compiler-news',
            from: '2026-10-20',
            to: '2026-10-29',
        };
        renderPage(props);
        const user = userEvent.setup();
        await user.click(screen.getByRole('button', { name: /^Filters/ }));
        const sheet = screen.getByRole('dialog', { name: 'Filters' });
        await user.click(within(sheet).getByRole('button', { name: 'Reset' }));
        expect(get).toHaveBeenLastCalledWith(
            '/search',
            { q: 'compiler' },
            visitOptions,
        );
        expect(
            within(sheet).getByRole('button', { name: 'PHP' }),
        ).toHaveAttribute('aria-pressed', 'false');
        expect(within(sheet).getByLabelText('From')).toHaveValue('');
        expect(within(sheet).getByLabelText('To')).toHaveValue('');
    });

    it('offers Clear filters only with an active filter and keeps the text', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const props = searchProps();
        props.filters = { ...props.filters, q: 'compiler', tags: ['php'] };
        renderPage(props);
        const user = userEvent.setup();

        await user.click(screen.getByRole('button', { name: 'Clear filters' }));

        expect(get).toHaveBeenLastCalledWith(
            '/search',
            { q: 'compiler' },
            visitOptions,
        );
        expect(
            screen.queryByRole('button', { name: 'Clear filters' }),
        ).not.toBeInTheDocument();
    });

    it('hides Clear filters when only the text is set', () => {
        const props = searchProps();
        props.filters = { ...props.filters, q: 'compiler' };
        renderPage(props);

        expect(
            screen.queryByRole('button', { name: 'Clear filters' }),
        ).not.toBeInTheDocument();
    });

    it('uses server results with singular count and digest dates', () => {
        renderPage();
        expect(screen.getByText('1 result')).toBeVisible();
        expect(screen.queryByText('1 results')).not.toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: newsItemProps().title }),
        ).toHaveAttribute('href', newsItemProps().url);
        expect(screen.getByText(/·\s*28 Oct/)).toBeVisible();
    });

    it('uses the same empty state with no digests or no matches', () => {
        const props = searchProps();
        props.results = [];
        props.pagination.total = 0;
        const view = renderPage(props);
        expect(screen.getByText('0 results')).toBeVisible();
        expect(
            screen.getByRole('heading', { name: 'No matches' }),
        ).toBeVisible();
        expect(
            screen.getByText('Try fewer filters or a broader word.'),
        ).toBeVisible();
        expect(screen.queryByRole('article')).not.toBeInTheDocument();
        expect(view.container).not.toHaveTextContent(/Page \d+ of/);
    });

    it('shows date validation errors in the filter sheet', async () => {
        renderPage(searchProps(), {
            from: 'Invalid start date.',
            to: 'End date must follow start date.',
        });
        await userEvent
            .setup()
            .click(screen.getByRole('button', { name: /^Filters/ }));
        const sheet = screen.getByRole('dialog', { name: 'Filters' });
        expect(within(sheet).getByText('Invalid start date.')).toBeVisible();
        expect(
            within(sheet).getByText('End date must follow start date.'),
        ).toBeVisible();
    });

    it('keeps the search query in server pagination links', () => {
        const props = searchProps();
        props.pagination = {
            current_page: 2,
            last_page: 3,
            total: 61,
            prev_url: '/search?q=compiler&page=1',
            next_url: '/search?q=compiler&page=3',
        };
        renderPage(props);
        expect(screen.getByText('61 results')).toBeVisible();
        expect(screen.getByText('Page 2 of 3')).toBeVisible();
        expect(screen.getByRole('link', { name: 'Previous' })).toHaveAttribute(
            'href',
            props.pagination.prev_url,
        );
        expect(screen.getByRole('link', { name: 'Next' })).toHaveAttribute(
            'href',
            props.pagination.next_url,
        );
    });
});
