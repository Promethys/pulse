<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Pagination } from '@/types';

withDefaults(defineProps<{ pagination: Pagination; label?: string }>(), {
    label: 'Pagination',
});
</script>

<template>
    <nav
        v-if="pagination.last_page > 1"
        :aria-label="label"
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
