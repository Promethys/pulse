<script setup lang="ts">
import { Check } from '@lucide/vue';
import { cn } from '@/lib/utils';

defineProps<{ steps: string[]; current: number }>();
</script>

<template>
    <div>
        <p
            class="mb-2 text-xs font-semibold tracking-wider text-primary-strong uppercase md:hidden"
        >
            Step {{ current + 1 }} of {{ steps.length }} · {{ steps[current] }}
        </p>
        <ol class="flex items-center gap-1.5 md:gap-3">
            <li
                v-for="(step, index) in steps"
                :key="step"
                :aria-current="index === current ? 'step' : undefined"
                class="flex flex-1 items-center gap-2"
            >
                <span
                    :class="
                        cn(
                            'hidden size-7 shrink-0 items-center justify-center rounded-full border text-xs font-semibold md:flex',
                            index < current
                                ? 'border-primary-strong bg-primary-strong text-background'
                                : index === current
                                  ? 'border-primary-strong bg-primary text-primary-foreground'
                                  : 'border-border text-muted-foreground',
                        )
                    "
                >
                    <Check
                        v-if="index < current"
                        class="size-3.5"
                        aria-hidden="true"
                    />
                    <template v-else>{{ index + 1 }}</template>
                </span>
                <span
                    :class="
                        cn(
                            'sr-only text-sm md:not-sr-only md:inline',
                            index === current
                                ? 'font-semibold'
                                : 'text-muted-foreground',
                        )
                    "
                    >{{ step }}</span
                >
                <span
                    :class="
                        cn(
                            'h-1 flex-1 rounded-full md:h-px',
                            index <= current
                                ? 'bg-primary-strong'
                                : 'bg-border',
                        )
                    "
                />
            </li>
        </ol>
    </div>
</template>
