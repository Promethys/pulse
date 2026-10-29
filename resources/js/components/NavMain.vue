<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { isActive } from '@/lib/navigation';
import type { NavigationItem } from '@/lib/navigation';

defineProps<{ items: NavigationItem[] }>();

const page = usePage();
</script>

<template>
    <nav aria-label="Main navigation" class="flex flex-col gap-1">
        <Link
            v-for="item in items.filter(
                (item) => item.available && !item.mobileOnly,
            )"
            :key="item.href"
            :href="item.href"
            :aria-current="isActive(page.url, item.href) ? 'page' : undefined"
            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
            :class="
                isActive(page.url, item.href)
                    ? 'bg-sidebar-primary text-sidebar-primary-foreground'
                    : 'text-sidebar-foreground/75 hover:bg-sidebar-accent'
            "
        >
            <component :is="item.icon" class="size-4" aria-hidden="true" />
            {{ item.title }}
        </Link>
    </nav>
</template>
