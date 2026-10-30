<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import EmptyState from '@/components/pulse/EmptyState.vue';
import NewsItemRow from '@/components/pulse/NewsItemRow.vue';
import PageHeader from '@/components/pulse/PageHeader.vue';
import PaginationLinks from '@/components/pulse/PaginationLinks.vue';
import Sparkline from '@/components/pulse/Sparkline.vue';
import type { TagProps } from '@/types';

const props = defineProps<TagProps>();
const subtitle = computed(
    () =>
        `${props.count} ${props.count === 1 ? 'item' : 'items'} · newest first`,
);
</script>

<template>
    <Head :title="tag.name" />
    <div>
        <PageHeader :title="tag.name" :subtitle="subtitle">
            <template v-if="count > 0" #actions>
                <div class="text-right">
                    <Sparkline :data="weeks" />
                    <div
                        class="text-[10px] tracking-wider text-muted-foreground uppercase"
                    >
                        per week · 12w
                    </div>
                </div>
            </template>
        </PageHeader>
        <EmptyState
            v-if="count === 0"
            title="Nothing here yet"
            description="Items with this tag will appear here."
        />
        <template v-else>
            <NewsItemRow
                v-for="item in items"
                :key="item.id"
                :item="item"
                show-date
            />
            <PaginationLinks :pagination="pagination" label="Tag pagination" />
        </template>
    </div>
</template>
