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
    page.url = '/';
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

    it('says the digest is late once its time has passed', () => {
        vi.setSystemTime(new Date('2026-10-30T10:00:00Z'));
        renderPage();

        expect(
            screen.getByText("Today's digest isn't ready yet."),
        ).toBeVisible();
        expect(screen.queryByText(/arrives at/)).not.toBeInTheDocument();
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
                'Your first digest arrives tomorrow at 06:15 (Pacific/Honolulu).',
            ),
        ).toBeVisible();
        expect(
            screen.queryByRole('button', { name: 'Pick a date' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByText(/Today's digest arrives/),
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
            screen.queryByText(/Today's digest arrives/),
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

    it.each([true, false])(
        'shows the scheduled notice above an existing digest when is_today=%s',
        (isToday) => {
            const props = todayProps();
            props.is_today = isToday;
            if (isToday) props.day!.date = props.today;
            props.next_digest = { time: '07:45', timezone: 'America/New_York' };
            renderPage(props);
            const notice = screen.getByText(
                "Today's digest arrives at 07:45 (America/New_York).",
            );
            expect(notice).toBeVisible();
            const heading = screen.getByRole('heading', { level: 1 });
            expect(
                notice.compareDocumentPosition(heading) &
                    Node.DOCUMENT_POSITION_FOLLOWING,
            ).toBeTruthy();
        },
    );

    it.each([true, false])(
        'omits the notice without next_digest even when is_today=%s',
        (isToday) => {
            const props = todayProps();
            props.is_today = isToday;
            if (isToday) props.day!.date = props.today;
            props.next_digest = null;
            renderPage(props);
            expect(
                screen.queryByText(/Today's digest arrives/),
            ).not.toBeInTheDocument();
            expect(
                screen.getByText(`${isToday ? 'Today' : 'Digest'} · 1 item`),
            ).toBeVisible();
        },
    );

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

    it.each(['interests', 'sources', 'ai_key', 'delivery'] as const)(
        'shows the checklist while %s is incomplete',
        (key) => {
            const props = todayProps();
            props.setup[key] = false;
            renderPage(props);
            expect(screen.getByText('Finish setting up')).toBeVisible();
            expect(screen.getByText('3 of 4 done')).toBeVisible();
            expect(screen.getByRole('progressbar')).toHaveAttribute(
                'aria-valuenow',
                '75',
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
        expect(calls[0][0]).toBe('/');
    });
});
