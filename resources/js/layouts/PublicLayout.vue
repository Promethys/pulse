<script setup lang="ts">
import PublicFooter from '@/components/pulse/PublicFooter.vue';
import PublicHeader from '@/components/pulse/PublicHeader.vue';
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background">
        <PublicHeader />
        <main class="flex-1">
            <slot />
        </main>
        <PublicFooter />
    </div>
</template>
