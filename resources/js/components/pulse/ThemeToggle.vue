<script setup lang="ts">
import { Moon, SunMedium } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { resolvedAppearance, updateAppearance } = useAppearance();
</script>

<template>
    <button
        type="button"
        :aria-label="
            resolvedAppearance === 'dark'
                ? 'Switch to light mode'
                : 'Switch to dark mode'
        "
        class="inline-flex size-9 items-center justify-center rounded-full text-muted-foreground focus-ring hover:bg-muted hover:text-foreground"
        @click="
            updateAppearance(resolvedAppearance === 'dark' ? 'light' : 'dark')
        "
    >
        <SunMedium
            v-if="resolvedAppearance === 'dark'"
            class="size-4"
            aria-hidden="true"
        />
        <Moon v-else class="size-4" aria-hidden="true" />
    </button>
</template>
