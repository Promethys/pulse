<script setup lang="ts">
import { CalendarDate, parseDate } from '@internationalized/date';
import type { DateValue } from '@internationalized/date';
import { CalendarDays } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { dateParts } from '@/lib/dates';

const props = defineProps<{
    modelValue: string | null;
    id?: string;
    ariaLabel?: string;
    invalid?: boolean;
    describedBy?: string;
    min?: string | null;
    max?: string | null;
}>();
const emit = defineEmits<{ 'update:modelValue': [value: string | null] }>();

const open = ref(false);
const selected = computed<CalendarDate | undefined>(() =>
    props.modelValue ? parseDate(props.modelValue) : undefined,
);
const minValue = computed(() => (props.min ? parseDate(props.min) : undefined));
const maxValue = computed(() => (props.max ? parseDate(props.max) : undefined));
const label = computed(() => {
    if (!props.modelValue) return 'Pick a date';

    const { day, month, year } = dateParts(props.modelValue);

    return `${day} ${month} ${year}`;
});

function select(date: DateValue | undefined) {
    if (date) {
        emit('update:modelValue', date.toString());
        open.value = false;
    }
}

function clear() {
    emit('update:modelValue', null);
    open.value = false;
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                :id="id"
                variant="outline"
                class="w-full justify-start font-normal data-[state=open]:bg-primary data-[state=open]:text-primary-foreground data-[state=open]:hover:bg-primary dark:aria-invalid:border-destructive dark:data-[state=open]:bg-primary dark:data-[state=open]:hover:bg-primary"
                :class="{ 'text-muted-foreground': !modelValue }"
                :aria-label="ariaLabel"
                :aria-invalid="invalid || undefined"
                :aria-describedby="describedBy"
            >
                <CalendarDays aria-hidden="true" />
                {{ label }}
            </Button>
        </PopoverTrigger>
        <PopoverContent
            align="start"
            class="w-auto p-0"
            :collision-padding="16"
        >
            <Calendar
                :model-value="selected"
                :default-placeholder="selected"
                :min-value="minValue"
                :max-value="maxValue"
                locale="en-GB"
                calendar-label="Pick a date"
                initial-focus
                @update:model-value="select"
            />
            <div v-if="modelValue" class="border-t border-border p-2">
                <Button variant="ghost" size="sm" class="w-full" @click="clear">
                    Clear
                </Button>
            </div>
        </PopoverContent>
    </Popover>
</template>
