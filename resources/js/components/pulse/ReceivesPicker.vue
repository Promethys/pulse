<script setup lang="ts">
import { useId } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

const props = defineProps<{ modelValue: string[] }>();
const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>();
const id = useId();
const kinds = [
    { value: 'news', label: 'News' },
    { value: 'learning', label: 'Learning' },
];
function toggle(kind: string, checked: boolean) {
    const next = checked
        ? [...props.modelValue, kind]
        : props.modelValue.filter((value) => value !== kind);
    if (next.length) emit('update:modelValue', next);
}
</script>

<template>
    <div class="flex gap-4">
        <div
            v-for="kind in kinds"
            :key="kind.value"
            class="flex items-center gap-2"
        >
            <Checkbox
                :id="`${id}-${kind.value}`"
                :model-value="modelValue.includes(kind.value)"
                @update:model-value="toggle(kind.value, $event === true)"
            />
            <Label :for="`${id}-${kind.value}`" class="font-normal">{{
                kind.label
            }}</Label>
        </div>
    </div>
</template>
