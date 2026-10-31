<script setup lang="ts">
import { Moon } from '@lucide/vue';

defineProps<{ title: string; description?: string }>();
</script>

<template>
    <div
        class="flex flex-col items-center rounded-2xl border border-dashed border-border px-6 py-12 text-center"
    >
        <div
            class="mb-3 flex size-12 items-center justify-center rounded-full bg-primary text-primary-foreground"
        >
            <slot name="icon"><Moon class="size-5" aria-hidden="true" /></slot>
        </div>
        <h3 class="text-lg font-semibold">{{ title }}</h3>
        <p
            v-if="description"
            class="mt-1 max-w-xs text-sm text-muted-foreground"
        >
            {{ description }}
        </p>
        <div v-if="$slots.actions" class="mt-4"><slot name="actions" /></div>
    </div>
</template>
