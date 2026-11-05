<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Lightbulb } from '@lucide/vue';
import { ref } from 'vue';
import ClampedProse from '@/components/pulse/ClampedProse.vue';
import TagChip from '@/components/pulse/TagChip.vue';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { index, show } from '@/routes/learning';
import type { LearningPost } from '@/types';

defineProps<{ tip: LearningPost }>();

const expanded = ref(false);
</script>

<template>
    <Card
        class="gap-3 border-primary-strong/30 bg-primary/40 shadow-none dark:bg-primary/10"
    >
        <CardHeader class="gap-1">
            <div
                class="flex items-center gap-1.5 text-xs font-semibold tracking-wider text-primary-strong uppercase"
            >
                <Lightbulb class="size-3.5" aria-hidden="true" />
                Tip
            </div>
            <h3
                :title="tip.title"
                :class="[
                    'text-lg leading-snug font-semibold',
                    { 'line-clamp-2': !expanded },
                ]"
            >
                <Link
                    :href="show.url(tip.id)"
                    class="focus-ring hover:underline active:underline"
                    >{{ tip.title }}</Link
                >
            </h3>
        </CardHeader>
        <CardContent class="space-y-3 text-sm leading-relaxed">
            <ClampedProse v-model:expanded="expanded" :html="tip.body_html" />
            <div
                v-if="tip.tags.length"
                class="flex flex-wrap items-center gap-1"
            >
                <TagChip
                    v-for="tag in tip.tags"
                    :key="tag.id"
                    :slug="tag.slug"
                    :name="tag.name"
                    :href="index.url({ query: { tag: tag.slug } })"
                />
            </div>
        </CardContent>
    </Card>
</template>
