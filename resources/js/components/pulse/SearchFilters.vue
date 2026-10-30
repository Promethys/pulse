<script setup lang="ts">
import type { AcceptableValue } from 'reka-ui';
import { useId } from 'vue';
import InputError from '@/components/InputError.vue';
import DatePicker from '@/components/pulse/DatePicker.vue';
import TagChip from '@/components/pulse/TagChip.vue';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { SearchFilterValues, SearchOptions } from '@/types';

const props = defineProps<{
    modelValue: SearchFilterValues;
    options: SearchOptions;
    errors: Record<string, string>;
}>();
const emit = defineEmits<{
    'update:modelValue': [value: SearchFilterValues];
}>();
const id = useId();

function toggleTag(slug: string) {
    emit('update:modelValue', {
        ...props.modelValue,
        tags: props.modelValue.tags.includes(slug)
            ? props.modelValue.tags.filter((tag) => tag !== slug)
            : [...props.modelValue.tags, slug],
    });
}

function updateSource(value: AcceptableValue) {
    emit('update:modelValue', {
        ...props.modelValue,
        source: typeof value === 'string' && value !== 'all' ? value : null,
    });
}

function updateDate(field: 'from' | 'to', value: string | null) {
    emit('update:modelValue', { ...props.modelValue, [field]: value });
}
</script>

<template>
    <div class="space-y-5">
        <div>
            <Label :id="`${id}-tags`" class="mb-2 block">Tags</Label>
            <div
                role="group"
                :aria-labelledby="`${id}-tags`"
                class="flex flex-wrap gap-1.5"
            >
                <TagChip
                    v-for="tag in options.tags"
                    :key="tag.slug"
                    :slug="tag.slug"
                    :name="tag.name"
                    :active="modelValue.tags.includes(tag.slug)"
                    @toggle="toggleTag(tag.slug)"
                />
            </div>
        </div>
        <div>
            <Label :for="`${id}-source`" class="mb-2 block">Source</Label>
            <Select
                :model-value="modelValue.source ?? 'all'"
                @update:model-value="updateSource"
            >
                <SelectTrigger :id="`${id}-source`" class="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All sources</SelectItem>
                    <SelectItem
                        v-for="source in options.sources"
                        :key="source.slug"
                        :value="source.slug"
                    >
                        {{ source.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-1">
            <div>
                <Label :for="`${id}-from`" class="mb-2 block">From</Label>
                <DatePicker
                    :id="`${id}-from`"
                    :model-value="modelValue.from"
                    :invalid="!!errors.from"
                    :described-by="errors.from ? `${id}-from-error` : undefined"
                    @update:model-value="updateDate('from', $event)"
                />
                <InputError
                    :id="`${id}-from-error`"
                    :message="errors.from"
                    class="mt-2"
                />
            </div>
            <div>
                <Label :for="`${id}-to`" class="mb-2 block">To</Label>
                <DatePicker
                    :id="`${id}-to`"
                    :model-value="modelValue.to"
                    :invalid="!!errors.to"
                    :described-by="errors.to ? `${id}-to-error` : undefined"
                    @update:model-value="updateDate('to', $event)"
                />
                <InputError
                    :id="`${id}-to-error`"
                    :message="errors.to"
                    class="mt-2"
                />
            </div>
        </div>
    </div>
</template>
