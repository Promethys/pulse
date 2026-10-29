<script setup lang="ts">
import { computed } from 'vue';
import InterestChip from '@/components/pulse/InterestChip.vue';
import type {
    OnboardingCategory,
    OnboardingInterest,
} from '@/types/onboarding';

const props = defineProps<{
    category: OnboardingCategory;
    interests: OnboardingInterest[];
    selected: string[];
}>();
defineEmits<{ toggle: [slug: string] }>();
const count = computed(
    () =>
        props.interests.filter(
            (interest) =>
                interest.status === 'live' &&
                props.selected.includes(interest.slug),
        ).length,
);
</script>

<template>
    <section class="space-y-2.5">
        <h2
            class="flex items-baseline gap-2 font-sans text-sm font-semibold tracking-wider text-muted-foreground uppercase"
        >
            {{ category.label }}
            <span v-if="count" class="text-primary-strong"
                >{{ count }} selected</span
            >
        </h2>
        <div class="flex flex-wrap gap-2">
            <InterestChip
                v-for="interest in interests"
                :key="interest.slug"
                :interest="interest"
                :selected="selected.includes(interest.slug)"
                @toggle="$emit('toggle', interest.slug)"
            />
        </div>
    </section>
</template>
