<script setup lang="ts">
import { ExternalLink } from '@lucide/vue';
import TagChip from '@/components/pulse/TagChip.vue';
import type { NewsItem } from '@/types';

defineProps<{ item: NewsItem }>();
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
        <div
            class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1.5 text-xs text-muted-foreground"
        >
            <span v-if="item.source" class="font-medium text-foreground/80">{{
                item.source
            }}</span>
            <span v-if="item.tags.length" class="flex gap-1">
                <TagChip
                    v-for="tag in item.tags"
                    :key="tag.id"
                    :slug="tag.slug"
                    :name="tag.name"
                />
            </span>
            <div class="ml-auto flex items-center">
                <a
                    :href="item.url"
                    target="_blank"
                    rel="noreferrer"
                    aria-label="Open source"
                    class="inline-flex size-8 items-center justify-center rounded-full hover:bg-muted hover:text-foreground"
                >
                    <ExternalLink class="size-3.5" aria-hidden="true" />
                </a>
            </div>
        </div>
    </article>
</template>
