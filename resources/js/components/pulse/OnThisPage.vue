<script setup lang="ts">
import { useScrollSpy } from '@/composables/useScrollSpy';
import { cn } from '@/lib/utils';

export interface DocsHeading {
    id: string;
    text: string;
    level: number;
}

const props = defineProps<{ items: DocsHeading[] }>();
const active = useScrollSpy(() => props.items.map((item) => item.id));
</script>

<template>
    <nav
        v-if="items.length"
        aria-label="On this page"
        class="sticky top-24 hidden w-44 shrink-0 self-start lg:block"
    >
        <p
            class="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
        >
            On this page
        </p>
        <ul class="space-y-1.5 border-l border-border">
            <li v-for="item in items" :key="item.id">
                <a
                    :href="`#${item.id}`"
                    :aria-current="active === item.id ? 'location' : undefined"
                    :class="
                        cn(
                            '-ml-px block border-l-2 py-0.5 pl-3 text-sm transition-colors',
                            item.level === 3 && 'pl-6',
                            active === item.id
                                ? 'border-primary-strong text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground active:text-foreground',
                        )
                    "
                    class="focus-ring"
                    >{{ item.text }}</a
                >
            </li>
        </ul>
    </nav>
</template>
