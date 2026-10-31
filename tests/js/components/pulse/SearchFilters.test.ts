import { render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import SearchFilters from '@/components/pulse/SearchFilters.vue';

function props() {
    return {
        modelValue: {
            type: 'news' as 'news' | 'learning',
            kind: null as 'tip' | 'topic' | null,
            category: null as string | null,
            q: null as string | null,
            tags: ['php'],
            source: null as string | null,
            from: null as string | null,
            to: null as string | null,
        },
        options: {
            categories: ['api-design', 'databases'],
            tags: [
                { slug: 'php', name: 'PHP' },
                { slug: 'vue', name: 'Vue' },
            ],
            sources: [{ slug: 'compiler-news', name: 'Compiler News' }],
        },
        errors: {} as Record<string, string>,
    };
}

describe('SearchFilters', () => {
    it('offers kind choices and emits selection and clearing without changing shared filters', async () => {
        const initial = props();
        initial.modelValue.type = 'learning';
        initial.modelValue.q = 'compiler';
        initial.modelValue.from = '2026-10-20';
        const view = render(SearchFilters, { props: initial });
        const user = userEvent.setup();
        const kind = screen.getByRole('combobox', { name: 'Kind' });
        expect(
            screen.queryByRole('combobox', { name: 'Source' }),
        ).not.toBeInTheDocument();
        await user.click(kind);
        expect(
            screen
                .getAllByRole('option')
                .map((option) => option.textContent?.trim()),
        ).toEqual(['All', 'Tips', 'Topics']);
        await user.click(screen.getByRole('option', { name: 'Topics' }));
        expect(view.emitted()['update:modelValue']).toContainEqual([
            { ...initial.modelValue, kind: 'topic' },
        ]);
        await view.rerender({
            modelValue: { ...initial.modelValue, kind: 'topic' },
        });
        await user.click(kind);
        await user.click(screen.getByRole('option', { name: /^All$/ }));
        expect(view.emitted()['update:modelValue']).toContainEqual([
            { ...initial.modelValue, kind: null },
        ]);
        await user.click(kind);
        await user.click(screen.getByRole('option', { name: 'Tips' }));
        expect(view.emitted()['update:modelValue']).toContainEqual([
            { ...initial.modelValue, kind: 'tip' },
        ]);
    });

    it('labels learning categories and emits selection and clearing', async () => {
        const initial = props();
        initial.modelValue.type = 'learning';
        initial.modelValue.kind = 'topic';
        const view = render(SearchFilters, { props: initial });
        const user = userEvent.setup();
        const category = screen.getByRole('combobox', { name: 'Category' });
        await user.click(category);
        expect(
            screen
                .getAllByRole('option')
                .map((option) => option.textContent?.trim()),
        ).toEqual(['All categories', 'API design', 'Databases']);
        await user.click(screen.getByRole('option', { name: 'API design' }));
        expect(view.emitted()['update:modelValue']).toContainEqual([
            { ...initial.modelValue, category: 'api-design' },
        ]);
        await view.rerender({
            modelValue: { ...initial.modelValue, category: 'api-design' },
        });
        await user.click(category);
        await user.click(
            screen.getByRole('option', { name: 'All categories' }),
        );
        expect(view.emitted()['update:modelValue']).toContainEqual([
            { ...initial.modelValue, category: null },
        ]);
    });

    it.each([null, 'tip', 'topic'] as const)(
        'disables category only for tips with kind %s',
        (kind) => {
            const initial = props();
            initial.modelValue.type = 'learning';
            initial.modelValue.kind = kind;
            render(SearchFilters, { props: initial });
            const category = screen.getByRole('combobox', { name: 'Category' });
            if (kind === 'tip') expect(category).toBeDisabled();
            else expect(category).toBeEnabled();
            expect(screen.getByRole('group', { name: 'Tags' })).toBeVisible();
            expect(screen.getByRole('button', { name: 'From' })).toBeVisible();
            expect(screen.getByRole('button', { name: 'To' })).toBeVisible();
        },
    );

    it('replaces learning filters with source when returning to news', async () => {
        const initial = props();
        initial.modelValue.type = 'learning';
        const view = render(SearchFilters, { props: initial });
        expect(screen.getByRole('combobox', { name: 'Kind' })).toBeVisible();
        expect(
            screen.getByRole('combobox', { name: 'Category' }),
        ).toBeVisible();
        await view.rerender({
            modelValue: { ...initial.modelValue, type: 'news' },
        });
        expect(screen.getByRole('combobox', { name: 'Source' })).toBeVisible();
        expect(
            screen.queryByRole('combobox', { name: 'Kind' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('combobox', { name: 'Category' }),
        ).not.toBeInTheDocument();
    });

    it('toggles any selected tag while preserving the remaining filters', async () => {
        const initial = props();
        const view = render(SearchFilters, { props: initial });
        const user = userEvent.setup();
        expect(screen.getByRole('button', { name: 'PHP' })).toHaveAttribute(
            'aria-pressed',
            'true',
        );
        expect(screen.getByRole('button', { name: 'Vue' })).toHaveAttribute(
            'aria-pressed',
            'false',
        );
        await user.click(screen.getByRole('button', { name: 'Vue' }));
        expect(view.emitted()['update:modelValue']).toEqual([
            [{ ...initial.modelValue, tags: ['php', 'vue'] }],
        ]);
        await view.rerender({
            modelValue: { ...initial.modelValue, tags: ['php', 'vue'] },
        });
        expect(screen.getByRole('button', { name: 'Vue' })).toHaveAttribute(
            'aria-pressed',
            'true',
        );
        await user.click(screen.getByRole('button', { name: 'PHP' }));
        expect(view.emitted()['update:modelValue']).toContainEqual([
            { ...initial.modelValue, tags: ['vue'] },
        ]);
    });

    it('offers All sources first and emits source selection and clearing', async () => {
        const initial = props();
        const view = render(SearchFilters, { props: initial });
        const user = userEvent.setup();
        const select = screen.getByRole('combobox', { name: 'Source' });
        await user.click(select);
        expect(screen.getAllByRole('option')[0]).toHaveTextContent(
            'All sources',
        );
        await user.click(screen.getByRole('option', { name: 'Compiler News' }));
        expect(view.emitted()['update:modelValue']).toContainEqual([
            { ...initial.modelValue, source: 'compiler-news' },
        ]);
        await view.rerender({
            modelValue: { ...initial.modelValue, source: 'compiler-news' },
        });
        await user.click(select);
        await user.click(screen.getByRole('option', { name: 'All sources' }));
        expect(view.emitted()['update:modelValue']).toContainEqual([
            { ...initial.modelValue, source: null },
        ]);
    });

    it.each(['from', 'to'] as const)(
        'emits a %s date and preserves the other values',
        async (field) => {
            const initial = props();
            initial.modelValue[field] = '2026-10-20';
            const view = render(SearchFilters, { props: initial });
            const user = userEvent.setup();
            await user.click(
                screen.getByRole('button', {
                    name: field === 'from' ? 'From' : 'To',
                }),
            );
            await screen.findByRole('application');
            await user.click(
                screen.getByRole('button', {
                    name: /\b(?:28 October|October 28)\b/,
                }),
            );
            expect(view.emitted()['update:modelValue']).toContainEqual([
                { ...initial.modelValue, [field]: '2026-10-28' },
            ]);
        },
    );

    it('shows server date errors beside the corresponding inputs', () => {
        const initial = props();
        initial.errors = {
            from: 'Invalid start date.',
            to: 'End date must follow start date.',
        };
        render(SearchFilters, { props: initial });
        for (const [label, message] of [
            ['From', initial.errors.from],
            ['To', initial.errors.to],
        ]) {
            const field = screen.getByLabelText(label);
            expect(
                within(field.parentElement!).getByText(message),
            ).toBeVisible();
        }
    });
});

it.each([
    ['news', 'tip', false],
    ['learning', 'tip', true],
    ['learning', 'topic', false],
    ['learning', null, false],
] as const)(
    'explains categories for type=%s kind=%s',
    (type, kind, visible) => {
        const initial = props();
        initial.modelValue.type = type;
        initial.modelValue.kind = kind;
        render(SearchFilters, { props: initial });
        const message = screen.queryByText('Categories apply to topics only.');
        if (visible) expect(message).toBeVisible();
        else expect(message).not.toBeInTheDocument();
    },
);
