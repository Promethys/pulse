<script setup lang="ts">
import { ExternalLink } from '@lucide/vue';
import { computed } from 'vue';
import TagChip from '@/components/pulse/TagChip.vue';
import { dateParts } from '@/lib/dates';
import type { NewsItem } from '@/types';

const props = defineProps<{ item: NewsItem; showDate?: boolean }>();
const date = computed(() => dateParts(props.item.date));
</script>

<template>
    <article class="group border-b border-border py-4 last:border-0">
        <a :href="item.url" target="_blank" rel="noreferrer" class="block">
            <h3
                :title="item.title"
                class="line-clamp-2 font-sans text-[15px] leading-snug font-semibold group-hover:underline"
            >
                {{ item.title }}
            </h3>
        </a>
        <p
            v-if="item.summary"
            class="mt-1 text-sm leading-relaxed text-muted-foreground"
        >
            {{ item.summary }}
        </p>
        <div class="mt-2 flex items-start gap-2 text-xs text-muted-foreground">
            <div
                class="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1.5"
            >
                <span
                    v-if="item.source"
                    class="font-medium text-foreground/80"
                    >{{ item.source }}</span
                >
                <template v-if="showDate">
                    <span aria-hidden="true">·</span>
                    <span>{{ date.day }} {{ date.month }}</span>
                </template>
                <span v-if="item.tags.length" class="flex flex-wrap gap-1">
                    <TagChip
                        v-for="tag in item.tags"
                        :key="tag.id"
                        :slug="tag.slug"
                        :name="tag.name"
                    />
                </span>
            </div>
            <a
                :href="item.url"
                target="_blank"
                rel="noreferrer"
                aria-label="Open source"
                class="inline-flex size-8 shrink-0 items-center justify-center rounded-full hover:bg-muted hover:text-foreground"
            >
                <ExternalLink class="size-3.5" aria-hidden="true" />
            </a>
        </div>
    </article>
</template>
