import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { dateParts, formatDate, timeHasPassed } from '@/lib/dates';

afterEach(() => {
    vi.unstubAllEnvs();
});

describe('formatDate', () => {
    it.each([
        ['2026-10-28', 'Wednesday 28 October'],
        ['2026-11-01', 'Sunday 1 November'],
        ['2027-01-01', 'Friday 1 January'],
        ['2028-02-29', 'Tuesday 29 February'],
    ])('formats %s in en-GB with weekday, day and month', (date, expected) => {
        expect(formatDate(date)).toBe(expected);
    });

    it.each(['America/Los_Angeles', 'Pacific/Kiritimati', 'Pacific/Honolulu'])(
        'never shifts the date in %s at month and year boundaries',
        (timezone) => {
            vi.stubEnv('TZ', timezone);
            expect(formatDate('2026-11-01')).toBe('Sunday 1 November');
            expect(formatDate('2027-01-01')).toBe('Friday 1 January');
        },
    );
});

describe('dateParts', () => {
    it.each([
        [
            '2026-10-28',
            { weekday: 'Wednesday', day: '28', month: 'Oct', year: '2026' },
        ],
        [
            '2026-11-01',
            { weekday: 'Sunday', day: '1', month: 'Nov', year: '2026' },
        ],
        [
            '2027-01-01',
            { weekday: 'Friday', day: '1', month: 'Jan', year: '2027' },
        ],
        [
            '2028-02-29',
            { weekday: 'Tuesday', day: '29', month: 'Feb', year: '2028' },
        ],
    ])('splits %s into display parts', (date, expected) => {
        expect(dateParts(date)).toEqual(expected);
    });

    it.each(['America/Los_Angeles', 'Pacific/Kiritimati', 'Pacific/Honolulu'])(
        'keeps calendar parts stable at month and year boundaries in %s',
        (timezone) => {
            vi.stubEnv('TZ', timezone);
            expect(dateParts('2026-11-01')).toEqual({
                weekday: 'Sunday',
                day: '1',
                month: 'Nov',
                year: '2026',
            });
            expect(dateParts('2027-01-01')).toEqual({
                weekday: 'Friday',
                day: '1',
                month: 'Jan',
                year: '2027',
            });
        },
    );
});

describe('timeHasPassed', () => {
    it.each([
        ['09:00', 'Europe/Paris', '2026-10-30T07:59:00Z', false],
        ['09:00', 'Europe/Paris', '2026-10-30T08:00:00Z', true],
        ['09:00', 'Europe/Paris', '2026-10-30T10:00:00Z', true],
        ['06:15', 'Pacific/Honolulu', '2026-10-30T10:00:00Z', false],
        ['07:45', 'America/New_York', '2026-10-30T07:00:00Z', false],
        ['00:15', 'Asia/Tokyo', '2026-10-30T15:00:00Z', false],
    ])('compares %s in %s at %s', (time, timezone, now, passed) => {
        expect(timeHasPassed(time, timezone, new Date(now))).toBe(passed);
    });
});
