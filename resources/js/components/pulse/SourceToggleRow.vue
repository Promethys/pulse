<script setup lang="ts">
import { computed } from 'vue';
import { Switch } from '@/components/ui/switch';
import type { OnboardingSource } from '@/types/onboarding';

const props = defineProps<{ source: OnboardingSource; modelValue: boolean }>();
defineEmits<{ 'update:modelValue': [value: boolean] }>();
const typeLabels: Record<string, string> = {
    feed: 'RSS feed',
    github_trending: 'GitHub Trending',
    resend_inbox: 'Newsletter',
};
const typeLabel = computed(
    () => typeLabels[props.source.type] ?? props.source.type,
);
</script>

<template>
    <li class="flex items-start gap-3 p-4">
        <div class="min-w-0 flex-1">
            <div class="flex items-baseline gap-x-2">
                <span class="line-clamp-1 font-semibold" :title="source.name">{{
                    source.name
                }}</span>
                <span class="shrink-0 text-xs text-muted-foreground">{{
                    typeLabel
                }}</span>
            </div>
            <p v-if="source.retired" class="text-sm text-muted-foreground">
                Retired: this source stopped working. Choose another source.
            </p>
        </div>
        <Switch
            :model-value="modelValue"
            :disabled="source.retired"
            :aria-label="`Use ${source.name}`"
            @update:model-value="$emit('update:modelValue', $event)"
        />
    </li>
</template>
