<script setup lang="ts">
defineProps<{ day: { date: string } }>();
</script>

<template>
    <h1>{{ day.date }}</h1>
</template>
