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
