<script setup lang="ts">
defineProps<{ slug: string; name: string }>();
</script>

<template>
    <span
        class="inline-flex h-7 items-center gap-1 rounded-full border border-border bg-secondary px-2.5 text-xs font-medium text-secondary-foreground transition-colors hover:bg-primary/60"
        >{{ name }}</span
    >
</template>
