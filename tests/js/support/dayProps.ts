type Status = 'generated' | 'pending' | 'failed';

export function tagProps() {
    return { id: 1, slug: 'programming', name: 'Programming' };
}

export function newsItemProps() {
    return {
        id: 11,
        title: 'A deliberately long news title about a new compiler release',
        summary: 'The compiler improves incremental builds.' as string | null,
        position: 1,
        url: 'https://news.example.test/compiler',
        date: '2026-10-28',
        source: 'Compiler News' as string | null,
        tags: [tagProps()],
    };
}

export function learningPostProps(kind: 'tip' | 'topic' = 'tip') {
    return {
        id: kind === 'tip' ? 21 : 22,
        kind,
        title:
            kind === 'tip'
                ? 'Inspect query plans before adding indexes'
                : 'How database isolation protects concurrent writes',
        body: 'Use **EXPLAIN** and `SELECT`.',
        body_html:
            '<p>Use <strong>EXPLAIN</strong> and <code>SELECT</code>.</p><pre><code>SELECT 1;</code></pre><ul><li>Measure first</li></ul><p><a href="https://docs.example.test/plans">Query plan guide</a></p>',
        category: (kind === 'topic' ? 'Databases' : null) as string | null,
        links: [] as { title: string; url: string }[],
        tags: [tagProps()],
    };
}

export function dayProps() {
    return {
        date: '2026-10-28',
        previous_date: '2026-10-20' as string | null,
        next_date: '2026-10-30' as string | null,
        news: {
            status: 'generated' as Status,
            item_count: 1,
            sections: [
                {
                    slug: 'programming',
                    name: 'Programming',
                    items: [newsItemProps()],
                },
            ],
        } as {
            status: Status;
            item_count: number;
            sections: {
                slug: string;
                name: string;
                items: ReturnType<typeof newsItemProps>[];
            }[];
        } | null,
        learning: {
            status: 'generated' as Status,
            tip: learningPostProps('tip'),
            topic: learningPostProps('topic'),
        } as {
            status: Status;
            tip: ReturnType<typeof learningPostProps> | null;
            topic: ReturnType<typeof learningPostProps> | null;
        } | null,
    };
}

export function dayViewProps() {
    return {
        day: dayProps(),
        availableDates: ['2026-10-30', '2026-10-28', '2026-10-20'],
        isToday: false,
        today: '2026-10-30',
    };
}

export function setupProps() {
    return { interests: true, sources: true, ai_key: true, delivery: true };
}

export function todayProps() {
    return {
        day: dayProps() as ReturnType<typeof dayProps> | null,
        is_today: false,
        today: '2026-10-30',
        next_digest: { time: '09:00', timezone: 'Europe/Paris' } as {
            time: string;
            timezone: string;
        } | null,
        available_dates: ['2026-10-30', '2026-10-28', '2026-10-20'],
        setup: setupProps(),
    };
}
