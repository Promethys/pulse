import type { ArchiveProps, NewsItem } from './day';

export type Pagination = ArchiveProps['pagination'];

export type SearchFilterValues = {
    q: string | null;
    tags: string[];
    source: string | null;
    from: string | null;
    to: string | null;
};

export type SearchOptions = {
    tags: { slug: string; name: string }[];
    sources: { slug: string; name: string }[];
};

export type SearchProps = {
    results: NewsItem[];
    pagination: Pagination;
    filters: SearchFilterValues;
    options: SearchOptions;
};
