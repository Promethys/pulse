import { router } from '@inertiajs/vue3';
import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import DayView from '@/components/pulse/DayView.vue';
import { dayViewProps, newsItemProps } from '../../support/dayProps';

function renderDay(props = dayViewProps()) {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
    const get = vi.spyOn(router, 'get').mockImplementation(() => {});
    const view = render(DayView, { props });
    return { view, visit, get, user: userEvent.setup() };
}

function expectNavigation(view: ReturnType<typeof renderDay>, url: string) {
    const calls = [...view.visit.mock.calls, ...view.get.mock.calls];
    expect(calls).toHaveLength(1);
    expect(calls[0][0]).toBe(url);
}

function calendarDate(day: number) {
    return screen.getByRole('button', {
        name: new RegExp(`\\b(?:${day} October|October ${day})\\b`),
    });
}

afterEach(() => {
    vi.unstubAllEnvs();
});

describe('DayView', () => {
    it.each([true, false])(
        'shows the news count with isToday=%s and a formatted date heading',
        (isToday) => {
            const props = dayViewProps();
            props.isToday = isToday;
            renderDay(props);
            expect(
                screen.getByText(`${isToday ? 'Today' : 'Digest'} · 1 item`),
            ).toBeVisible();
            expect(
                screen.getByRole('heading', {
                    level: 1,
                    name: 'Wednesday 28 October',
                }),
            ).toBeVisible();
            expect(
                screen.getByRole('heading', { level: 2, name: 'Programming' }),
            ).toBeVisible();
            expect(
                screen.getByRole('link', {
                    name: props.day.news!.sections[0].items[0].title,
                }),
            ).toBeVisible();
            expect(
                screen.getByRole('heading', {
                    name: props.day.learning!.tip!.title,
                }),
            ).toBeVisible();
            expect(
                screen.getByRole('heading', {
                    name: props.day.learning!.topic!.title,
                }),
            ).toBeVisible();
            expect(screen.queryByText('Quick recall')).not.toBeInTheDocument();
            expect(
                screen.queryByRole('button', {
                    name: /Ask|Useful|Not useful|Too basic/,
                }),
            ).not.toBeInTheDocument();
        },
    );

    it.each(['America/Los_Angeles', 'Pacific/Kiritimati'])(
        'keeps the calendar date at boundaries in %s',
        (timezone) => {
            vi.stubEnv('TZ', timezone);
            const props = dayViewProps();
            props.day.date = '2026-11-01';
            renderDay(props);
            expect(
                screen.getByRole('heading', {
                    level: 1,
                    name: 'Sunday 1 November',
                }),
            ).toBeVisible();
        },
    );

    it('renders multiple news sections in server order with their counts', () => {
        const props = dayViewProps();
        const second = {
            ...newsItemProps(),
            id: 12,
            title: 'A security release',
            tags: [],
        };
        props.day.news!.sections.push({
            slug: 'security',
            name: 'Security',
            items: [second],
        });
        props.day.news!.item_count = 2;
        renderDay(props);
        expect(screen.getByText('Digest · 2 items')).toBeVisible();
        expect(
            screen
                .getAllByRole('heading', { level: 2 })
                .map((heading) => heading.textContent),
        ).toEqual(['Programming', 'Security']);
        expect(screen.getByRole('link', { name: second.title })).toBeVisible();
        expect(screen.getAllByText('1')).toHaveLength(2);
    });

    it.each([
        ['Previous day', '/days/2026-10-20'],
        ['Next day', '/'],
    ])('navigates with %s to %s', async (name, url) => {
        const view = renderDay();
        await view.user.click(screen.getByRole('button', { name }));
        expectNavigation(view, url);
    });

    it('navigates to a newer historical date without treating it as today', async () => {
        const props = dayViewProps();
        props.today = '2026-10-31';
        const view = renderDay(props);
        await view.user.click(screen.getByRole('button', { name: 'Next day' }));
        expectNavigation(view, '/days/2026-10-30');
    });

    it.each(['previous_date', 'next_date'] as const)(
        'disables the %s end without making a request',
        async (key) => {
            const props = dayViewProps();
            props.day[key] = null;
            const view = renderDay(props);
            const name = key === 'previous_date' ? 'Previous day' : 'Next day';
            const button = screen.getByRole('button', { name });
            expect(button).toBeDisabled();
            await view.user.click(button);
            expect(view.visit).not.toHaveBeenCalled();
            expect(view.get).not.toHaveBeenCalled();
        },
    );

    it('disables both directions for the only available day', () => {
        const props = dayViewProps();
        props.day.previous_date = null;
        props.day.next_date = null;
        props.availableDates = [props.day.date];
        renderDay(props);
        expect(
            screen.getByRole('button', { name: 'Previous day' }),
        ).toBeDisabled();
        expect(screen.getByRole('button', { name: 'Next day' })).toBeDisabled();
    });

    it('opens the calendar with only available dates enabled and ignores an unavailable date', async () => {
        const view = renderDay();
        expect(screen.queryByRole('application')).not.toBeInTheDocument();
        await view.user.click(
            screen.getByRole('button', { name: 'Pick a date' }),
        );
        expect(await screen.findByRole('application')).toBeVisible();
        for (let day = 1; day <= 31; day++) {
            const button = calendarDate(day);
            const disabled =
                button.hasAttribute('disabled') ||
                button.getAttribute('aria-disabled') === 'true';
            expect(disabled).toBe(![20, 28, 30].includes(day));
        }
        const outsideMonth = screen.getAllByRole('button', {
            name: /September|November/,
        });
        outsideMonth.forEach((button) => {
            expect(
                button.hasAttribute('disabled') ||
                    button.getAttribute('aria-disabled') === 'true',
            ).toBe(true);
        });
        await view.user.click(calendarDate(29));
        expect(view.visit).not.toHaveBeenCalled();
        expect(view.get).not.toHaveBeenCalled();
    });

    it.each([
        [20, '/days/2026-10-20'],
        [30, '/'],
    ] as const)(
        'selects calendar day %i and navigates to %s',
        async (day, url) => {
            const view = renderDay();
            await view.user.click(
                screen.getByRole('button', { name: 'Pick a date' }),
            );
            await screen.findByRole('application');
            await view.user.click(calendarDate(day));
            expectNavigation(view, url);
        },
    );

    it.each(['tip', 'topic', 'neither'] as const)(
        'renders learning cards independently: %s',
        (present) => {
            const props = dayViewProps();
            const tipTitle = props.day.learning!.tip!.title;
            const topicTitle = props.day.learning!.topic!.title;
            if (present !== 'tip') props.day.learning!.tip = null;
            if (present !== 'topic') props.day.learning!.topic = null;
            renderDay(props);
            if (present === 'tip')
                expect(
                    screen.getByRole('heading', { name: tipTitle }),
                ).toBeVisible();
            else
                expect(
                    screen.queryByRole('heading', { name: tipTitle }),
                ).not.toBeInTheDocument();
            if (present === 'topic')
                expect(
                    screen.getByRole('heading', { name: topicTitle }),
                ).toBeVisible();
            else
                expect(
                    screen.queryByRole('heading', { name: topicTitle }),
                ).not.toBeInTheDocument();
            expect(
                screen.queryByText(/learning.*prepared|learning.*could not/i),
            ).not.toBeInTheDocument();
        },
    );

    it('omits learning cards when there is no learning post', () => {
        const props = dayViewProps();
        props.day.learning = null;
        renderDay(props);
        expect(
            screen.queryByRole('heading', {
                name: /Inspect query plans|How database isolation/,
            }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByText(/learning.*prepared|learning.*could not/i),
        ).not.toBeInTheDocument();
    });

    it('shows Quiet night only for generated news with zero items', () => {
        const props = dayViewProps();
        props.day.news = { status: 'generated', item_count: 0, sections: [] };
        renderDay(props);
        expect(screen.getByText('Digest · 0 items')).toBeVisible();
        expect(
            screen.getByRole('heading', { name: 'Quiet night' }),
        ).toBeVisible();
        expect(
            screen.getByText(
                'Nothing notable came through. Enjoy the tip and topic instead.',
            ),
        ).toBeVisible();
        expect(
            screen.queryByRole('link', { name: 'Open source' }),
        ).not.toBeInTheDocument();
    });

    it.each([
        ['pending', 'Your news is being prepared.'],
        ['failed', "Today's news could not be generated."],
    ] as const)(
        'shows %s news without a quiet-night fallback',
        (status, message) => {
            const props = dayViewProps();
            props.day.news = { status, item_count: 0, sections: [] };
            renderDay(props);
            expect(screen.getByText(message)).toBeVisible();
            expect(
                screen.queryByRole('heading', { name: 'Quiet night' }),
            ).not.toBeInTheDocument();
            expect(
                screen.queryByRole('link', { name: 'Open source' }),
            ).not.toBeInTheDocument();
        },
    );

    it('renders no news state when the news post is absent', () => {
        const props = dayViewProps();
        props.day.news = null;
        renderDay(props);
        expect(screen.getByText('Digest · 0 items')).toBeVisible();
        expect(
            screen.queryByRole('heading', { name: 'Quiet night' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByText(
                /Your news is being prepared|news could not be generated/,
            ),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Open source' }),
        ).not.toBeInTheDocument();
    });

    it.each(['pending', 'failed'] as const)(
        'shows a muted %s learning line when both cards are absent',
        (status) => {
            const props = dayViewProps();
            props.day.learning = { status, tip: null, topic: null };
            renderDay(props);
            const message =
                status === 'pending'
                    ? /learning.*being prepared/i
                    : /learning.*could not be generated/i;
            expect(screen.getByText(message)).toHaveClass(
                'text-muted-foreground',
            );
            expect(
                screen.queryByRole('heading', {
                    name: /Inspect query plans|How database isolation/,
                }),
            ).not.toBeInTheDocument();
        },
    );
});

describe('DayView archive navigation', () => {
    it('links back to the archive below past content and hides the link for today', async () => {
        const props = dayViewProps();
        const rendered = renderDay(props);
        const link = screen.getByRole('link', { name: 'Back to archive' });
        expect(link).toHaveAttribute('href', '/archive');
        const news = screen.getByRole('link', {
            name: props.day.news!.sections[0].items[0].title,
        });
        expect(
            news.compareDocumentPosition(link) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
        const topic = screen.getByRole('heading', {
            name: props.day.learning!.topic!.title,
        });
        expect(
            topic.compareDocumentPosition(link) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
        await rendered.view.rerender({
            isToday: true,
            day: { ...props.day, date: props.today },
        });
        expect(
            screen.queryByRole('link', { name: 'Back to archive' }),
        ).not.toBeInTheDocument();
    });
});
