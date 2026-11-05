<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { index } from '@/routes/docs';

defineOptions({ layout: [] });
</script>

<template>
    <PublicLayout>
        <Head title="Page not found - Docs" />
        <div class="mx-auto max-w-2xl px-4 py-16 text-center">
            <p class="mb-3 text-sm text-muted-foreground">Docs</p>
            <h1 class="text-3xl font-semibold">
                This page moved or never existed
            </h1>
            <p class="mt-3 text-muted-foreground">
                The link may be old, or the guide may have been renamed. Browse
                the docs or search for the guide you need.
            </p>
            <div class="mt-6 flex flex-wrap justify-center gap-2">
                <Button as-child class="focus-ring"
                    ><Link :href="index()">Docs home</Link></Button
                >
                <Button
                    as-child
                    variant="outline"
                    class="focus-ring hover:bg-accent hover:text-accent-foreground active:bg-accent active:text-accent-foreground dark:active:bg-accent"
                    ><Link :href="`${index.url()}#search`"
                        >Search the docs</Link
                    ></Button
                >
            </div>
        </div>
    </PublicLayout>
</template>
