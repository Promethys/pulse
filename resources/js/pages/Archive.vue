<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ArchiveRow from '@/components/pulse/ArchiveRow.vue';
import EmptyState from '@/components/pulse/EmptyState.vue';
import PageHeader from '@/components/pulse/PageHeader.vue';
import PaginationLinks from '@/components/pulse/PaginationLinks.vue';
import type { ArchiveProps } from '@/types';

const props = defineProps<ArchiveProps>();
const subtitle = computed(
    () =>
        `${props.pagination.total} ${props.pagination.total === 1 ? 'digest' : 'digests'}`,
);
</script>

<template>
    <Head title="Archive" />
    <div>
        <PageHeader title="Archive" :subtitle="subtitle" />
        <EmptyState
            v-if="pagination.total === 0"
            title="No digests yet"
            description="Your past digests will appear here."
        >
            <template #actions>
                <p class="text-sm text-muted-foreground">
                    Visit
                    <Link
                        href="/"
                        class="underline underline-offset-4 focus-ring hover:decoration-2 active:decoration-2"
                        >Today</Link
                    >
                    to check your next digest.
                </p>
            </template>
        </EmptyState>
        <template v-else>
            <ul
                class="divide-y divide-border rounded-2xl border border-border bg-card"
            >
                <li
                    v-for="day in days"
                    :key="day.date"
                    class="first:rounded-t-2xl last:rounded-b-2xl"
                >
                    <ArchiveRow :day="day" :today="today" />
                </li>
            </ul>
            <PaginationLinks
                :pagination="pagination"
                label="Archive pagination"
            />
        </template>
    </div>
</template>
