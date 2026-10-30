import { render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import SearchFilters from '@/components/pulse/SearchFilters.vue';

function props() {
    return {
        modelValue: {
            q: null as string | null,
            tags: ['php'],
            source: null as string | null,
            from: null as string | null,
            to: null as string | null,
        },
        options: {
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
