<script setup lang="ts">
import { Activity } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';

defineProps<{ className?: HTMLAttributes['class'] }>();
</script>

<template>
    <Activity :class="className" aria-hidden="true" />
</template>
