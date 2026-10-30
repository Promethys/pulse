<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import TagChip from '@/components/pulse/TagChip.vue';
import { dateParts } from '@/lib/dates';
import { home } from '@/routes';
import { show } from '@/routes/days';
import type { ArchiveDay } from '@/types';

const props = defineProps<{ day: ArchiveDay; today: string }>();
const parts = computed(() => dateParts(props.day.date));
const weekday = computed(() =>
    parts.value.year === dateParts(props.today).year
        ? parts.value.weekday
        : `${parts.value.weekday} ${parts.value.year}`,
);
const href = computed(() =>
    props.day.date === props.today ? home.url() : show.url(props.day.date),
);
const tagNames = computed(() =>
    props.day.tags.map((tag) => tag.name).join(', '),
);
const label = computed(() => {
    if (props.day.item_count > 0) {
        return `${props.day.item_count} ${props.day.item_count === 1 ? 'item' : 'items'}`;
    }

    switch (props.day.news_status) {
        case 'generated':
            return 'Quiet night';
        case 'pending':
            return 'In progress';
        case 'failed':
            return 'News failed';
        default:
            return 'No news';
    }
});
</script>

<template>
    <Link :href="href" class="flex items-center gap-4 p-4 hover:bg-muted/60">
        <div
            class="flex w-12 shrink-0 flex-col items-center rounded-lg bg-primary py-1.5 text-primary-foreground"
        >
            <span class="text-[10px] font-semibold uppercase">{{
                parts.month
            }}</span>
            <span class="font-display text-xl leading-none font-semibold">{{
                parts.day
            }}</span>
        </div>
        <div class="min-w-0 flex-1">
            <div class="text-sm font-semibold">
                {{ weekday }}
                <span class="font-normal text-muted-foreground"
                    >· {{ label }}</span
                >
            </div>
            <div
                v-if="day.tags.length"
                :title="tagNames"
                class="mt-1.5 flex max-h-7 flex-wrap gap-1 overflow-hidden"
            >
                <TagChip
                    v-for="tag in day.tags"
                    :key="tag.id"
                    :slug="tag.slug"
                    :name="tag.name"
                />
            </div>
        </div>
        <ChevronRight class="size-4 text-muted-foreground" aria-hidden="true" />
    </Link>
</template>
