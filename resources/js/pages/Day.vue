<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import DayView from '@/components/pulse/DayView.vue';
import { formatDate } from '@/lib/dates';
import type { Day } from '@/types';

const props = defineProps<{ day: Day; available_dates: string[] }>();
const title = computed(() => formatDate(props.day.date));
</script>

<template>
    <Head :title="title" />
    <DayView
        :day="day"
        :available-dates="available_dates"
        :is-today="false"
        today=""
    />
</template>
