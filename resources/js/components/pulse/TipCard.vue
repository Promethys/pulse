<script setup lang="ts">
import { Lightbulb } from '@lucide/vue';
import ClampedProse from '@/components/pulse/ClampedProse.vue';
import TagChip from '@/components/pulse/TagChip.vue';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import type { LearningPost } from '@/types';

defineProps<{ tip: LearningPost }>();
</script>

<template>
    <Card class="gap-3 border-primary-strong/30 bg-primary/40 shadow-none">
        <CardHeader class="gap-1">
            <div
                class="flex items-center gap-1.5 text-xs font-semibold tracking-wider text-primary-strong uppercase"
            >
                <Lightbulb class="size-3.5" aria-hidden="true" />
                Tip
            </div>
            <h3
                :title="tip.title"
                class="line-clamp-1 text-lg leading-snug font-semibold"
            >
                {{ tip.title }}
            </h3>
        </CardHeader>
        <CardContent class="space-y-3 text-sm leading-relaxed">
            <ClampedProse :html="tip.body_html" />
            <div
                v-if="tip.tags.length"
                class="flex flex-wrap items-center gap-1"
            >
                <TagChip
                    v-for="tag in tip.tags"
                    :key="tag.id"
                    :slug="tag.slug"
                    :name="tag.name"
                />
            </div>
        </CardContent>
    </Card>
</template>
