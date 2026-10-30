export type DigestStatus = 'generated' | 'pending' | 'failed';

export type DayTag = { id: number; slug: string; name: string };

export type NewsItem = {
    id: number;
    title: string;
    summary: string | null;
    position: number;
    url: string;
    date: string;
    source: string | null;
    tags: DayTag[];
};

export type LearningPost = {
    id: number;
    kind: 'tip' | 'topic';
    date?: string;
    title: string;
    body: string;
    body_html: string;
    category: string | null;
    links: { title: string; url: string }[];
    tags: DayTag[];
};

export type Day = {
    date: string;
    previous_date: string | null;
    next_date: string | null;
    news: {
        status: DigestStatus;
        item_count: number;
        sections: { slug: string; name: string; items: NewsItem[] }[];
    } | null;
    learning: {
        status: DigestStatus;
        tip: LearningPost | null;
        topic: LearningPost | null;
    } | null;
};

export type SetupState = {
    interests: boolean;
    sources: boolean;
    ai_key: boolean;
    delivery: boolean;
};

export type TodayProps = {
    day: Day | null;
    is_today: boolean;
    today: string;
    next_digest: { time: string; timezone: string } | null;
    available_dates: string[];
    setup: SetupState;
};
