<script setup lang="ts">
import { useId } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

const props = withDefaults(
    defineProps<{ modelValue: string[]; pauseHint?: boolean }>(),
    { pauseHint: false },
);
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
    <fieldset class="space-y-2">
        <legend class="text-sm font-medium">Send to this destination</legend>
        <p class="text-sm text-muted-foreground">
            Choose at least one.
            <template v-if="pauseHint">
                To pause this destination, turn off its switch.
            </template>
        </p>
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
    </fieldset>
</template>
