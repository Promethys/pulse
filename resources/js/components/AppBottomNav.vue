<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { isActive, navItems } from '@/lib/navigation';

const page = usePage();
const items = computed(() =>
    navItems.filter((item) => item.available && !item.desktopOnly),
);
</script>

<template>
    <nav
        aria-label="Mobile navigation"
        class="fixed inset-x-0 bottom-0 z-40 grid auto-cols-fr grid-flow-col border-t border-border bg-background/90 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden"
    >
        <Link
            v-for="item in items"
            :key="item.href"
            :href="item.href"
            :aria-current="isActive(page.url, item.href) ? 'page' : undefined"
            class="flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium"
        >
            <span
                class="flex h-7 w-12 items-center justify-center rounded-full transition-colors"
                :class="
                    isActive(page.url, item.href)
                        ? 'bg-primary text-primary-foreground'
                        : 'text-muted-foreground'
                "
            >
                <component
                    :is="item.icon"
                    class="size-[18px]"
                    aria-hidden="true"
                />
            </span>
            <span
                :class="
                    isActive(page.url, item.href)
                        ? 'text-foreground'
                        : 'text-muted-foreground'
                "
                >{{ item.title }}</span
            >
        </Link>
    </nav>
</template>
