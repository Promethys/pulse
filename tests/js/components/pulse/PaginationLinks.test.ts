import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import PaginationLinks from '@/components/pulse/PaginationLinks.vue';

describe('PaginationLinks', () => {
    it.each([
        [1, null, '/search?q=compiler&page=2'],
        [2, '/search?q=compiler&page=1', '/search?q=compiler&page=3'],
        [3, '/search?q=compiler&page=2', null],
    ] as const)(
        'uses server URLs and disables absent links on page %i',
        (current, previous, next) => {
            render(PaginationLinks, {
                props: {
                    pagination: {
                        current_page: current,
                        last_page: 3,
                        total: 61,
                        prev_url: previous,
                        next_url: next,
                    },
                },
            });
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
                    expect(screen.getByText(name)).toHaveAttribute(
                        'aria-disabled',
                        'true',
                    );
                }
            }
        },
    );

    it.each([0, 1])(
        'hides pagination for %i results on a single page',
        (total) => {
            render(PaginationLinks, {
                props: {
                    pagination: {
                        current_page: 1,
                        last_page: 1,
                        total,
                        prev_url: null,
                        next_url: null,
                    },
                },
            });
            expect(screen.queryByRole('navigation')).not.toBeInTheDocument();
            expect(screen.queryByText(/Page \d+ of/)).not.toBeInTheDocument();
            expect(screen.queryByText('Previous')).not.toBeInTheDocument();
            expect(screen.queryByText('Next')).not.toBeInTheDocument();
        },
    );
});
