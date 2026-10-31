export function dateParts(date: string): {
    weekday: string;
    day: string;
    month: string;
    year: string;
} {
    const parts = new Intl.DateTimeFormat('en-GB', {
        weekday: 'long',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).formatToParts(new Date(`${date}T00:00:00Z`));
    const part = (type: Intl.DateTimeFormatPartTypes) =>
        parts.find((item) => item.type === type)?.value ?? '';

    return {
        weekday: part('weekday'),
        day: part('day'),
        month: part('month'),
        year: part('year'),
    };
}

export function formatDate(date: string): string {
    const parts = new Intl.DateTimeFormat('en-GB', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone: 'UTC',
    }).formatToParts(new Date(`${date}T00:00:00Z`));
    const part = (type: Intl.DateTimeFormatPartTypes) =>
        parts.find((item) => item.type === type)?.value;

    return `${part('weekday')} ${part('day')} ${part('month')}`;
}

export function timeHasPassed(
    time: string,
    timezone: string,
    now: Date = new Date(),
): boolean {
    const local = new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
        timeZone: timezone,
    }).format(now);

    return local >= time;
}
