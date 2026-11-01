<script setup lang="ts">
defineProps<{ items: { q: string; a: string }[] }>();
</script>

<template>
    <div class="divide-y divide-border">
        <details v-for="item in items" :key="item.q" class="group py-4">
            <summary
                class="cursor-pointer rounded-sm text-base font-semibold focus-visible:outline-2 focus-visible:outline-ring"
            >
                {{ item.q }}
            </summary>
            <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                {{ item.a }}
            </p>
        </details>
    </div>
</template>
