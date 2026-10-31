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
                >Choose how Pulse looks in this browser.</CardDescription
            >
        </CardHeader>
        <CardContent class="space-y-4">
            <AppearanceTabs />
            <p class="text-sm text-muted-foreground">
                System follows the light or dark appearance of this device.
            </p>
        </CardContent>
    </Card>
</template>
