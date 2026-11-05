<script setup lang="ts">
import { useScrollSpy } from '@/composables/useScrollSpy';
import { cn } from '@/lib/utils';

const props = defineProps<{ sections: { id: string; label: string }[] }>();
const active = useScrollSpy(() => props.sections.map((section) => section.id));
</script>

<template>
    <nav
        aria-label="Sections"
        class="hidden lg:sticky lg:top-24 lg:block lg:w-48 lg:shrink-0 lg:self-start"
    >
        <ul class="space-y-1 border-l border-border">
            <li v-for="section in sections" :key="section.id">
                <a
                    :href="`#${section.id}`"
                    :aria-current="
                        active === section.id ? 'location' : undefined
                    "
                    :class="
                        cn(
                            '-ml-px block border-l-2 px-4 py-1 text-sm transition-colors',
                            active === section.id
                                ? 'border-primary-strong font-medium text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground active:text-foreground',
                        )
                    "
                    class="focus-ring"
                    >{{ section.label }}</a
                >
            </li>
        </ul>
    </nav>
</template>
