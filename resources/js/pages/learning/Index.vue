<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import EmptyState from '@/components/pulse/EmptyState.vue';
import PageHeader from '@/components/pulse/PageHeader.vue';
import PaginationLinks from '@/components/pulse/PaginationLinks.vue';
import TagChip from '@/components/pulse/TagChip.vue';
import TipCard from '@/components/pulse/TipCard.vue';
import TopicCard from '@/components/pulse/TopicCard.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatDate } from '@/lib/dates';
import { index } from '@/routes/learning';
import type {
    LearningEntry,
    LearningFilterValues,
    LearningProps,
} from '@/types';

const props = defineProps<LearningProps>();
const filters = ref<LearningFilterValues>({ ...props.filters });
const active = computed(
    () => !!(filters.value.kind || filters.value.category || filters.value.tag),
);
const count = computed(
    () =>
        `${props.pagination.total} ${props.pagination.total === 1 ? 'entry' : 'entries'}`,
);
const groups = computed(() => {
    const dates = new Map<string, LearningEntry[]>();
    for (const entry of props.entries) {
        const entries = dates.get(entry.date) ?? [];
        entries.push(entry);
        dates.set(entry.date, entries);
    }
    return Array.from(dates, ([date, entries]) => ({ date, entries }));
});

watch(
    () => props.filters,
    (value) => {
        filters.value = { ...value };
    },
);

function filter() {
    const value = filters.value;
    router.get(
        index.url(),
        {
            ...(value.kind ? { kind: value.kind } : {}),
            ...(value.category ? { category: value.category } : {}),
            ...(value.tag ? { tag: value.tag } : {}),
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function updateKind(value: AcceptableValue | AcceptableValue[]) {
    if (value !== 'all' && value !== 'tip' && value !== 'topic') return;
    filters.value.kind = value === 'all' ? null : value;
    filter();
}

function updateCategory(value: AcceptableValue) {
    filters.value.category =
        typeof value === 'string' && value !== 'all' ? value : null;
    filter();
}

function toggleTag(slug: string) {
    filters.value.tag = filters.value.tag === slug ? null : slug;
    filter();
}

function reset() {
    filters.value = { kind: null, category: null, tag: null };
    filter();
}
</script>

<template>
    <Head title="Learning" />
    <div>
        <PageHeader title="Learning" subtitle="Tips and topics, day by day" />
        <div class="mb-6 space-y-3">
            <div class="flex flex-wrap gap-2">
                <ToggleGroup
                    type="single"
                    variant="outline"
                    aria-label="Kind"
                    :model-value="filters.kind ?? 'all'"
                    @update:model-value="updateKind"
                >
                    <ToggleGroupItem value="all">All</ToggleGroupItem>
                    <ToggleGroupItem value="tip">Tips</ToggleGroupItem>
                    <ToggleGroupItem value="topic">Topics</ToggleGroupItem>
                </ToggleGroup>
                <Select
                    :model-value="filters.category ?? 'all'"
                    @update:model-value="updateCategory"
                >
                    <SelectTrigger aria-label="Category" class="w-48">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All categories</SelectItem>
                        <SelectItem
                            v-for="category in options.categories"
                            :key="category"
                            :value="category"
                            >{{ category }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
            <div
                v-if="options.tags.length"
                role="group"
                aria-label="Tags"
                class="flex flex-wrap gap-1.5"
            >
                <TagChip
                    v-for="tag in options.tags"
                    :key="tag.slug"
                    :slug="tag.slug"
                    :name="tag.name"
                    :active="filters.tag === tag.slug"
                    @toggle="toggleTag(tag.slug)"
                />
            </div>
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs text-muted-foreground">{{ count }}</p>
                <button
                    v-if="active"
                    type="button"
                    class="text-xs font-medium text-primary-strong underline-offset-2 hover:underline"
                    @click="reset"
                >
                    Clear filters
                </button>
            </div>
        </div>
        <div v-if="entries.length" class="space-y-8">
            <section v-for="group in groups" :key="group.date">
                <h2 class="mb-3 text-sm font-semibold text-muted-foreground">
                    {{ formatDate(group.date) }}
                </h2>
                <div class="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                    <template v-for="entry in group.entries" :key="entry.id">
                        <TipCard v-if="entry.kind === 'tip'" :tip="entry" />
                        <TopicCard v-else :topic="entry" />
                    </template>
                </div>
            </section>
        </div>
        <EmptyState
            v-else-if="active"
            title="No matches"
            description="Try fewer filters."
        />
        <EmptyState
            v-else
            title="No tips yet"
            description="Your daily tips and topics will appear here."
        />
        <PaginationLinks :pagination="pagination" />
    </div>
</template>
