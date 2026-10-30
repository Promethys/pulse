import type { DayTag, NewsItem } from './day';
import type { Pagination } from './search';

export type TagProps = {
    tag: DayTag;
    count: number;
    weeks: number[];
    items: NewsItem[];
    pagination: Pagination;
};
