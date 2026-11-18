<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Sunrise } from '@lucide/vue';
import { computed, ref } from 'vue';
import DayView from '@/components/pulse/DayView.vue';
import EmptyState from '@/components/pulse/EmptyState.vue';
import HeadlinePreview from '@/components/pulse/HeadlinePreview.vue';
import PageHeader from '@/components/pulse/PageHeader.vue';
import SetupChecklist from '@/components/pulse/SetupChecklist.vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/dates';
import { resume } from '@/routes/digest';
import type { TodayProps } from '@/types';

const props = defineProps<TodayProps>();
const resuming = ref(false);
function resumeDigests() {
    resuming.value = true;
    router.delete(resume.url(), {
        preserveScroll: true,
        onFinish: () => {
            resuming.value = false;
        },
    });
}
const incomplete = computed(
    () => !props.setup.interests || !props.setup.sources || !props.setup.ai_key,
);
const firstDigest = computed(() => {
    if (props.pause) return undefined;
    if (!props.setup.ai_key)
        return 'Add an AI key in Settings to start receiving digests.';
    return props.next_digest
        ? `Pulse prepares your digest every day at ${props.next_digest.time} (${props.next_digest.timezone}).`
        : undefined;
});
const notice = computed(() =>
    !props.pause &&
    props.is_today &&
    (props.day?.news?.status === 'pending' ||
        props.day?.learning?.status === 'pending')
        ? 'Your digest is being prepared. Refresh this page shortly.'
        : null,
);
</script>

<template>
    <Head title="Today" />
    <div
        v-if="pause"
        class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary-strong/30 bg-primary/40 p-4 text-sm text-foreground dark:bg-primary/10"
    >
        <p>
            {{
                pause.until
                    ? `Your digests are paused until ${formatDate(pause.until)}.`
                    : 'Your digests are paused.'
            }}
        </p>
        <Button
            :disabled="resuming"
            size="sm"
            class="focus-ring"
            @click="resumeDigests"
        >
            Resume
        </Button>
    </div>
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
        <p
            v-if="!is_today && !pause"
            class="mb-6 text-sm text-muted-foreground"
        >
            No digest for today yet. Showing your latest day.
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
                pause
                    ? 'Digests paused'
                    : setup.ai_key
                      ? 'Your first digest is on its way'
                      : 'Set up your first digest'
            "
            :description="firstDigest"
        >
            <template #icon
                ><Sunrise class="size-5" aria-hidden="true"
            /></template>
        </EmptyState>
        <HeadlinePreview v-if="preview" :items="preview" />
    </div>
</template>
