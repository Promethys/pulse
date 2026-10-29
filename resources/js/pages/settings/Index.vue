<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import PageHeader from '@/components/pulse/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { settingsSections } from '@/lib/settingsSections';
import { logout } from '@/routes';

defineOptions({ layout: AppLayout });

const logOut = () => {
    router.flushAll();
    router.post(logout.url());
};
</script>

<template>
    <Head title="Settings" />
    <PageHeader title="Settings" />
    <ul
        class="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-card"
    >
        <li v-for="section in settingsSections" :key="section.href">
            <Link
                :href="section.href"
                class="flex items-center justify-between p-4 text-sm font-medium"
            >
                {{ section.label }}
                <ChevronRight
                    class="size-4 text-muted-foreground"
                    aria-hidden="true"
                />
            </Link>
        </li>
        <li>
            <button
                type="button"
                class="flex w-full items-center p-4 text-sm font-medium text-destructive"
                @click="logOut"
            >
                Log out
            </button>
        </li>
    </ul>
</template>
