<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import PulseMark from '@/components/pulse/PulseMark.vue';

defineProps<{ className?: HTMLAttributes['class'] }>();
</script>

<template>
    <PulseMark :class="className" />
</template>
