import { router } from '@inertiajs/vue3';
import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import Today from '@/pages/Today.vue';
import { todayProps } from '../support/dayProps';
import { page } from '../support/inertia';

function renderPage(props = todayProps()) {
    page.url = '/dashboard';
    page.props = { ...props, errors: {} };
    return render(Today, { props });
}

describe('Today', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-10-30T07:00:00Z'));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it.each(['06:00', '23:00'])(
        'shows the pending news banner independently of schedule %s',
        (time) => {
            const props = todayProps();
            props.is_today = true;
            props.day!.news!.status = 'pending';
            props.next_digest!.time = time;
            renderPage(props);
            expect(
                screen.getByText(
                    'Your digest is being prepared. Refresh this page shortly.',
                ),
            ).toBeVisible();
        },
    );

    it.each(['generated', 'failed', 'missing'] as const)(
        'omits the banner for today with news %s',
        (status) => {
            const props = todayProps();
            props.is_today = true;
            if (status === 'missing') props.day!.news = null;
            else props.day!.news!.status = status;
            renderPage(props);
            expect(
                screen.queryByText(
                    'Your digest is being prepared. Refresh this page shortly.',
                ),
            ).not.toBeInTheDocument();
            expect(
                screen.queryByText(
                    'No digest for today yet. Showing your latest day.',
                ),
            ).not.toBeInTheDocument();
            if (status === 'failed') {
                expect(
                    screen.getByText(
                        'News generation failed. Check your AI keys in Settings and check your enabled sources.',
                    ),
                ).toBeVisible();
            }
        },
    );

    it('does not show a pending banner for an older day', () => {
        const props = todayProps();
        props.day!.news!.status = 'pending';
        renderPage(props);
        expect(
            screen.getByText(
                'No digest for today yet. Showing your latest day.',
            ),
        ).toBeVisible();
        expect(
            screen.queryByText(
                'Your digest is being prepared. Refresh this page shortly.',
            ),
        ).not.toBeInTheDocument();
    });

    it('never offers the archive link for an older digest shown on Today', () => {
        const props = todayProps();
        props.is_today = false;
        renderPage(props);

        expect(
            screen.queryByRole('link', { name: 'Back to archive' }),
        ).not.toBeInTheDocument();
    });

    it('welcomes a reader with no digest using the configured time and timezone', () => {
        const props = todayProps();
        props.day = null;
        props.available_dates = [];
        props.next_digest = { time: '06:15', timezone: 'Pacific/Honolulu' };
        renderPage(props);
        expect(
            screen.getByRole('heading', { level: 1, name: 'Today' }),
        ).toBeVisible();
        expect(screen.getByText('Welcome to Pulse')).toBeVisible();
        expect(
            screen.getByRole('heading', {
                name: 'Your first digest is on its way',
            }),
        ).toBeVisible();
        expect(
            screen.getByText(
                'Pulse prepares your digest every day at 06:15 (Pacific/Honolulu).',
            ),
        ).toBeVisible();
        expect(
            screen.queryByText(
                'Add an AI key in Settings to start receiving digests.',
            ),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Pick a date' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByText(/Today's digest will start preparing/),
        ).not.toBeInTheDocument();
    });

    it('handles no digest and no scheduled news without inventing a time', () => {
        const props = todayProps();
        props.day = null;
        props.next_digest = null;
        props.available_dates = [];
        renderPage(props);
        expect(
            screen.getByRole('heading', {
                name: 'Your first digest is on its way',
            }),
        ).toBeVisible();
        expect(
            screen.queryByText(/undefined|null|09:00|Europe\/Paris/),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByText(/Today's digest will start preparing/),
        ).not.toBeInTheDocument();
    });

    it.each([true, false])(
        'propagates is_today=%s to the digest eyebrow and renders its content',
        (isToday) => {
            const props = todayProps();
            props.is_today = isToday;
            if (isToday) props.day!.date = props.today;
            renderPage(props);
            expect(
                screen.getByText(`${isToday ? 'Today' : 'Digest'} · 1 item`),
            ).toBeVisible();
            expect(
                screen.getByRole('link', {
                    name: props.day!.news!.sections[0].items[0].title,
                }),
            ).toBeVisible();
            expect(
                screen.queryByText('Welcome to Pulse'),
            ).not.toBeInTheDocument();
            expect(screen.queryByText(/Preview:/)).not.toBeInTheDocument();
        },
    );

    it('explains when an older digest is displayed', () => {
        renderPage();
        const notice = screen.getByText(
            'No digest for today yet. Showing your latest day.',
        );
        expect(notice).toBeVisible();
        expect(
            notice.compareDocumentPosition(
                screen.getByRole('heading', { level: 1 }),
            ) & Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
    });

    it.each([true, false])(
        'hides a complete checklist with hasDay=%s',
        (hasDay) => {
            const props = todayProps();
            if (!hasDay) props.day = null;
            renderPage(props);
            expect(
                screen.queryByText('Finish setting up'),
            ).not.toBeInTheDocument();
            expect(screen.queryByRole('progressbar')).not.toBeInTheDocument();
            if (hasDay)
                expect(screen.getByText('Digest · 1 item')).toBeVisible();
            else
                expect(
                    screen.getByRole('heading', {
                        name: 'Your first digest is on its way',
                    }),
                ).toBeVisible();
        },
    );

    it.each(['interests', 'sources', 'ai_key'] as const)(
        'shows the checklist while %s is incomplete',
        (key) => {
            const props = todayProps();
            props.setup[key] = false;
            renderPage(props);
            expect(screen.getByText('Finish setting up')).toBeVisible();
            expect(screen.getByText('2 of 3 done')).toBeVisible();
            expect(screen.getByRole('progressbar')).toHaveAttribute(
                'aria-valuenow',
                String((2 / 3) * 100),
            );
        },
    );

    it('shows an incomplete checklist in the welcome state and hides it after completion', async () => {
        const props = todayProps();
        props.day = null;
        props.setup.ai_key = false;
        const view = renderPage(props);
        expect(screen.getByText('Finish setting up')).toBeVisible();
        await view.rerender({ setup: { ...props.setup, ai_key: true } });
        expect(screen.queryByText('Finish setting up')).not.toBeInTheDocument();
        expect(screen.queryByRole('progressbar')).not.toBeInTheDocument();
    });

    it('passes available dates and today through to calendar navigation', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage();
        const user = userEvent.setup();
        await user.click(screen.getByRole('button', { name: 'Pick a date' }));
        await screen.findByRole('application');
        await user.click(
            screen.getByRole('button', {
                name: /\b(?:30 October|October 30)\b/,
            }),
        );
        const calls = [...visit.mock.calls, ...get.mock.calls];
        expect(calls).toHaveLength(1);
        expect(calls[0][0]).toBe('/dashboard');
    });

    it('explains missing AI access before the first digest', () => {
        const props = todayProps();
        props.day = null;
        props.setup.ai_key = false;
        renderPage(props);
        expect(
            screen.getByText(
                'Add an AI key in Settings to start receiving digests.',
            ),
        ).toBeVisible();
        expect(
            screen.queryByText(
                /will be prepared at|Today's digest will start preparing|isn't ready yet/,
            ),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('heading', {
                name: 'Your first digest is on its way',
            }),
        ).not.toBeInTheDocument();
    });
});

it.each([true, false])(
    'does not require external delivery with hasDay=%s',
    (hasDay) => {
        const props = todayProps();
        if (!hasDay) props.day = null;
        props.setup.delivery = false;
        renderPage(props);
        expect(screen.queryByText('Finish setting up')).not.toBeInTheDocument();
    },
);
