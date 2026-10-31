import { render, screen } from '@testing-library/vue';
import { router } from '@inertiajs/vue3';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import ErrorPage from '@/pages/Error.vue';

describe('Error', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it.each([
        [403, /access|permission|forbidden|allowed/i],
        [404, 'This page wandered off'],
        [419, /session|expired/i],
        [429, /too many|slow|rate|requests/i],
        [500, 'Something broke on our side'],
        [503, /unavailable|maintenance|back soon|right back|taking a break/i],
    ] as const)(
        'explains status %s and offers the correct recovery actions',
        (status, title) => {
            const { container } = render(ErrorPage, { props: { status } });

            const heading = screen.getByRole('heading', {
                level: 1,
                name: title,
            });
            expect(heading).toBeVisible();
            expect(
                screen.getByRole('link', { name: 'Back to Pulse' }),
            ).toHaveAttribute('href', '/');
            expect(
                [...container.querySelectorAll('p')].some((paragraph) => {
                    const body = paragraph.textContent?.trim();
                    return (
                        Boolean(body) &&
                        body !== String(status) &&
                        body !== heading.textContent?.trim()
                    );
                }),
            ).toBe(true);

            if (status === 500 || status === 503) {
                expect(
                    screen.getByRole('button', { name: 'Try again' }),
                ).toBeVisible();
            } else {
                expect(
                    screen.queryByRole('button', { name: 'Try again' }),
                ).not.toBeInTheDocument();
            }

            if (status === 404) {
                expect(
                    screen.getByText('The link may be old, or the page moved.'),
                ).toBeVisible();
            }
            if (status === 500) {
                expect(
                    screen.getByText(
                        "It's not you. Try again in a moment; if it keeps happening, the issue is already logged.",
                    ),
                ).toBeVisible();
            }
        },
    );

    it.each([500, 503])('reloads after retrying status %s', async (status) => {
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => {});
        render(ErrorPage, { props: { status } });

        expect(reload).not.toHaveBeenCalled();
        await userEvent
            .setup()
            .click(screen.getByRole('button', { name: 'Try again' }));

        expect(reload).toHaveBeenCalledOnce();
    });
});
