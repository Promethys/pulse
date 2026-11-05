<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/pulse/PageHeader.vue';
import { settingsSections } from '@/lib/settingsSections';
import { index } from '@/routes/settings';

const page = usePage();
const currentPath = computed(() => page.url.split(/[?#]/)[0]);
const currentSection = computed(() =>
    settingsSections.find((section) => section.href === currentPath.value),
);
</script>

<template>
    <div>
        <div class="md:hidden">
            <Link
                :href="index()"
                class="mb-4 inline-flex items-center gap-1 text-sm text-muted-foreground focus-ring hover:text-foreground active:text-foreground"
            >
                <ChevronLeft class="size-4" aria-hidden="true" />
                Settings
            </Link>
            <h1 class="mb-4 text-3xl font-semibold">
                {{ currentSection?.label }}
            </h1>
        </div>
        <div class="hidden md:block">
            <PageHeader title="Settings" />
            <nav
                aria-label="Settings sections"
                class="mb-4 inline-flex h-auto flex-wrap items-center gap-1 rounded-lg bg-muted p-1 text-muted-foreground"
            >
                <Link
                    v-for="section in settingsSections"
                    :key="section.href"
                    :href="section.href"
                    :aria-current="
                        currentPath === section.href ? 'page' : undefined
                    "
                    class="inline-flex items-center justify-center rounded-md px-3 py-1 text-sm font-medium focus-ring transition-colors"
                    :class="
                        currentPath === section.href
                            ? 'bg-primary text-primary-foreground shadow-sm focus-visible:ring-ring!'
                            : 'hover:bg-accent hover:text-accent-foreground active:bg-accent active:text-accent-foreground dark:active:bg-accent'
                    "
                >
                    {{ section.label }}
                </Link>
            </nav>
        </div>
        <section class="space-y-4">
            <slot />
        </section>
    </div>
</template>
