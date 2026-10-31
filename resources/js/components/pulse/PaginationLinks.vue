<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button, buttonVariants } from '@/components/ui/button';
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
        <Button v-if="pagination.prev_url" variant="outline" size="sm" as-child>
            <Link :href="pagination.prev_url">Previous</Link>
        </Button>
        <span
            v-else
            :class="buttonVariants({ variant: 'outline', size: 'sm' })"
            class="pointer-events-none opacity-50"
            aria-disabled="true"
            >Previous</span
        >
        <span class="text-muted-foreground"
            >Page {{ pagination.current_page }} of
            {{ pagination.last_page }}</span
        >
        <Button v-if="pagination.next_url" variant="outline" size="sm" as-child>
            <Link :href="pagination.next_url">Next</Link>
        </Button>
        <span
            v-else
            :class="buttonVariants({ variant: 'outline', size: 'sm' })"
            class="pointer-events-none opacity-50"
            aria-disabled="true"
            >Next</span
        >
    </nav>
</template>
