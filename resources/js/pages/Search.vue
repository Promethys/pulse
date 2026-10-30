<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Search as SearchIcon, SlidersHorizontal } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import EmptyState from '@/components/pulse/EmptyState.vue';
import NewsItemRow from '@/components/pulse/NewsItemRow.vue';
import PageHeader from '@/components/pulse/PageHeader.vue';
import PaginationLinks from '@/components/pulse/PaginationLinks.vue';
import SearchFilters from '@/components/pulse/SearchFilters.vue';
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
import type { SearchFilterValues, SearchProps } from '@/types';

const props = defineProps<SearchProps>();
const page = usePage();
const filters = ref<SearchFilterValues>({
    ...props.filters,
    tags: [...props.filters.tags],
});
const sheetOpen = ref(false);
const errors = computed(() => page.props.errors);
const active = computed(
    () =>
        filters.value.tags.length +
        (filters.value.source ? 1 : 0) +
        (filters.value.from || filters.value.to ? 1 : 0),
);
const count = computed(
    () =>
        `${props.pagination.total} ${props.pagination.total === 1 ? 'result' : 'results'}`,
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
            ...(value.q ? { q: value.q } : {}),
            ...(value.tags.length ? { tags: value.tags } : {}),
            ...(value.source ? { source: value.source } : {}),
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

function updateFilters(value: SearchFilterValues) {
    filters.value = value;
    search();
}

function reset() {
    updateFilters({
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
        <PageHeader title="Search" />
        <div class="md:grid md:grid-cols-[1fr_15rem] md:gap-8">
            <div class="min-w-0">
                <div
                    class="sticky top-0 z-10 -mx-4 flex gap-2 bg-background/95 px-4 py-2 backdrop-blur md:static md:mx-0 md:px-0"
                >
                    <div class="relative flex-1">
                        <SearchIcon
                            class="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            type="search"
                            role="searchbox"
                            aria-label="Search titles and summaries"
                            placeholder="Search titles and summaries…"
                            class="h-11 rounded-full pl-10"
                            :model-value="filters.q ?? ''"
                            :aria-invalid="!!errors.q"
                            :aria-describedby="
                                errors.q ? 'search-error' : undefined
                            "
                            @update:model-value="updateQuery"
                        />
                    </div>
                    <Sheet v-model:open="sheetOpen">
                        <SheetTrigger as-child>
                            <Button
                                variant="outline"
                                class="relative h-11 rounded-full md:hidden"
                                aria-label="Filters"
                            >
                                <SlidersHorizontal aria-hidden="true" />
                                <span
                                    v-if="active > 0"
                                    class="absolute -top-1 -right-1 flex size-5 items-center justify-center rounded-full bg-primary text-[10px] font-semibold text-primary-foreground"
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
                                    class="flex-1"
                                    @click="reset"
                                    >Reset</Button
                                >
                                <SheetClose as-child>
                                    <Button class="flex-1"
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
                <div class="mt-3 flex items-center justify-between gap-2">
                    <p class="text-xs text-muted-foreground">{{ count }}</p>
                    <button
                        v-if="active > 0"
                        type="button"
                        class="text-xs font-medium text-primary-strong underline-offset-2 hover:underline"
                        @click="reset"
                    >
                        Clear filters
                    </button>
                </div>
                <NewsItemRow
                    v-for="item in results"
                    :key="item.id"
                    :item="item"
                    show-date
                />
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
