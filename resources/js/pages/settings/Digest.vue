<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
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
                    />
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
                            <p class="text-xs text-muted-foreground">
                                {{
                                    schedule.kind === 'learning' &&
                                    !settings.learning_available
                                        ? 'Available with a learning interest'
                                        : schedule.description
                                }}
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
                <Button :disabled="processing">Save</Button>
            </form>
        </CardContent>
    </Card>
</template>
