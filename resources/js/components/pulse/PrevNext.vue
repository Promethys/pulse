<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight } from '@lucide/vue';
import { cn } from '@/lib/utils';
import { show } from '@/routes/docs';

export interface DocsEntry {
    section: string;
    sectionTitle: string;
    slug: string;
    title: string;
    summary: string;
    url: string;
}

defineProps<{ prev: DocsEntry | null; next: DocsEntry | null }>();
</script>

<template>
    <nav
        v-if="prev || next"
        aria-label="Previous and next articles"
        class="mt-10 grid gap-3 sm:grid-cols-2"
    >
        <template v-for="(entry, index) in [prev, next]" :key="index">
            <Link
                v-if="entry"
                :href="show({ section: entry.section, article: entry.slug })"
                :class="
                    cn(
                        'group rounded-xl border border-border p-4 transition-colors hover:bg-card',
                        index === 1 && 'text-right',
                    )
                "
            >
                <p
                    :class="
                        cn(
                            'flex items-center gap-1 text-xs text-muted-foreground',
                            index === 1 && 'justify-end',
                        )
                    "
                >
                    <ArrowLeft
                        v-if="index === 0"
                        class="size-3"
                        aria-hidden="true"
                    />
                    {{ index === 0 ? 'Previous' : 'Next' }}
                    <ArrowRight
                        v-if="index === 1"
                        class="size-3"
                        aria-hidden="true"
                    />
                </p>
                <p class="mt-1 font-semibold group-hover:underline">
                    {{ entry.title }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ entry.sectionTitle }}
                </p>
            </Link>
            <div v-else class="hidden sm:block" />
        </template>
    </nav>
</template>
