<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { parseDate, today } from '@internationalized/date';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import DatePicker from '@/components/pulse/DatePicker.vue';
import HelpTip from '@/components/pulse/HelpTip.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Slider } from '@/components/ui/slider';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { formatDate } from '@/lib/dates';
import { pause, resume } from '@/routes/digest';
import type { DigestSettingsProps } from '@/types/settings';

const props = defineProps<DigestSettingsProps>();
const page = usePage();
const errors = computed(() => page.props.errors);
const form = ref({
    timezone: props.settings.timezone,
    profile: props.settings.profile ?? '',
    news_enabled: props.settings.news_enabled,
    news_time: props.settings.news_time,
    learning_enabled:
        props.settings.learning_enabled && props.settings.learning_available,
    learning_time: props.settings.learning_time,
    max_items: props.settings.max_items,
});
const processing = ref(false);
const pauseProcessing = ref(false);
const pauseMode = ref('resume');
const pauseUntil = ref<string | null>(null);
const localToday = computed(() => today(props.settings.timezone).toString());
const lastPauseDate = computed(() =>
    parseDate(localToday.value).add({ days: 365 }).toString(),
);

function pickPause(days: number) {
    pauseMode.value = 'date';
    pauseUntil.value = parseDate(localToday.value).add({ days }).toString();
}

function pauseDigests() {
    pauseProcessing.value = true;
    router.put(
        pause.url(),
        { until: pauseMode.value === 'date' ? pauseUntil.value : null },
        {
            preserveScroll: true,
            onFinish: () => {
                pauseProcessing.value = false;
            },
        },
    );
}

function resumeDigests() {
    pauseProcessing.value = true;
    router.delete(resume.url(), {
        preserveScroll: true,
        onFinish: () => {
            pauseProcessing.value = false;
        },
    });
}
const times = Array.from(
    { length: 96 },
    (_, index) =>
        `${String(Math.floor(index / 4)).padStart(2, '0')}:${String((index % 4) * 15).padStart(2, '0')}`,
);
const schedules = [
    {
        kind: 'news',
        label: 'News',
        description: 'Grouped headlines with summaries',
    },
    {
        kind: 'learning',
        label: 'Learning',
        description: 'One tip and one deeper topic a day',
    },
] as const;
function save() {
    processing.value = true;
    router.put(
        '/settings/digest',
        { ...form.value },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Digest settings" />
    <Card class="mb-4 shadow-none">
        <CardHeader
            ><CardTitle class="font-display">Digest</CardTitle
            ><CardDescription
                >What you receive and when.</CardDescription
            ></CardHeader
        >
        <CardContent>
            <form class="space-y-4" @submit.prevent="save">
                <div class="space-y-1.5">
                    <Label for="about">About you</Label>
                    <Textarea
                        id="about"
                        v-model="form.profile"
                        :rows="4"
                        :maxlength="2000"
                        placeholder="Laravel developer, curious about AI agents, not interested in crypto. I like short summaries with a link to the source."
                    />
                    <p class="text-sm text-muted-foreground">
                        Optional. Describe your experience, interests and what
                        to avoid so the AI can choose and explain content for
                        you. This text is sent to your AI provider.
                    </p>
                    <p class="text-right text-xs text-muted-foreground">
                        {{ form.profile.length.toLocaleString('en') }} / 2,000
                    </p>
                    <InputError :message="errors.profile" />
                </div>
                <div class="space-y-1.5">
                    <Label for="timezone">Timezone</Label>
                    <Select v-model="form.timezone"
                        ><SelectTrigger id="timezone" class="w-full"
                            ><SelectValue /></SelectTrigger
                        ><SelectContent
                            ><SelectItem
                                v-for="timezone in timezones"
                                :key="timezone"
                                :value="timezone"
                                >{{ timezone }}</SelectItem
                            ></SelectContent
                        ></Select
                    >
                    <InputError :message="errors.timezone" />
                </div>
                <p class="text-sm text-muted-foreground">
                    Times use your selected timezone. Pulse checks every 15
                    minutes and starts preparing each digest when it is due.
                    Delivery can take longer.
                </p>
                <div
                    v-for="schedule in schedules"
                    :key="schedule.kind"
                    class="space-y-1.5"
                >
                    <div
                        class="flex flex-wrap items-center gap-3 rounded-xl border border-border p-3"
                        :class="
                            schedule.kind === 'learning' &&
                            !settings.learning_available
                                ? 'opacity-60'
                                : ''
                        "
                    >
                        <div class="flex-1">
                            <Label :for="schedule.kind">{{
                                schedule.label
                            }}</Label>
                            <p
                                v-if="
                                    schedule.kind === 'learning' &&
                                    !settings.learning_available
                                "
                                class="text-sm text-muted-foreground"
                            >
                                Choose an interest marked ‘Includes learning’ on
                                <Link
                                    href="/sources"
                                    class="underline underline-offset-4 focus-ring hover:decoration-2 active:decoration-2"
                                    >Sources</Link
                                >
                                to enable daily tips and topics.
                            </p>
                            <p v-else class="text-xs text-muted-foreground">
                                {{ schedule.description }}
                            </p>
                        </div>
                        <Select v-model="form[`${schedule.kind}_time`]"
                            ><SelectTrigger
                                :aria-label="`${schedule.label} time`"
                                class="w-28 font-mono"
                                ><SelectValue /></SelectTrigger
                            ><SelectContent class="max-h-72"
                                ><SelectItem
                                    v-for="time in times"
                                    :key="time"
                                    :value="time"
                                    class="font-mono"
                                    >{{ time }}</SelectItem
                                ></SelectContent
                            ></Select
                        >
                        <Switch
                            :id="schedule.kind"
                            v-model="form[`${schedule.kind}_enabled`]"
                            :disabled="
                                schedule.kind === 'learning' &&
                                !settings.learning_available
                            "
                        />
                    </div>
                    <InputError :message="errors[`${schedule.kind}_time`]" />
                    <InputError :message="errors[`${schedule.kind}_enabled`]" />
                </div>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <Label>Max items per day</Label
                        ><span class="font-mono text-sm">{{
                            form.max_items
                        }}</span>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Maximum news stories in each daily digest. You may
                        receive fewer.
                        <HelpTip
                            label="News limit"
                            text="This does not limit the daily tip and topic."
                        />
                    </p>
                    <Slider
                        :model-value="[form.max_items]"
                        :min="1"
                        :max="20"
                        :step="1"
                        label="Max items per day"
                        @update:model-value="form.max_items = $event?.[0] ?? 1"
                    />
                    <InputError :message="errors.max_items" />
                </div>
                <Button :disabled="processing" class="focus-ring">Save</Button>
            </form>
        </CardContent>
    </Card>
    <Card class="mb-4 shadow-none">
        <CardHeader>
            <CardTitle class="font-display">Pause digests</CardTitle>
            <CardDescription>
                Take a break from both digests. Paused days get no digest and
                stay empty in your history.
            </CardDescription>
        </CardHeader>
        <CardContent>
            <div
                v-if="settings.paused"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary-strong/30 bg-primary/40 p-4 text-sm text-foreground dark:bg-primary/10"
            >
                <p>
                    {{
                        settings.paused_until
                            ? `Digests are paused until ${formatDate(settings.paused_until)}.`
                            : 'Digests are paused until you resume them.'
                    }}
                </p>
                <Button
                    :disabled="pauseProcessing"
                    size="sm"
                    class="focus-ring"
                    @click="resumeDigests"
                >
                    Resume digests
                </Button>
            </div>
            <form v-else class="space-y-4" @submit.prevent="pauseDigests">
                <RadioGroup
                    v-model="pauseMode"
                    aria-label="Pause duration"
                    class="grid gap-2 sm:grid-cols-2"
                >
                    <Label
                        for="pause-resume"
                        class="flex items-center gap-2 rounded-lg border border-border p-3 font-normal has-[[data-state=checked]]:border-primary-strong"
                    >
                        <RadioGroupItem
                            id="pause-resume"
                            value="resume"
                            class="focus-ring"
                        />
                        Until I resume
                    </Label>
                    <Label
                        for="pause-date"
                        class="flex items-center gap-2 rounded-lg border border-border p-3 font-normal has-[[data-state=checked]]:border-primary-strong"
                    >
                        <RadioGroupItem
                            id="pause-date"
                            value="date"
                            class="focus-ring"
                        />
                        Until a date
                    </Label>
                </RadioGroup>
                <div v-if="pauseMode === 'date'" class="space-y-1.5">
                    <Label for="pause-until">End date</Label>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="w-full sm:w-auto">
                            <DatePicker
                                id="pause-until"
                                v-model="pauseUntil"
                                aria-label="End date"
                                :min="localToday"
                                :max="lastPauseDate"
                                :invalid="!!errors.until"
                                :described-by="
                                    errors.until
                                        ? 'pause-until-error'
                                        : undefined
                                "
                            />
                        </div>
                        <Button
                            v-for="pick in [
                                { days: 7, label: '1 week' },
                                { days: 14, label: '2 weeks' },
                            ]"
                            :key="pick.days"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="focus-ring"
                            @click="pickPause(pick.days)"
                        >
                            {{ pick.label }}
                        </Button>
                    </div>
                    <InputError
                        id="pause-until-error"
                        :message="errors.until"
                    />
                </div>
                <InputError v-else :message="errors.until" />
                <Button
                    :disabled="
                        pauseProcessing || (pauseMode === 'date' && !pauseUntil)
                    "
                    class="focus-ring"
                >
                    Pause digests
                </Button>
            </form>
        </CardContent>
    </Card>
</template>
