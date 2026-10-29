<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PulseLogo from '@/components/pulse/PulseLogo.vue';
import ThemeToggle from '@/components/pulse/ThemeToggle.vue';
import { Card, CardContent } from '@/components/ui/card';
import { home } from '@/routes';

const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-screen bg-background">
        <div class="flex flex-1 flex-col px-4 py-6">
            <header class="flex items-center justify-between">
                <Link :href="home()"><PulseLogo /></Link>
                <ThemeToggle />
            </header>
            <main class="flex flex-1 items-center justify-center py-8">
                <div class="w-full max-w-sm">
                    <Card class="gap-0 py-0 shadow-none">
                        <CardContent class="space-y-5 p-6">
                            <div>
                                <h1 class="text-2xl font-semibold">
                                    {{ title }}
                                </h1>
                                <p
                                    v-if="description"
                                    class="mt-1 text-sm text-muted-foreground"
                                >
                                    {{ description }}
                                </p>
                            </div>
                            <slot />
                        </CardContent>
                    </Card>
                    <div
                        v-if="$slots.footer"
                        class="mt-4 text-center text-sm text-muted-foreground"
                    >
                        <slot name="footer" />
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
