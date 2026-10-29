<script setup lang="ts">
import { RadioGroupItem } from 'reka-ui';
import { cn } from '@/lib/utils';
import type { OnboardingProvider } from '@/types/onboarding';

defineProps<{ provider: OnboardingProvider; selected: boolean }>();
</script>

<template>
    <RadioGroupItem
        :value="provider.value"
        :aria-label="provider.label"
        :class="
            cn(
                'flex items-center gap-3 rounded-xl border p-3 text-left transition-colors focus-visible:outline-2 focus-visible:outline-ring',
                selected
                    ? 'border-primary-strong bg-primary/50 ring-1 ring-primary-strong'
                    : 'border-border bg-card hover:bg-secondary',
            )
        "
    >
        <span
            aria-hidden="true"
            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent font-mono text-sm font-semibold text-accent-foreground"
            >{{ provider.label.slice(0, 2) }}</span
        >
        <span class="min-w-0 text-sm font-semibold">{{ provider.label }}</span>
    </RadioGroupItem>
</template>
