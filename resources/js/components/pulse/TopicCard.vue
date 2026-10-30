<script setup lang="ts">
import { BookOpen } from '@lucide/vue';
import { ref } from 'vue';
import ClampedProse from '@/components/pulse/ClampedProse.vue';
import TagChip from '@/components/pulse/TagChip.vue';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import type { LearningPost } from '@/types';

defineProps<{ topic: LearningPost }>();

const expanded = ref(false);
</script>

<template>
    <Card class="gap-3 shadow-none">
        <CardHeader class="gap-1">
            <div
                class="flex items-center gap-1.5 text-xs font-semibold tracking-wider text-accent-foreground uppercase"
            >
                <BookOpen class="size-3.5" aria-hidden="true" />
                Topic<template v-if="topic.category">
                    · {{ topic.category }}</template
                >
            </div>
            <h3
                :title="topic.title"
                :class="[
                    'text-lg leading-snug font-semibold',
                    { 'line-clamp-2': !expanded },
                ]"
            >
                {{ topic.title }}
            </h3>
        </CardHeader>
        <CardContent class="space-y-3 text-sm leading-relaxed">
            <ClampedProse v-model:expanded="expanded" :html="topic.body_html" />
            <div
                v-if="topic.tags.length"
                class="flex flex-wrap items-center gap-1"
            >
                <TagChip
                    v-for="tag in topic.tags"
                    :key="tag.id"
                    :slug="tag.slug"
                    :name="tag.name"
                    linked
                />
            </div>
        </CardContent>
    </Card>
</template>
