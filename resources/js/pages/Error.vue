<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import PulseLogo from '@/components/pulse/PulseLogo.vue';
import ThemeToggle from '@/components/pulse/ThemeToggle.vue';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

defineOptions({ layout: [] });
const props = defineProps<{ status: number }>();
const messages: Record<number, { title: string; body: string }> = {
    403: {
        title: 'You do not have access',
        body: 'You do not have permission to open this page.',
    },
    404: {
        title: 'This page wandered off',
        body: 'The link may be old, or the page moved.',
    },
    419: {
        title: 'Your session expired',
        body: 'Refresh the page and try again to continue securely.',
    },
    429: {
        title: 'Too many requests',
        body: 'Take a short break, then try again in a moment.',
    },
    500: {
        title: 'Something broke on our side',
        body: "It's not you. Try again in a moment; if it keeps happening, the issue is already logged.",
    },
    503: {
        title: 'We will be right back',
        body: 'Pulse is temporarily unavailable. Please try again in a moment.',
    },
};
const message = computed(() => messages[props.status] ?? messages[500]);
const canRetry = computed(() => props.status === 500 || props.status === 503);
const retry = () => router.reload();
</script>

<template>
    <Head :title="message.title" />
    <div class="flex min-h-screen flex-col bg-background px-4 py-6">
        <header class="flex items-center justify-between">
            <a :href="home.url()"><PulseLogo /></a>
            <ThemeToggle />
        </header>
        <main class="flex flex-1 items-center justify-center">
            <div class="max-w-sm text-center">
                <p
                    class="font-display text-7xl font-semibold text-primary-strong"
                >
                    {{ status }}
                </p>
                <h1 class="mt-4 text-2xl font-semibold">{{ message.title }}</h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ message.body }}
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-2">
                    <Button v-if="canRetry" variant="outline" @click="retry"
                        >Try again</Button
                    >
                    <Button as-child
                        ><a :href="home.url()">Back to Pulse</a></Button
                    >
                </div>
            </div>
        </main>
    </div>
</template>
