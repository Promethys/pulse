<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Tag as TagIcon } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/pulse/EmptyState.vue';
import HelpTip from '@/components/pulse/HelpTip.vue';
import NewsItemRow from '@/components/pulse/NewsItemRow.vue';
import PageHeader from '@/components/pulse/PageHeader.vue';
import PaginationLinks from '@/components/pulse/PaginationLinks.vue';
import Sparkline from '@/components/pulse/Sparkline.vue';
import { dashboard } from '@/routes';
import type { TagProps } from '@/types';

const props = defineProps<TagProps>();
const subtitle = computed(() =>
    props.count === 0
        ? 'No items yet'
        : `${props.count} ${props.count === 1 ? 'item' : 'items'} · newest first`,
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
                        class="flex items-center justify-end gap-2 text-[10px] tracking-wider text-muted-foreground uppercase"
                    >
                        per week · 12w
                        <HelpTip
                            label="Weekly stories"
                            text="News stories in your digests with this tag, grouped into weeks over the last 12 weeks."
                        />
                    </div>
                </div>
            </template>
        </PageHeader>
        <EmptyState
            v-if="count === 0"
            title="Nothing here yet"
            description="No saved news uses this tag yet."
        >
            <template #actions>
                <p class="text-sm text-muted-foreground">
                    Browse
                    <Link
                        :href="dashboard()"
                        class="underline underline-offset-4"
                        >Today</Link
                    >
                    or
                    <Link href="/search" class="underline underline-offset-4"
                        >Search</Link
                    >.
                </p>
            </template>
            <template #icon>
                <TagIcon class="size-5" aria-hidden="true" />
            </template>
        </EmptyState>
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
