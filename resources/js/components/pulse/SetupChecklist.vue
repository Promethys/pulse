<script setup lang="ts">
import { Check, X } from '@lucide/vue';
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';
import type { SetupState } from '@/types';

const props = defineProps<{ state: SetupState }>();
const items: { key: keyof SetupState; label: string; hint: string }[] = [
    {
        key: 'interests',
        label: 'Pick your interests',
        hint: 'What the digest is about',
    },
    {
        key: 'sources',
        label: 'Review your sources',
        hint: 'Where news comes from',
    },
    {
        key: 'ai_key',
        label: 'Add an AI key',
        hint: "The digest won't run without one",
    },
    {
        key: 'delivery',
        label: 'Choose where to receive it',
        hint: 'Discord or email',
    },
];
const done = computed(
    () => items.filter((item) => props.state[item.key]).length,
);
</script>

<template>
    <Card class="gap-0 border-primary-strong/30 shadow-none">
        <CardContent class="p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p
                        class="text-xs font-semibold tracking-wider text-primary-strong uppercase"
                    >
                        Finish setting up
                    </p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        {{ done }} of 4 done
                    </p>
                </div>
            </div>
            <Progress
                :model-value="(done / 4) * 100"
                class="mt-3 h-1.5"
                aria-label="Setup progress"
            />
            <ul class="mt-3 divide-y divide-border">
                <li v-for="item in items" :key="item.key">
                    <div class="flex items-center gap-3 py-2.5">
                        <span
                            :class="
                                cn(
                                    'flex size-6 shrink-0 items-center justify-center rounded-full',
                                    state[item.key]
                                        ? 'bg-success text-background'
                                        : 'border border-dashed border-muted-foreground/50 text-muted-foreground',
                                )
                            "
                        >
                            <Check
                                v-if="state[item.key]"
                                class="size-3.5"
                                aria-hidden="true"
                            />
                            <X v-else class="size-3" aria-hidden="true" />
                            <span class="sr-only">{{
                                state[item.key] ? 'Complete' : 'Incomplete'
                            }}</span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span
                                :class="
                                    cn(
                                        'block text-sm font-medium',
                                        state[item.key] &&
                                            'text-muted-foreground line-through',
                                    )
                                "
                                >{{ item.label }}</span
                            >
                            <span
                                v-if="!state[item.key]"
                                class="block text-xs text-muted-foreground"
                                >{{ item.hint }}</span
                            >
                        </span>
                    </div>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
