<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import HelpTip from '@/components/pulse/HelpTip.vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'System' },
] as const;
</script>

<template>
    <div class="inline-flex gap-1 rounded-lg bg-muted p-1">
        <div
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            class="flex items-center gap-1"
        >
            <button
                type="button"
                :aria-pressed="appearance === value"
                @click="updateAppearance(value)"
                :class="[
                    'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                    appearance === value
                        ? 'bg-primary text-primary-foreground shadow-xs'
                        : 'text-muted-foreground hover:bg-background/60 hover:text-foreground',
                ]"
            >
                <component :is="Icon" class="-ml-1 h-4 w-4" />
                <span class="ml-1.5 text-sm">{{ label }}</span>
            </button>
            <HelpTip
                v-if="value === 'system'"
                label="System appearance"
                text="Follow this device’s light or dark appearance."
            />
        </div>
    </div>
</template>
