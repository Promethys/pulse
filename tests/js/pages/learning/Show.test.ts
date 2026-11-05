import userEvent from '@testing-library/user-event';
import { render, screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import Show from '@/pages/learning/Show.vue';
import { learningPostProps } from '../../support/dayProps';
import { page } from '../../support/inertia';

function showProps(kind: 'tip' | 'topic' = 'tip') {
    return { entry: { ...learningPostProps(kind), date: '2026-10-28' } };
}

function renderPage(props = showProps()) {
    page.url = `/learning/${props.entry.id}`;
    page.props = { ...props };
    return render(Show, { props });
}

describe('Learning Show', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('adds a Copy button to the code block of the body', async () => {
        const user = userEvent.setup();
        const writeText = vi
            .spyOn(navigator.clipboard, 'writeText')
            .mockResolvedValue();
        renderPage();
        const copy = screen.getByRole('button', { name: 'Copy code' });

        expect(copy).toHaveClass('code-copy');
        expect(
            copy.closest('.code-block')?.querySelector(':scope > pre'),
        ).toHaveTextContent('SELECT 1;');
        expect(
            screen.getAllByRole('button', { name: 'Copy code' }),
        ).toHaveLength(1);

        await user.click(copy);

        expect(writeText).toHaveBeenCalledWith('SELECT 1;');
        expect(screen.getByRole('button', { name: 'Copied' })).toBeVisible();
    });

    it.each([
        ['tip', 'Tip'],
        ['topic', 'Topic'],
    ] as const)(
        'shows the full %s with its date, title, prose and Learning back link',
        (kind, label) => {
            const props = showProps(kind);
            renderPage(props);
            expect(
                screen.getByRole('link', { name: 'Learning' }),
            ).toHaveAttribute('href', '/learning');
            expect(
                screen.getByText(
                    new RegExp(
                        `^${label} of the day\\s*·\\s*Wednesday 28 October$`,
                    ),
                ),
            ).toBeVisible();
            const title = screen.getByRole('heading', {
                level: 1,
                name: props.entry.title,
            });
            expect(title).not.toHaveClass('line-clamp-2');
            expect(screen.getByText('EXPLAIN').tagName).toBe('STRONG');
            expect(screen.getByText('SELECT').tagName).toBe('CODE');
            expect(
                screen.getByText('SELECT 1;').closest('pre'),
            ).toBeInTheDocument();
            expect(screen.getByRole('listitem')).toHaveTextContent(
                'Measure first',
            );
            const prose = screen.getByText('EXPLAIN').closest('.pulse-prose');
            expect(prose).toBeInTheDocument();
            expect(prose).not.toHaveClass('line-clamp-6');
            expect(
                screen.getByRole('link', { name: 'Query plan guide' }),
            ).toHaveAttribute('href', 'https://docs.example.test/plans');
            expect(screen.getByText('Programming')).toBeVisible();
            expect(
                screen.queryByRole('button', { name: 'Read more' }),
            ).not.toBeInTheDocument();
        },
    );

    it('shows every suggested link in a new tab with noreferrer', () => {
        const props = showProps();
        props.entry.links = [
            {
                title: 'PostgreSQL documentation',
                url: 'https://docs.example.test/postgres',
            },
            { title: 'Index guide', url: 'https://docs.example.test/indexes' },
        ];
        renderPage(props);
        expect(
            screen.getByRole('heading', { name: 'Suggested reading' }),
        ).toBeVisible();
        props.entry.links.forEach((reading) => {
            const link = screen.getByRole('link', { name: reading.title });
            expect(link).toHaveAttribute('href', reading.url);
            expect(link).toHaveAttribute('target', '_blank');
            expect(link).toHaveAttribute('rel', 'noreferrer');
        });
    });

    it('omits Suggested reading for an empty links array', () => {
        renderPage();
        expect(screen.queryByText('Suggested reading')).not.toBeInTheDocument();
        expect(screen.getByText('EXPLAIN')).toBeVisible();
    });

    it('accepts a topic without category or tags without rendering placeholders', () => {
        const props = showProps('topic');
        props.entry.category = null;
        props.entry.tags = [];
        renderPage(props);
        expect(
            screen.getByText(/^Topic of the day\s*·\s*Wednesday 28 October$/),
        ).toBeVisible();
        expect(
            screen.queryByText(/null|undefined|Databases/),
        ).not.toBeInTheDocument();
        expect(screen.queryByText('Programming')).not.toBeInTheDocument();
    });
});
