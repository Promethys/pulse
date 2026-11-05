<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Search as SearchIcon, SlidersHorizontal, X } from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { computed, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import EmptyState from '@/components/pulse/EmptyState.vue';
import NewsItemRow from '@/components/pulse/NewsItemRow.vue';
import PageHeader from '@/components/pulse/PageHeader.vue';
import PaginationLinks from '@/components/pulse/PaginationLinks.vue';
import SearchFilters from '@/components/pulse/SearchFilters.vue';
import TipCard from '@/components/pulse/TipCard.vue';
import TopicCard from '@/components/pulse/TopicCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDate } from '@/lib/dates';
import type {
    LearningEntry,
    NewsItem,
    SearchFilterValues,
    SearchProps,
} from '@/types';

const props = defineProps<SearchProps>();
const page = usePage();
const filters = ref<SearchFilterValues>({
    ...props.filters,
    tags: [...props.filters.tags],
});
const sheetOpen = ref(false);
const searchBox = useTemplateRef<HTMLElement>('searchBox');
const errors = computed(() => page.props.errors);
const active = computed(
    () =>
        filters.value.tags.length +
        (filters.value.source ? 1 : 0) +
        (filters.value.kind ? 1 : 0) +
        (filters.value.category ? 1 : 0) +
        (filters.value.from || filters.value.to ? 1 : 0),
);
const count = computed(
    () =>
        `${props.pagination.total} ${props.pagination.total === 1 ? 'result' : 'results'}`,
);
const newsResults = computed(() =>
    props.type === 'news' ? (props.results as NewsItem[]) : [],
);
const learningResults = computed(() =>
    props.type === 'learning' ? (props.results as LearningEntry[]) : [],
);
let debounce: ReturnType<typeof setTimeout> | undefined;

watch(
    () => props.filters,
    (value) => {
        clearTimeout(debounce);
        filters.value = { ...value, tags: [...value.tags] };
    },
);

function search() {
    clearTimeout(debounce);
    const value = filters.value;
    router.get(
        '/search',
        {
            ...(value.type === 'learning' ? { type: value.type } : {}),
            ...(value.type === 'learning' && value.kind
                ? { kind: value.kind }
                : {}),
            ...(value.type === 'learning' && value.category
                ? { category: value.category }
                : {}),
            ...(value.q ? { q: value.q } : {}),
            ...(value.tags.length ? { tags: value.tags } : {}),
            ...(value.type === 'news' && value.source
                ? { source: value.source }
                : {}),
            ...(value.from ? { from: value.from } : {}),
            ...(value.to ? { to: value.to } : {}),
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function updateQuery(value: string | number) {
    filters.value.q = String(value) || null;
    clearTimeout(debounce);
    debounce = setTimeout(search, 300);
}

function clearQuery() {
    filters.value.q = null;
    search();
    searchBox.value?.querySelector('input')?.focus();
}

function updateFilters(value: SearchFilterValues) {
    filters.value = value;
    search();
}

function switchType(value: AcceptableValue) {
    if (value === filters.value.type) return;

    if (value !== 'news' && value !== 'learning') return;
    updateFilters({
        ...filters.value,
        type: value,
        source: null,
        kind: null,
        category: null,
    });
}

function reset() {
    updateFilters({
        type: filters.value.type,
        kind: null,
        category: null,
        q: filters.value.q,
        tags: [],
        source: null,
        from: null,
        to: null,
    });
}

onBeforeUnmount(() => clearTimeout(debounce));
</script>

<template>
    <Head title="Search" />
    <div>
        <PageHeader
            title="Search"
            subtitle="Search stories and learning saved in your digests."
        />
        <div class="md:grid md:grid-cols-[1fr_15rem] md:gap-8">
            <div class="min-w-0">
                <div
                    class="sticky top-0 z-10 -mx-4 flex gap-2 bg-background/95 px-4 py-2 backdrop-blur md:static md:mx-0 md:px-0"
                >
                    <div ref="searchBox" class="relative flex-1">
                        <SearchIcon
                            class="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            type="search"
                            role="searchbox"
                            :aria-label="
                                filters.type === 'learning'
                                    ? 'Search titles and bodies'
                                    : 'Search titles and summaries'
                            "
                            :placeholder="
                                filters.type === 'learning'
                                    ? 'Search titles and bodies…'
                                    : 'Search titles and summaries…'
                            "
                            class="h-11 rounded-full pr-10 pl-10 [&::-webkit-search-cancel-button]:appearance-none"
                            :model-value="filters.q ?? ''"
                            :aria-invalid="!!errors.q"
                            :aria-describedby="
                                errors.q ? 'search-error' : undefined
                            "
                            @update:model-value="updateQuery"
                        />
                        <button
                            v-if="filters.q"
                            type="button"
                            aria-label="Clear search"
                            class="absolute top-1/2 right-3 flex size-6 -translate-y-1/2 items-center justify-center rounded-full text-muted-foreground focus-ring hover:bg-muted hover:text-foreground"
                            @click="clearQuery"
                        >
                            <X class="size-4" aria-hidden="true" />
                        </button>
                    </div>
                    <Sheet v-model:open="sheetOpen">
                        <SheetTrigger as-child>
                            <Button
                                variant="outline"
                                class="relative h-11 rounded-full focus-ring hover:bg-accent hover:text-accent-foreground active:bg-accent active:text-accent-foreground md:hidden dark:active:bg-accent"
                                aria-label="Filters"
                            >
                                <SlidersHorizontal aria-hidden="true" />
                                <span
                                    v-if="active > 0"
                                    class="absolute -top-1 -right-1 flex size-5 items-center justify-center rounded-full bg-primary-strong text-[10px] font-semibold text-background"
                                    >{{ active }}</span
                                >
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="bottom"
                            class="max-h-[90dvh] overflow-y-auto rounded-t-2xl"
                            :aria-describedby="undefined"
                        >
                            <SheetHeader>
                                <SheetTitle>Filters</SheetTitle>
                            </SheetHeader>
                            <div class="px-4">
                                <SearchFilters
                                    :model-value="filters"
                                    :options="options"
                                    :errors="errors"
                                    @update:model-value="updateFilters"
                                />
                            </div>
                            <SheetFooter class="flex-row">
                                <Button
                                    variant="outline"
                                    class="flex-1 focus-ring hover:bg-accent hover:text-accent-foreground active:bg-accent active:text-accent-foreground dark:active:bg-accent"
                                    @click="reset"
                                    >Reset</Button
                                >
                                <SheetClose as-child>
                                    <Button class="flex-1 focus-ring"
                                        >Show {{ count }}</Button
                                    >
                                </SheetClose>
                            </SheetFooter>
                        </SheetContent>
                    </Sheet>
                </div>
                <InputError
                    id="search-error"
                    :message="errors.q"
                    class="mt-2"
                />
                <Tabs
                    :model-value="filters.type"
                    @update:model-value="switchType"
                >
                    <TabsList class="mt-3 w-full md:w-auto">
                        <TabsTrigger
                            value="news"
                            class="focus-ring hover:bg-accent hover:text-accent-foreground active:bg-accent active:text-accent-foreground data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:hover:bg-primary data-[state=active]:hover:text-primary-foreground data-[state=active]:focus-visible:ring-ring! data-[state=active]:active:bg-primary data-[state=active]:active:text-primary-foreground"
                            >News {{ counts.news }}</TabsTrigger
                        >
                        <TabsTrigger
                            value="learning"
                            class="focus-ring hover:bg-accent hover:text-accent-foreground active:bg-accent active:text-accent-foreground data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:hover:bg-primary data-[state=active]:hover:text-primary-foreground data-[state=active]:focus-visible:ring-ring! data-[state=active]:active:bg-primary data-[state=active]:active:text-primary-foreground"
                            >Learning {{ counts.learning }}</TabsTrigger
                        >
                    </TabsList>
                </Tabs>
                <div class="mt-3 flex items-center justify-between gap-2">
                    <p class="text-xs text-muted-foreground">{{ count }}</p>
                    <button
                        v-if="active > 0"
                        type="button"
                        class="text-xs font-medium text-primary-strong underline-offset-2 focus-ring hover:underline active:underline"
                        @click="reset"
                    >
                        Clear filters
                    </button>
                </div>
                <NewsItemRow
                    v-for="item in newsResults"
                    :key="item.id"
                    :item="item"
                    show-date
                />
                <div v-if="learningResults.length" class="mt-4 space-y-6">
                    <section v-for="entry in learningResults" :key="entry.id">
                        <h2
                            class="mb-3 text-sm font-semibold text-muted-foreground"
                        >
                            {{ formatDate(entry.date) }}
                        </h2>
                        <TipCard v-if="entry.kind === 'tip'" :tip="entry" />
                        <TopicCard v-else :topic="entry" />
                    </section>
                </div>
                <div v-if="results.length === 0" class="mt-4">
                    <EmptyState
                        title="No matches"
                        description="Try fewer filters or a broader word."
                    >
                        <template #icon>
                            <SearchIcon class="size-5" aria-hidden="true" />
                        </template>
                    </EmptyState>
                </div>
                <PaginationLinks
                    :pagination="pagination"
                    label="Search pagination"
                />
            </div>
            <aside class="hidden md:block" aria-label="Search filters">
                <div class="sticky top-10">
                    <SearchFilters
                        :model-value="filters"
                        :options="options"
                        :errors="errors"
                        @update:model-value="updateFilters"
                    />
                </div>
            </aside>
        </div>
    </div>
</template>
