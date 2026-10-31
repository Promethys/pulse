<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ data: number[] }>();
const points = computed(() => {
    const max = Math.max(1, ...props.data);
    const intervals = Math.max(1, props.data.length - 1);

    return props.data
        .map(
            (value, index) =>
                `${(index / intervals) * 100},${28 - (value / max) * 26}`,
        )
        .join(' ');
});
</script>

<template>
    <svg
        viewBox="0 0 100 30"
        class="h-10 w-32 text-primary-strong"
        preserveAspectRatio="none"
        role="img"
        aria-label="Items per week, last 12 weeks"
    >
        <polyline
            :points="points"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            vector-effect="non-scaling-stroke"
            stroke-linejoin="round"
        />
    </svg>
</template>
