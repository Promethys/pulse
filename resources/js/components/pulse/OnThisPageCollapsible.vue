<script setup lang="ts">
import type { DocsHeading } from '@/components/pulse/OnThisPage.vue';

defineProps<{ items: DocsHeading[] }>();
</script>

<template>
    <details
        v-if="items.length"
        class="mb-6 rounded-xl border border-border bg-card p-4 lg:hidden"
    >
        <summary class="cursor-pointer text-sm font-semibold">
            On this page
        </summary>
        <ul class="mt-2 space-y-1.5">
            <li
                v-for="item in items"
                :key="item.id"
                :class="item.level === 3 ? 'pl-3' : ''"
            >
                <a
                    :href="`#${item.id}`"
                    class="text-sm text-muted-foreground hover:text-foreground hover:underline"
                    >{{ item.text }}</a
                >
            </li>
        </ul>
    </details>
</template>
