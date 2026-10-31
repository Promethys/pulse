<script setup lang="ts">
defineProps<{ title: string; subtitle?: string }>();
</script>

<template>
    <header class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-3xl font-semibold md:text-4xl">{{ title }}</h1>
            <p v-if="subtitle" class="mt-1 text-sm text-muted-foreground">
                {{ subtitle }}
            </p>
        </div>
        <slot name="actions" />
    </header>
</template>
