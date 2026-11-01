<script setup lang="ts">
defineProps<{ name: string; alt: string }>();
</script>

<template>
    <img
        :src="`/images/shots/${name}.webp`"
        :alt="alt"
        loading="lazy"
        class="w-full dark:hidden"
    />
    <img
        :src="`/images/shots/${name}-dark.webp`"
        :alt="alt"
        loading="lazy"
        class="hidden w-full dark:block"
    />
</template>
