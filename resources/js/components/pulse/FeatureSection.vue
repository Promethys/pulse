<script setup lang="ts">
import { cn } from '@/lib/utils';

defineProps<{
    id: string;
    kicker?: string;
    title: string;
    body: string;
    flip?: boolean;
}>();
</script>

<template>
    <section
        :id="id"
        :aria-labelledby="`${id}-heading`"
        class="scroll-mt-24 border-t border-border py-12 first:border-0 first:pt-0"
    >
        <div class="grid items-center gap-6 lg:grid-cols-2 lg:gap-10">
            <div :class="cn(flip && 'lg:order-2')">
                <p
                    v-if="kicker"
                    class="text-xs font-semibold tracking-wider text-primary-strong uppercase"
                >
                    {{ kicker }}
                </p>
                <h2
                    :id="`${id}-heading`"
                    class="mt-1 text-2xl font-semibold md:text-3xl"
                >
                    {{ title }}
                </h2>
                <p class="mt-3 leading-relaxed text-muted-foreground">
                    {{ body }}
                </p>
            </div>
            <div
                :class="
                    cn(
                        'min-w-0 overflow-hidden rounded-2xl border border-border bg-card',
                        flip && 'lg:order-1',
                    )
                "
            >
                <slot />
            </div>
        </div>
    </section>
</template>
