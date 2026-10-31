<script setup lang="ts">
import PulseMark from '@/components/pulse/PulseMark.vue';

withDefaults(defineProps<{ size?: 'sm' | 'md' }>(), {
    size: 'md',
});
</script>

<template>
    <span class="inline-flex items-center gap-2">
        <PulseMark :class="size === 'sm' ? 'size-7' : 'size-8'" />
        <span
            class="font-display font-semibold"
            :class="size === 'sm' ? 'text-xl' : 'text-2xl'"
            >Pulse</span
        >
    </span>
</template>
