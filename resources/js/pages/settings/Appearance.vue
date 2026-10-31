<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Appearance settings" />

    <Card class="mb-4 shadow-none">
        <CardHeader>
            <CardTitle class="font-display">Appearance settings</CardTitle>
            <CardDescription
                >Update the appearance settings for your
                account</CardDescription
            >
        </CardHeader>
        <CardContent class="space-y-4">
            <AppearanceTabs />
        </CardContent>
    </Card>
</template>
