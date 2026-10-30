import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { formatDate } from '@/lib/dates';

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
