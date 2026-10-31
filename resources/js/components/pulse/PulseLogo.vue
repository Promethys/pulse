<script setup lang="ts">
import { Activity } from '@lucide/vue';

withDefaults(defineProps<{ size?: 'sm' | 'md' }>(), {
    size: 'md',
});
</script>

<template>
    <span class="inline-flex items-center gap-2">
        <span
            class="flex items-center justify-center rounded-lg bg-primary text-primary-foreground"
            :class="size === 'sm' ? 'size-7' : 'size-8'"
        >
            <Activity class="size-4" aria-hidden="true" />
        </span>
        <span
            class="font-display font-semibold"
            :class="size === 'sm' ? 'text-xl' : 'text-2xl'"
            >Pulse</span
        >
    </span>
</template>
