<script setup lang="ts">
import { RadioGroupItem } from 'reka-ui';
import ProviderLogo from '@/components/pulse/ProviderLogo.vue';
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
            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-secondary text-foreground"
        >
            <ProviderLogo :provider="provider.value" class="size-5" />
        </span>
        <span class="min-w-0 text-sm font-semibold">{{ provider.label }}</span>
    </RadioGroupItem>
</template>
