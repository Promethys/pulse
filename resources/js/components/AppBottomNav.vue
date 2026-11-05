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
            class="group flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium outline-none"
        >
            <span
                class="flex h-7 w-12 items-center justify-center rounded-full transition-colors group-focus-visible:ring-[3px]"
                :class="
                    isActive(page.url, item.href)
                        ? 'bg-primary text-primary-foreground group-focus-visible:ring-ring dark:group-focus-visible:ring-foreground/70'
                        : 'text-muted-foreground group-hover:bg-accent group-hover:text-accent-foreground group-focus-visible:ring-ring/50 group-active:bg-accent group-active:text-accent-foreground'
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
                        : 'text-muted-foreground group-hover:text-accent-foreground group-active:text-accent-foreground'
                "
                >{{ item.title }}</span
            >
        </Link>
    </nav>
</template>
