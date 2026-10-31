<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ data: number[] }>();
const path = computed(() => {
    const max = Math.max(1, ...props.data);
    const intervals = Math.max(1, props.data.length - 1);
    const points = props.data.map((value, index) => ({
        x: (index / intervals) * 100,
        y: 28 - (value / max) * 26,
    }));

    return points
        .map(({ x, y }, index) => {
            if (index === 0) return `M ${x},${y}`;
            const previous = points[index - 1];
            const middle = (previous.x + x) / 2;

            return `C ${middle},${previous.y} ${middle},${y} ${x},${y}`;
        })
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
        <path
            :d="path"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            vector-effect="non-scaling-stroke"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
    </svg>
</template>
