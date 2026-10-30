import type { ArchiveProps, NewsItem } from './day';
import type { LearningEntry } from './learning';

export type Pagination = ArchiveProps['pagination'];

export type SearchFilterValues = {
    type: 'news' | 'learning';
    kind: LearningEntry['kind'] | null;
    category: string | null;
    q: string | null;
    tags: string[];
    source: string | null;
    from: string | null;
    to: string | null;
};

export type SearchOptions = {
    categories: string[];
    tags: { slug: string; name: string }[];
    sources: { slug: string; name: string }[];
};

export type SearchProps = {
    type: SearchFilterValues['type'];
    counts: { news: number; learning: number };
    results: NewsItem[] | LearningEntry[];
    pagination: Pagination;
    filters: SearchFilterValues;
    options: SearchOptions;
};
