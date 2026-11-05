<script setup lang="ts">
import { CircleHelp } from '@lucide/vue';
import { ref, watch } from 'vue';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

defineProps<{ text: string; label: string }>();
const open = ref(false);
const openedByHover = ref(false);

watch(open, (value) => {
    if (!value) openedByHover.value = false;
});

function enter(event: PointerEvent) {
    if (event.pointerType !== 'mouse' || open.value) return;
    open.value = true;
    openedByHover.value = true;
}

function leave(event: PointerEvent) {
    if (event.pointerType === 'mouse' && openedByHover.value)
        open.value = false;
}

function pinHoverOpened(event: MouseEvent) {
    if (!openedByHover.value) return;
    openedByHover.value = false;
    // Stops the trigger's toggle, which would close the tip.
    event.stopImmediatePropagation();
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <button
                type="button"
                :aria-label="`Help: ${label}`"
                class="inline-flex shrink-0 items-center rounded-sm align-text-bottom text-muted-foreground focus-ring hover:text-foreground active:text-foreground"
                @click.capture="pinHoverOpened"
                @pointerenter="enter"
                @pointerleave="leave"
            >
                <CircleHelp class="size-4" aria-hidden="true" />
            </button>
        </PopoverTrigger>
        <PopoverContent
            class="w-fit max-w-xs border-0 bg-foreground px-3 py-1.5 text-xs text-background"
            :collision-padding="8"
            @open-auto-focus.prevent
            @close-auto-focus.prevent
        >
            {{ text }}
        </PopoverContent>
    </Popover>
</template>
