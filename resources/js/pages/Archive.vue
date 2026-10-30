<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ArchiveRow from '@/components/pulse/ArchiveRow.vue';
import EmptyState from '@/components/pulse/EmptyState.vue';
import PageHeader from '@/components/pulse/PageHeader.vue';
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
        />
        <template v-else>
            <ul
                class="divide-y divide-border rounded-2xl border border-border bg-card"
            >
                <li v-for="day in days" :key="day.date">
                    <ArchiveRow :day="day" :today="today" />
                </li>
            </ul>
            <nav
                v-if="pagination.last_page > 1"
                aria-label="Archive pagination"
                class="mt-6 flex items-center justify-between gap-4 text-sm"
            >
                <Link
                    v-if="pagination.prev_url"
                    :href="pagination.prev_url"
                    class="text-muted-foreground hover:underline"
                    >Previous</Link
                >
                <span v-else class="text-muted-foreground" aria-disabled="true"
                    >Previous</span
                >
                <span
                    >Page {{ pagination.current_page }} of
                    {{ pagination.last_page }}</span
                >
                <Link
                    v-if="pagination.next_url"
                    :href="pagination.next_url"
                    class="text-muted-foreground hover:underline"
                    >Next</Link
                >
                <span v-else class="text-muted-foreground" aria-disabled="true"
                    >Next</span
                >
            </nav>
        </template>
    </div>
</template>
