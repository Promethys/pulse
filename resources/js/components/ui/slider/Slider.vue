<script setup lang="ts">
import type { SliderRootEmits, SliderRootProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { reactiveOmit } from '@vueuse/core';
import { SliderRange, SliderRoot, SliderThumb, SliderTrack, useForwardPropsEmits } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps<SliderRootProps & { class?: HTMLAttributes['class']; label?: string }>();
const emits = defineEmits<SliderRootEmits>();
const forwarded = useForwardPropsEmits(reactiveOmit(props, 'class', 'label'), emits);
</script>

<template>
    <SliderRoot v-bind="forwarded" :class="cn('relative flex w-full touch-none items-center select-none data-[disabled]:opacity-50', props.class)">
        <SliderTrack class="relative h-2 w-full grow overflow-hidden rounded-full bg-secondary">
            <SliderRange class="absolute h-full bg-primary-strong" />
        </SliderTrack>
        <SliderThumb :aria-label="label" class="block size-4 rounded-full border border-primary-strong bg-background shadow-sm focus-visible:outline-2 focus-visible:outline-ring" />
    </SliderRoot>
</template>
