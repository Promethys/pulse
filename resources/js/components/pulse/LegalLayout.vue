<script setup lang="ts">
import StickySectionNav from '@/components/pulse/StickySectionNav.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

defineProps<{
    title: string;
    lastUpdated: string;
    intro?: string;
    sections: { id: string; label: string }[];
}>();
</script>

<template>
    <PublicLayout>
        <div class="mx-auto max-w-5xl px-4 py-10 md:py-14">
            <header class="mb-8 max-w-2xl">
                <h1 class="text-3xl font-semibold md:text-4xl">{{ title }}</h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    Last updated: {{ lastUpdated }}
                </p>
                <p
                    v-if="intro"
                    class="mt-3 leading-relaxed text-muted-foreground"
                >
                    {{ intro }}
                </p>
            </header>
            <nav
                v-if="sections.length"
                aria-label="Table of contents"
                class="mb-8 rounded-xl border border-border bg-card p-4 lg:hidden"
            >
                <p
                    class="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    On this page
                </p>
                <ul class="space-y-1.5 text-sm sm:columns-2 sm:gap-6">
                    <li v-for="section in sections" :key="section.id">
                        <a
                            :href="`#${section.id}`"
                            class="text-muted-foreground hover:text-foreground hover:underline focus-visible:outline-2 focus-visible:outline-ring"
                            >{{ section.label }}</a
                        >
                    </li>
                </ul>
            </nav>
            <div class="flex gap-10">
                <StickySectionNav v-if="sections.length" :sections="sections" />
                <div class="prose-pulse max-w-2xl min-w-0 flex-1">
                    <section
                        v-for="section in sections"
                        :id="section.id"
                        :key="section.id"
                        :aria-labelledby="`${section.id}-heading`"
                        class="scroll-mt-24"
                    >
                        <h2 :id="`${section.id}-heading`">
                            {{ section.label }}
                        </h2>
                        <slot :name="section.id" />
                    </section>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
