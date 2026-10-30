<script setup lang="ts">
defineProps<{
    slug: string;
    name: string;
    active?: boolean;
    onToggle?: () => void;
}>();
const emit = defineEmits<{ toggle: [] }>();
</script>

<template>
    <component
        :is="onToggle ? 'button' : 'span'"
        :type="onToggle ? 'button' : undefined"
        :aria-pressed="onToggle ? !!active : undefined"
        class="inline-flex h-7 items-center gap-1 rounded-full border px-2.5 text-xs font-medium transition-colors hover:bg-primary/60"
        :class="
            active
                ? 'border-primary bg-primary text-primary-foreground'
                : 'border-border bg-secondary text-secondary-foreground'
        "
        @click="emit('toggle')"
        >{{ name }}</component
    >
</template>
