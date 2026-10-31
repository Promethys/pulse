import { router } from '@inertiajs/vue3';
import { render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import Index from '@/pages/learning/Index.vue';
import { learningPostProps } from '../../support/dayProps';
import { page } from '../../support/inertia';

function learningProps() {
    return {
        entries: [
            { ...learningPostProps(), date: '2026-10-28' },
            { ...learningPostProps('topic'), date: '2026-10-28' },
            {
                ...learningPostProps(),
                id: 23,
                title: 'Measure before tuning',
                date: '2026-10-27',
            },
        ],
        filters: {
            kind: null as 'tip' | 'topic' | null,
            category: null as string | null,
            tag: null as string | null,
        },
        options: {
            categories: ['Databases', 'Testing'],
            tags: [
                { slug: 'programming', name: 'Programming' },
                { slug: 'php', name: 'PHP' },
            ],
        },
        pagination: {
            current_page: 1,
            last_page: 1,
            total: 3,
            prev_url: null as string | null,
            next_url: null as string | null,
        },
    };
}

function renderPage(props = learningProps()) {
    page.url = '/learning?page=3';
    page.props = { ...props };
    return render(Index, { props });
}

const visitOptions = {
    preserveState: true,
    preserveScroll: true,
    replace: true,
};

function isPressed(button: HTMLElement): boolean {
    return (
        button.getAttribute('aria-pressed') === 'true' ||
        button.getAttribute('aria-checked') === 'true' ||
        ['on', 'checked'].includes(button.getAttribute('data-state') ?? '')
    );
}

describe('Learning Index', () => {
    it('defines tips and topics once, next to the page title', () => {
        renderPage();

        expect(
            screen.getByRole('button', { name: 'Help: Learning' }),
        ).toBeInTheDocument();
    });

    it('links to the learning tab of unified search even with active browsing filters', () => {
        const props = learningProps();
        props.filters = { kind: 'topic', category: 'Testing', tag: 'php' };
        renderPage(props);
        expect(
            screen.getByRole('link', { name: 'Search learning' }),
        ).toHaveAttribute('href', '/search?type=learning');
    });

    it('groups received tips and topics under one heading per date with responsive grids', () => {
        renderPage();
        expect(
            screen.getByRole('heading', { level: 1, name: 'Learning' }),
        ).toBeVisible();
        expect(screen.getByText('Tips and topics, day by day')).toBeVisible();
        expect(
            screen
                .getAllByRole('heading', { level: 2 })
                .map((heading) => heading.textContent?.trim()),
        ).toEqual(['Wednesday 28 October', 'Tuesday 27 October']);
        const tip = screen.getByRole('heading', {
            name: learningPostProps().title,
        });
        const topic = screen.getByRole('heading', {
            name: learningPostProps('topic').title,
        });
        const old = screen.getByRole('heading', {
            name: 'Measure before tuning',
        });
        const grid = tip.closest('.md\\:grid-cols-2');
        expect(grid).toHaveClass(
            'grid-cols-1',
            'items-start',
            'gap-4',
            'md:grid-cols-2',
        );
        expect(grid).toContainElement(topic);
        expect(grid).not.toContainElement(old);
        expect(old.closest('.md\\:grid-cols-2')).toHaveClass(
            'grid-cols-1',
            'items-start',
            'gap-4',
            'md:grid-cols-2',
        );
        expect(screen.getAllByText(/^Tip$/)).toHaveLength(2);
        expect(screen.getByText(/^Topic\s*·\s*Databases$/)).toBeVisible();
    });

    it('keeps the received group and entry order rather than sorting in the browser', () => {
        const props = learningProps();
        props.entries = [props.entries[2], props.entries[1], props.entries[0]];
        renderPage(props);
        expect(
            screen
                .getAllByRole('heading', { level: 2 })
                .map((heading) => heading.textContent?.trim()),
        ).toEqual(['Tuesday 27 October', 'Wednesday 28 October']);
        expect(
            screen
                .getAllByRole('heading', { level: 3 })
                .map((heading) => heading.textContent?.trim()),
        ).toEqual(props.entries.map((entry) => entry.title));
    });

    it.each([
        ['Tips', { kind: 'tip', tag: 'php' }],
        ['Topics', { kind: 'topic', category: 'Testing', tag: 'php' }],
    ] as const)(
        'selects %s and resets pagination, tips having no category',
        async (name, params) => {
            const get = vi.spyOn(router, 'get').mockImplementation(() => {});
            const props = learningProps();
            props.filters = { kind: null, category: 'Testing', tag: 'php' };
            renderPage(props);
            const user = userEvent.setup();
            expect(
                isPressed(screen.getByRole('button', { name: /^All$/ })),
            ).toBe(true);
            await user.click(screen.getByRole('button', { name }));
            expect(get).toHaveBeenLastCalledWith(
                '/learning',
                params,
                visitOptions,
            );
            expect(isPressed(screen.getByRole('button', { name }))).toBe(true);
            expect(
                isPressed(screen.getByRole('button', { name: /^All$/ })),
            ).toBe(false);
        },
    );

    it('disables the category filter on tips', () => {
        const props = learningProps();
        props.filters.kind = 'tip';
        renderPage(props);

        expect(
            screen.getByRole('combobox', { name: 'Category' }),
        ).toBeDisabled();
    });

    it('All clears the kind without sending empty filters or a page', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const props = learningProps();
        props.filters.kind = 'tip';
        renderPage(props);
        expect(isPressed(screen.getByRole('button', { name: 'Tips' }))).toBe(
            true,
        );
        await userEvent
            .setup()
            .click(screen.getByRole('button', { name: /^All$/ }));
        expect(get).toHaveBeenCalledExactlyOnceWith(
            '/learning',
            {},
            visitOptions,
        );
    });

    it('selects and clears a category while retaining the other filters', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const props = learningProps();
        props.filters = { kind: 'topic', category: null, tag: 'php' };
        renderPage(props);
        const user = userEvent.setup();
        await user.click(screen.getByRole('combobox', { name: 'Category' }));
        expect(
            screen
                .getAllByRole('option')
                .map((option) => option.textContent?.trim()),
        ).toEqual(['All categories', 'Databases', 'Testing']);
        await user.click(screen.getByRole('option', { name: 'Databases' }));
        expect(get).toHaveBeenLastCalledWith(
            '/learning',
            { kind: 'topic', category: 'Databases', tag: 'php' },
            visitOptions,
        );
        await user.click(screen.getByRole('combobox', { name: 'Category' }));
        await user.click(
            screen.getByRole('option', { name: 'All categories' }),
        );
        expect(get).toHaveBeenLastCalledWith(
            '/learning',
            { kind: 'topic', tag: 'php' },
            visitOptions,
        );
    });

    it('replaces the selected tag and toggles it off instead of accumulating tags', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const props = learningProps();
        props.filters = {
            kind: 'tip',
            category: 'Testing',
            tag: 'programming',
        };
        renderPage(props);
        const user = userEvent.setup();
        const programming = screen.getByRole('button', { name: 'Programming' });
        const php = screen.getByRole('button', { name: 'PHP' });
        expect(isPressed(programming)).toBe(true);
        expect(isPressed(php)).toBe(false);
        await user.click(php);
        expect(get).toHaveBeenLastCalledWith(
            '/learning',
            { kind: 'tip', category: 'Testing', tag: 'php' },
            visitOptions,
        );
        expect(isPressed(programming)).toBe(false);
        expect(isPressed(php)).toBe(true);
        await user.click(php);
        expect(get).toHaveBeenLastCalledWith(
            '/learning',
            { kind: 'tip', category: 'Testing' },
            visitOptions,
        );
        expect(isPressed(php)).toBe(false);
    });

    it.each(['kind', 'category', 'tag'] as const)(
        'Clear filters resets an active %s and all controls',
        async (filter) => {
            const get = vi.spyOn(router, 'get').mockImplementation(() => {});
            const props = learningProps();
            if (filter === 'kind') props.filters.kind = 'tip';
            if (filter === 'category') props.filters.category = 'Testing';
            if (filter === 'tag') props.filters.tag = 'php';
            renderPage(props);
            await userEvent
                .setup()
                .click(screen.getByRole('button', { name: 'Clear filters' }));
            expect(get).toHaveBeenCalledExactlyOnceWith(
                '/learning',
                {},
                visitOptions,
            );
            expect(
                screen.queryByRole('button', { name: 'Clear filters' }),
            ).not.toBeInTheDocument();
            expect(
                isPressed(screen.getByRole('button', { name: /^All$/ })),
            ).toBe(true);
            expect(
                screen.getByRole('combobox', { name: 'Category' }),
            ).toHaveTextContent('All categories');
            expect(isPressed(screen.getByRole('button', { name: 'PHP' }))).toBe(
                false,
            );
        },
    );

    it('Clear filters removes all active filters in one request', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const props = learningProps();
        props.filters = { kind: 'topic', category: 'Testing', tag: 'php' };
        renderPage(props);
        await userEvent
            .setup()
            .click(screen.getByRole('button', { name: 'Clear filters' }));
        expect(get).toHaveBeenCalledExactlyOnceWith(
            '/learning',
            {},
            visitOptions,
        );
    });

    it('uses the server pagination links with filters intact', () => {
        const props = learningProps();
        props.pagination = {
            current_page: 2,
            last_page: 3,
            total: 61,
            prev_url: '/learning?kind=tip&page=1',
            next_url: '/learning?kind=tip&page=3',
        };
        props.filters.kind = 'tip';
        renderPage(props);
        expect(screen.getByText(/^61\b/)).toBeVisible();
        const pagination = screen.getByRole('navigation', {
            name: 'Pagination',
        });
        expect(
            within(pagination).getByRole('link', { name: 'Previous' }),
        ).toHaveAttribute('href', props.pagination.prev_url);
        expect(
            within(pagination).getByRole('link', { name: 'Next' }),
        ).toHaveAttribute('href', props.pagination.next_url);
        expect(within(pagination).getByText('Page 2 of 3')).toBeVisible();
    });

    it('offers the first-use empty state without Clear filters or pagination', () => {
        const props = learningProps();
        props.entries = [];
        props.pagination.total = 0;
        props.options = { categories: [], tags: [] };
        renderPage(props);
        expect(screen.getByText('No tips yet')).toBeVisible();
        expect(
            screen.getByText(
                'Your tips and topics appear here after your next learning digest.',
            ),
        ).toBeVisible();
        expect(
            screen.getByRole('link', { name: 'Sources' }).parentElement,
        ).toHaveTextContent(
            'Choose an interest marked Includes learning on Sources, then enable Learning in Digest settings.',
        );
        expect(
            screen.queryByRole('button', { name: 'Clear filters' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('navigation', { name: 'Pagination' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('heading', { level: 2 }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('combobox', { name: 'Category' }),
        ).toBeVisible();
    });

    it.each(['kind', 'category', 'tag'] as const)(
        'offers No matches with an active %s',
        (filter) => {
            const props = learningProps();
            props.entries = [];
            props.pagination.total = 0;
            if (filter === 'kind') props.filters.kind = 'tip';
            if (filter === 'category') props.filters.category = 'Testing';
            if (filter === 'tag') props.filters.tag = 'php';
            renderPage(props);
            expect(screen.getByText('No matches')).toBeVisible();
            expect(screen.getByText('Try fewer filters.')).toBeVisible();
            expect(
                screen.getByRole('button', { name: 'Clear filters' }),
            ).toBeVisible();
            expect(screen.queryByText('No tips yet')).not.toBeInTheDocument();
        },
    );
});

it.each([null, 'tip', 'topic'] as const)(
    'explains categories only for kind=%s',
    (kind) => {
        const props = learningProps();
        props.filters.kind = kind;
        renderPage(props);
        const message = screen.queryByText('Categories apply to topics only.');
        if (kind === 'tip') expect(message).toBeVisible();
        else expect(message).not.toBeInTheDocument();
    },
);

it.each(['empty', 'filtered', 'populated'])(
    'offers setup links for the %s learning state',
    (state) => {
        const props = learningProps();
        if (state !== 'populated') {
            props.entries = [];
            props.pagination.total = 0;
        }
        if (state === 'filtered') props.filters.kind = 'tip';
        renderPage(props);
        for (const [name, href] of [
            ['Sources', '/sources'],
            ['Digest settings', '/settings/digest'],
        ]) {
            const link = screen.queryByRole('link', { name });
            if (state === 'empty') expect(link).toHaveAttribute('href', href);
            else expect(link).not.toBeInTheDocument();
        }
    },
);
