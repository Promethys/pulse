import { describe, expect, it } from 'vite-plus/test';
import { categoryLabel } from '@/lib/categories';

describe('categoryLabel', () => {
    it.each([
        ['ai-engineering', 'AI engineering'],
        ['ai', 'AI'],
        ['security', 'Security'],
        ['system-design', 'System design'],
        ['airflow', 'Airflow'],
        ['devops', 'DevOps'],
        ['php-internals', 'PHP internals'],
        ['modern-sql', 'Modern SQL'],
    ])('labels %s as %s', (category, label) => {
        expect(categoryLabel(category)).toBe(label);
    });
});
