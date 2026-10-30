<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, ExternalLink } from '@lucide/vue';
import TagChip from '@/components/pulse/TagChip.vue';
import { Card, CardContent } from '@/components/ui/card';
import { formatDate } from '@/lib/dates';
import { index } from '@/routes/learning';
import type { LearningShowProps } from '@/types';

defineProps<LearningShowProps>();
</script>

<template>
    <Head :title="entry.title" />
    <div>
        <Link
            :href="index.url()"
            class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
        >
            <ChevronLeft class="size-4" aria-hidden="true" />
            Learning
        </Link>
        <p
            class="mt-4 text-xs font-semibold tracking-wider text-primary-strong uppercase"
        >
            {{ entry.kind === 'tip' ? 'Tip' : 'Topic' }} of the day ·
            {{ formatDate(entry.date) }}
        </p>
        <h1 class="mt-1 mb-6 text-3xl font-semibold md:text-4xl">
            {{ entry.title }}
        </h1>
        <Card class="gap-3 shadow-none">
            <CardContent class="space-y-3 text-sm leading-relaxed">
                <div class="pulse-prose" v-html="entry.body_html" />
                <div v-if="entry.links.length">
                    <h2
                        class="mb-1 font-sans text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Suggested reading
                    </h2>
                    <ul class="space-y-1">
                        <li v-for="link in entry.links" :key="link.url">
                            <a
                                :href="link.url"
                                target="_blank"
                                rel="noreferrer"
                                class="inline-flex items-center gap-1 underline-offset-2 hover:underline"
                            >
                                {{ link.title }}
                                <ExternalLink
                                    class="size-3"
                                    aria-hidden="true"
                                />
                            </a>
                        </li>
                    </ul>
                </div>
                <div
                    v-if="entry.tags.length"
                    class="flex flex-wrap items-center gap-1"
                >
                    <TagChip
                        v-for="tag in entry.tags"
                        :key="tag.id"
                        :slug="tag.slug"
                        :name="tag.name"
                        :href="index.url({ query: { tag: tag.slug } })"
                    />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
