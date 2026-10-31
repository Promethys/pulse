import type { LearningPost } from './day';
import type { Pagination } from './search';

export type LearningEntry = LearningPost & { date: string };

export type LearningFilterValues = {
    kind: LearningPost['kind'] | null;
    category: string | null;
    tag: string | null;
};

export type LearningProps = {
    entries: LearningEntry[];
    filters: LearningFilterValues;
    options: {
        categories: string[];
        tags: { slug: string; name: string }[];
    };
    pagination: Pagination;
};

export type LearningShowProps = { entry: LearningEntry };
