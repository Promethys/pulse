import { router } from '@inertiajs/vue3';
import { render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { nextTick } from 'vue';
import Search from '@/pages/Search.vue';
import { learningPostProps, newsItemProps } from '../support/dayProps';
import { page } from '../support/inertia';

function searchProps() {
    return {
        type: 'news' as 'news' | 'learning',
        counts: { news: 1, learning: 3 },
        results: [newsItemProps()],
        pagination: {
            current_page: 1,
            last_page: 1,
            total: 1,
            prev_url: null as string | null,
            next_url: null as string | null,
        },
        filters: {
            type: 'news' as 'news' | 'learning',
            kind: null as 'tip' | 'topic' | null,
            category: null as string | null,
            q: null as string | null,
            tags: [] as string[],
            source: null as string | null,
            from: null as string | null,
            to: null as string | null,
        },
        options: {
            categories: ['databases', 'testing'],
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
    it.each(['news', 'learning'] as const)(
        'shows both tab counts and selects the %s tab',
        (type) => {
            const props = searchProps();
            props.type = type;
            props.filters.type = type;
            props.results = [];
            props.pagination.total = type === 'news' ? 1 : 3;
            renderPage(props);

            expect(
                screen.getByRole('tab', { name: /^News 1$/ }),
            ).toHaveAttribute('aria-selected', String(type === 'news'));
            expect(
                screen.getByRole('tab', { name: /^Learning 3$/ }),
            ).toHaveAttribute('aria-selected', String(type === 'learning'));
            expect(
                screen.getByText(type === 'news' ? '1 result' : '3 results'),
            ).toBeVisible();
        },
    );

    it('switches to learning with shared filters, drops source and returns to page one', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const props = searchProps();
        props.filters = {
            ...props.filters,
            q: 'compiler',
            tags: ['php'],
            source: 'compiler-news',
            from: '2026-10-20',
            to: '2026-10-29',
        };
        renderPage(props);
        await userEvent
            .setup()
            .click(screen.getByRole('tab', { name: /^Learning 3$/ }));

        expect(get).toHaveBeenCalledExactlyOnceWith(
            '/search',
            {
                type: 'learning',
                q: 'compiler',
                tags: ['php'],
                from: '2026-10-20',
                to: '2026-10-29',
            },
            visitOptions,
        );
    });

    it('switches to news without type, kind, category or page while keeping shared filters', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const props = searchProps();
        props.type = 'learning';
        props.filters = {
            ...props.filters,
            type: 'learning',
            q: 'compiler',
            tags: ['php'],
            kind: 'topic',
            category: 'databases',
            from: '2026-10-20',
            to: '2026-10-29',
        };
        props.results = [];
        renderPage(props);
        await userEvent
            .setup()
            .click(screen.getByRole('tab', { name: /^News 1$/ }));

        expect(get).toHaveBeenCalledExactlyOnceWith(
            '/search',
            {
                q: 'compiler',
                tags: ['php'],
                from: '2026-10-20',
                to: '2026-10-29',
            },
            visitOptions,
        );
    });

    it('switches an unfiltered search to learning without empty parameters', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage();
        await userEvent
            .setup()
            .click(screen.getByRole('tab', { name: /^Learning 3$/ }));
        expect(get).toHaveBeenCalledExactlyOnceWith(
            '/search',
            { type: 'learning' },
            visitOptions,
        );
    });

    it('renders learning cards with a formatted digest date above every result', async () => {
        const props = searchProps();
        props.type = 'learning';
        props.filters.type = 'learning';
        props.pagination.total = 3;
        const entries = [
            { ...learningPostProps(), date: '2026-10-28' },
            { ...learningPostProps('topic'), date: '2026-10-28' },
            {
                ...learningPostProps(),
                id: 23,
                title: 'Measure before tuning',
                date: '2026-10-27',
            },
        ];
        props.results = [];
        const view = renderPage(props);
        await view.rerender({ results: entries });

        expect(screen.getByText('3 results')).toBeVisible();
        expect(screen.getAllByText('Wednesday 28 October')).toHaveLength(2);
        expect(screen.getByText('Tuesday 27 October')).toBeVisible();
        for (const entry of entries) {
            expect(
                screen.getByRole('link', { name: entry.title }),
            ).toHaveAttribute('href', `/learning/${entry.id}`);
        }
        expect(
            screen.getAllByText('EXPLAIN', { selector: 'strong' }),
        ).toHaveLength(3);
        expect(screen.getAllByText(/^Tip$/)).toHaveLength(2);
        expect(screen.getByText(/^Topic/)).toBeVisible();
        expect(
            screen.queryByRole('link', { name: newsItemProps().title }),
        ).not.toBeInTheDocument();
    });

    it.each(['news', 'learning'] as const)(
        'shows the %s filters in the sidebar and sheet',
        async (type) => {
            const props = searchProps();
            props.type = type;
            props.filters.type = type;
            props.results = [];
            renderPage(props);
            expect(
                screen.getByRole('tab', {
                    name: type === 'news' ? /^News 1$/ : /^Learning 3$/,
                }),
            ).toHaveAttribute('aria-selected', 'true');
            const sidebar = screen.getByRole('complementary', {
                name: 'Search filters',
            });
            function expectFilters(container: HTMLElement) {
                expect(
                    within(container).getByRole('group', { name: 'Tags' }),
                ).toBeVisible();
                expect(
                    within(container).getByRole('button', { name: 'From' }),
                ).toBeVisible();
                expect(
                    within(container).getByRole('button', { name: 'To' }),
                ).toBeVisible();
                if (type === 'learning') {
                    expect(
                        within(container).getByRole('combobox', {
                            name: 'Kind',
                        }),
                    ).toBeVisible();
                    expect(
                        within(container).getByRole('combobox', {
                            name: 'Category',
                        }),
                    ).toBeVisible();
                    expect(
                        within(container).queryByRole('combobox', {
                            name: 'Source',
                        }),
                    ).not.toBeInTheDocument();
                } else {
                    expect(
                        within(container).getByRole('combobox', {
                            name: 'Source',
                        }),
                    ).toBeVisible();
                    expect(
                        within(container).queryByRole('combobox', {
                            name: 'Kind',
                        }),
                    ).not.toBeInTheDocument();
                    expect(
                        within(container).queryByRole('combobox', {
                            name: 'Category',
                        }),
                    ).not.toBeInTheDocument();
                }
            }
            expectFilters(sidebar);
            await userEvent
                .setup()
                .click(screen.getByRole('button', { name: /^Filters/ }));
            expectFilters(screen.getByRole('dialog', { name: 'Filters' }));
        },
    );

    it('applies learning kind and category immediately with shared filters and type', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const props = searchProps();
        props.type = 'learning';
        props.filters = {
            ...props.filters,
            type: 'learning',
            q: 'compiler',
            tags: ['php'],
            from: '2026-10-20',
            to: '2026-10-29',
        };
        props.results = [];
        renderPage(props);
        const user = userEvent.setup();
        await user.click(screen.getAllByRole('combobox', { name: 'Kind' })[0]);
        await user.click(screen.getByRole('option', { name: 'Topics' }));
        expect(get).toHaveBeenLastCalledWith(
            '/search',
            {
                type: 'learning',
                q: 'compiler',
                tags: ['php'],
                from: '2026-10-20',
                to: '2026-10-29',
                kind: 'topic',
            },
            visitOptions,
        );
        await user.click(
            screen.getAllByRole('combobox', { name: 'Category' })[0],
        );
        await user.click(screen.getByRole('option', { name: 'Databases' }));
        expect(get).toHaveBeenLastCalledWith(
            '/search',
            {
                type: 'learning',
                q: 'compiler',
                tags: ['php'],
                from: '2026-10-20',
                to: '2026-10-29',
                kind: 'topic',
                category: 'databases',
            },
            visitOptions,
        );
    });

    it('counts learning filters and clears them while retaining text and the active tab', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const props = searchProps();
        props.type = 'learning';
        props.filters = {
            ...props.filters,
            type: 'learning',
            q: 'compiler',
            tags: ['php'],
            kind: 'topic',
            category: 'databases',
            from: '2026-10-20',
            to: '2026-10-29',
        };
        props.results = [];
        renderPage(props);
        expect(
            within(screen.getByRole('button', { name: /^Filters/ })).getByText(
                '4',
            ),
        ).toBeVisible();
        await userEvent
            .setup()
            .click(screen.getByRole('button', { name: 'Clear filters' }));
        expect(get).toHaveBeenLastCalledWith(
            '/search',
            { type: 'learning', q: 'compiler' },
            visitOptions,
        );
        expect(
            screen.queryByRole('button', { name: 'Clear filters' }),
        ).not.toBeInTheDocument();
    });

    it('debounces learning text changes while retaining learning filters', async () => {
        vi.useFakeTimers();
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const props = searchProps();
        props.type = 'learning';
        props.filters = {
            ...props.filters,
            type: 'learning',
            kind: 'topic',
            category: 'testing',
        };
        props.results = [];
        renderPage(props);
        await userEvent
            .setup({ advanceTimers: vi.advanceTimersByTime })
            .type(screen.getByRole('searchbox'), 'compiler');
        vi.advanceTimersByTime(299);
        expect(get).not.toHaveBeenCalled();
        vi.advanceTimersByTime(1);
        await nextTick();
        expect(get).toHaveBeenCalledExactlyOnceWith(
            '/search',
            {
                type: 'learning',
                q: 'compiler',
                kind: 'topic',
                category: 'testing',
            },
            visitOptions,
        );
    });

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
            ...props.filters,
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
            {
                q: 'compiler',
                tags: ['programming', 'php'],
                source: 'compiler-news',
                from: '2026-10-20',
                to: '2026-10-29',
            },
            visitOptions,
        );
    });

    it('applies source and date changes immediately', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const user = userEvent.setup();
        const props = searchProps();
        props.filters = { ...props.filters, from: '2026-10-20' };
        renderPage(props);
        await user.click(
            screen.getAllByRole('combobox', { name: 'Source' })[0],
        );
        await user.click(screen.getByRole('option', { name: 'Compiler News' }));
        expect(get).toHaveBeenLastCalledWith(
            '/search',
            { source: 'compiler-news', from: '2026-10-20' },
            visitOptions,
        );
        await user.click(screen.getAllByRole('button', { name: 'From' })[0]);
        await screen.findByRole('application');
        await user.click(
            screen.getByRole('button', {
                name: /\b(?:22 October|October 22)\b/,
            }),
        );
        expect(get).toHaveBeenLastCalledWith(
            '/search',
            { source: 'compiler-news', from: '2026-10-22' },
            visitOptions,
        );
    });

    it('counts tags source and one date range, opens the sheet and closes on Show results', async () => {
        const user = userEvent.setup();
        const props = searchProps();
        props.filters = {
            ...props.filters,
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
            ...props.filters,
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
        expect(
            within(sheet).getByRole('button', { name: 'From' }),
        ).toHaveTextContent('Pick a date');
        expect(
            within(sheet).getByRole('button', { name: 'To' }),
        ).toHaveTextContent('Pick a date');
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

    it('clears the text with its own button and keeps the filters', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const props = searchProps();
        props.filters = { ...props.filters, q: 'compiler', tags: ['php'] };
        renderPage(props);
        const user = userEvent.setup();

        await user.click(screen.getByRole('button', { name: 'Clear search' }));

        expect(get).toHaveBeenLastCalledWith(
            '/search',
            { tags: ['php'] },
            visitOptions,
        );
        expect(
            screen.queryByRole('button', { name: 'Clear search' }),
        ).not.toBeInTheDocument();
        expect(screen.getByRole('searchbox')).toHaveFocus();
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
        expect(screen.getByText('28 Oct')).toBeVisible();
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

it('explains the scope of search', () => {
    renderPage();
    expect(
        screen.getByText('Search stories and learning saved in your digests.'),
    ).toBeVisible();
});
