<script setup lang="ts">
defineProps<{ title: string; count?: number }>();
</script>

<template>
    <div class="mt-8 mb-1 flex items-baseline gap-2 first:mt-0">
        <h2 class="text-xl font-semibold">{{ title }}</h2>
        <span
            v-if="count !== undefined"
            class="text-sm text-muted-foreground"
            >{{ count }}</span
        >
        <div class="ml-2 h-px flex-1 self-center bg-border" />
    </div>
</template>
