<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Sunrise } from '@lucide/vue';
import { computed } from 'vue';
import DayView from '@/components/pulse/DayView.vue';
import EmptyState from '@/components/pulse/EmptyState.vue';
import PageHeader from '@/components/pulse/PageHeader.vue';
import SetupChecklist from '@/components/pulse/SetupChecklist.vue';
import { timeHasPassed } from '@/lib/dates';
import type { TodayProps } from '@/types';

const props = defineProps<TodayProps>();
const incomplete = computed(
    () => !props.setup.interests || !props.setup.sources || !props.setup.ai_key,
);
const firstDigest = computed(() => {
    if (!props.setup.ai_key)
        return 'Add an AI key in Settings to start receiving digests.';
    return props.next_digest
        ? `Your first digest will be prepared at ${props.next_digest.time} (${props.next_digest.timezone}).`
        : undefined;
});
const notice = computed(() => {
    if (!props.setup.ai_key)
        return 'Add an AI key in Settings to start receiving digests.';
    if (!props.next_digest) return null;

    const { time, timezone } = props.next_digest;

    return timeHasPassed(time, timezone)
        ? "Today's digest is not ready yet. Check the generation status below and refresh this page shortly."
        : `Today's digest will start preparing at ${time} (${timezone}).`;
});
</script>

<template>
    <Head title="Today" />
    <div v-if="day">
        <div v-if="incomplete" class="mb-6">
            <SetupChecklist :state="setup" />
        </div>
        <p
            v-if="notice"
            class="mb-6 rounded-xl border border-primary-strong/30 bg-primary/40 p-4 text-sm text-foreground dark:bg-primary/10"
        >
            {{ notice }}
        </p>
        <DayView
            :day="day"
            :available-dates="available_dates"
            :is-today="is_today"
            :today="today"
        />
    </div>
    <div v-else class="space-y-6">
        <PageHeader title="Today" subtitle="Welcome to Pulse" />
        <SetupChecklist v-if="incomplete" :state="setup" />
        <EmptyState
            :title="
                setup.ai_key
                    ? 'Your first digest is on its way'
                    : 'Set up your first digest'
            "
            :description="firstDigest"
        >
            <template #icon
                ><Sunrise class="size-5" aria-hidden="true"
            /></template>
        </EmptyState>
    </div>
</template>
