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
import Day from '@/pages/Day.vue';
import { dayProps } from '../support/dayProps';
import { page } from '../support/inertia';

function renderPage() {
    const props = {
        day: dayProps(),
        available_dates: ['2026-10-30', '2026-10-28', '2026-10-20'],
    };
    page.url = '/days/2026-10-28';
    page.props = { ...props, errors: {} };
    return render(Day, { props });
}

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-10-30T12:00:00Z'));
});

afterEach(() => {
    vi.useRealTimers();
});

describe('Day', () => {
    it('renders the historical digest, its sections and both learning cards', () => {
        const day = dayProps();
        renderPage();
        expect(screen.getByText('Digest · 1 item')).toBeVisible();
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
                name: day.news!.sections[0].items[0].title,
            }),
        ).toBeVisible();
        expect(
            screen.getByRole('heading', { name: day.learning!.tip!.title }),
        ).toBeVisible();
        expect(
            screen.getByRole('heading', { name: day.learning!.topic!.title }),
        ).toBeVisible();
        expect(screen.queryByText('Finish setting up')).not.toBeInTheDocument();
        expect(
            screen.queryByText(/Today's digest arrives/),
        ).not.toBeInTheDocument();
    });

    it('passes the historical navigation dates to DayView', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage();
        await userEvent
            .setup()
            .click(screen.getByRole('button', { name: 'Previous day' }));
        const calls = [...visit.mock.calls, ...get.mock.calls];
        expect(calls).toHaveLength(1);
        expect(calls[0][0]).toBe('/days/2026-10-20');
    });

    it('passes available_dates to the calendar and navigates to another historical day', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage();
        const user = userEvent.setup();
        await user.click(screen.getByRole('button', { name: 'Pick a date' }));
        await screen.findByRole('application');
        const missing = screen.getByRole('button', {
            name: /\b(?:29 October|October 29)\b/,
        });
        expect(
            missing.hasAttribute('disabled') ||
                missing.getAttribute('aria-disabled') === 'true',
        ).toBe(true);
        await user.click(
            screen.getByRole('button', {
                name: /\b(?:20 October|October 20)\b/,
            }),
        );
        const calls = [...visit.mock.calls, ...get.mock.calls];
        expect(calls).toHaveLength(1);
        expect(calls[0][0]).toBe('/days/2026-10-20');
    });
});
