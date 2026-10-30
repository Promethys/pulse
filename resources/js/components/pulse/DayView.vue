<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { parseDate } from '@internationalized/date';
import type { DateValue } from '@internationalized/date';
import { CalendarDays, ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/pulse/EmptyState.vue';
import NewsItemRow from '@/components/pulse/NewsItemRow.vue';
import SectionHeader from '@/components/pulse/SectionHeader.vue';
import TipCard from '@/components/pulse/TipCard.vue';
import TopicCard from '@/components/pulse/TopicCard.vue';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { formatDate } from '@/lib/dates';
import { home } from '@/routes';
import { show } from '@/routes/days';
import type { Day } from '@/types';

const props = defineProps<{
    day: Day;
    availableDates: string[];
    isToday: boolean;
    today: string;
}>();
const calendarOpen = ref(false);
const selectedDate = computed(() => parseDate(props.day.date));
const available = computed(() => new Set(props.availableDates));
const count = computed(() => props.day.news?.item_count ?? 0);
const dateHeading = computed(() => formatDate(props.day.date));
const hasLearningCards = computed(() =>
    Boolean(props.day.learning?.tip || props.day.learning?.topic),
);

function go(date: string | null) {
    if (!date) return;
    calendarOpen.value = false;
    router.visit(date === props.today ? home.url() : show.url(date));
}

function isDateDisabled(date: DateValue) {
    return !available.value.has(date.toString());
}

function selectDate(date: DateValue | undefined) {
    if (date && !isDateDisabled(date)) go(date.toString());
}
</script>

<template>
    <div>
        <header class="mb-6">
            <p
                class="text-xs font-semibold tracking-wider text-primary-strong uppercase"
            >
                {{ isToday ? 'Today' : 'Digest' }} · {{ count }}
                {{ count === 1 ? 'item' : 'items' }}
            </p>
            <div class="mt-1 flex items-center gap-2">
                <h1 class="flex-1 text-3xl font-semibold md:text-4xl">
                    {{ dateHeading }}
                </h1>
                <Button
                    variant="outline"
                    size="icon"
                    :disabled="!day.previous_date"
                    aria-label="Previous day"
                    @click="go(day.previous_date)"
                    ><ChevronLeft aria-hidden="true"
                /></Button>
                <Popover v-model:open="calendarOpen">
                    <PopoverTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label="Pick a date"
                            ><CalendarDays aria-hidden="true"
                        /></Button>
                    </PopoverTrigger>
                    <PopoverContent align="end" class="w-auto p-0">
                        <Calendar
                            :model-value="selectedDate"
                            :default-placeholder="selectedDate"
                            :is-date-disabled="isDateDisabled"
                            locale="en-GB"
                            calendar-label="Pick a date"
                            initial-focus
                            prevent-deselect
                            @update:model-value="selectDate"
                        />
                    </PopoverContent>
                </Popover>
                <Button
                    variant="outline"
                    size="icon"
                    :disabled="!day.next_date"
                    aria-label="Next day"
                    @click="go(day.next_date)"
                    ><ChevronRight aria-hidden="true"
                /></Button>
            </div>
        </header>
        <div v-if="hasLearningCards" class="grid gap-4 md:grid-cols-2">
            <TipCard v-if="day.learning?.tip" :tip="day.learning.tip" />
            <TopicCard v-if="day.learning?.topic" :topic="day.learning.topic" />
        </div>
        <template v-else-if="day.learning">
            <p
                v-if="day.learning.status === 'pending'"
                class="text-sm text-muted-foreground"
            >
                Your learning is being prepared.
            </p>
            <p
                v-else-if="day.learning.status === 'failed'"
                class="text-sm text-muted-foreground"
            >
                Today's learning could not be generated.
            </p>
        </template>
        <section v-if="day.news" class="mt-8">
            <p
                v-if="day.news.status === 'pending'"
                class="text-sm text-muted-foreground"
            >
                Your news is being prepared.
            </p>
            <p
                v-else-if="day.news.status === 'failed'"
                class="text-sm text-muted-foreground"
            >
                Today's news could not be generated.
            </p>
            <EmptyState
                v-else-if="count === 0"
                title="Quiet night"
                description="Nothing notable came through. Enjoy the tip and topic instead."
            />
            <template v-else>
                <div v-for="section in day.news.sections" :key="section.slug">
                    <SectionHeader
                        :title="section.name"
                        :count="section.items.length"
                    />
                    <NewsItemRow
                        v-for="item in section.items"
                        :key="item.id"
                        :item="item"
                    />
                </div>
            </template>
        </section>
    </div>
</template>
