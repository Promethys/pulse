<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';

defineOptions({ layout: [] });
</script>

<template>
    <PublicLayout>
        <Head title="Docs" />
        <div class="mx-auto max-w-5xl px-4 py-10">
            <h1 class="text-3xl font-semibold">Docs</h1>
        </div>
    </PublicLayout>
</template>
