<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { show } from '@/routes/tags';

defineProps<{
    slug: string;
    name: string;
    active?: boolean;
    linked?: boolean;
    href?: string;
    onToggle?: () => void;
}>();
const emit = defineEmits<{ toggle: [] }>();
</script>

<template>
    <component
        :is="onToggle ? 'button' : href || linked ? Link : 'span'"
        :href="
            !onToggle && (href || linked) ? (href ?? show.url(slug)) : undefined
        "
        :type="onToggle ? 'button' : undefined"
        :aria-pressed="onToggle ? !!active : undefined"
        class="inline-flex h-7 items-center gap-1 rounded-full border px-2.5 text-xs font-medium transition-colors"
        :class="[
            (onToggle || href || linked) && 'focus-ring',
            active
                ? 'border-primary bg-primary text-primary-foreground'
                : 'border-border bg-secondary text-secondary-foreground',
            !active &&
                (onToggle || href || linked) &&
                'hover:bg-accent hover:text-accent-foreground active:bg-accent active:text-accent-foreground',
        ]"
        @click="emit('toggle')"
        >{{ name }}</component
    >
</template>
