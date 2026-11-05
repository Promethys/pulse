<script setup lang="ts">
import { useResizeObserver } from '@vueuse/core';
import { onMounted, ref, useTemplateRef } from 'vue';
import { useCodeCopy } from '@/composables/useCodeCopy';

const props = defineProps<{ html: string }>();

const prose = useTemplateRef<HTMLElement>('prose');
const expanded = defineModel<boolean>('expanded', { default: false });
const overflowing = ref(false);

function measure() {
    if (prose.value && !expanded.value) {
        overflowing.value = prose.value.scrollHeight > prose.value.clientHeight;
    }
}

onMounted(measure);
useResizeObserver(prose, measure);
useCodeCopy(prose, () => props.html);
</script>

<template>
    <div>
        <div
            ref="prose"
            class="pulse-prose"
            :class="{
                'line-clamp-6': !expanded,
                '[&_.code-copy]:hidden': overflowing && !expanded,
            }"
            v-html="html"
        />
        <button
            v-if="overflowing"
            type="button"
            class="mt-1 text-sm font-medium text-primary-strong underline-offset-2 focus-ring hover:underline active:underline"
            :aria-expanded="expanded"
            @click="expanded = !expanded"
        >
            {{ expanded ? 'Show less' : 'Read more' }}
        </button>
    </div>
</template>
