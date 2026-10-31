<script setup lang="ts">
import { CircleHelp } from '@lucide/vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

defineProps<{ text: string; label: string }>();
</script>

<template>
    <TooltipProvider>
        <Tooltip>
            <TooltipTrigger as-child>
                <button
                    type="button"
                    :aria-label="`Help: ${label}`"
                    class="inline-flex shrink-0 items-center rounded-sm text-muted-foreground focus-visible:outline-2 focus-visible:outline-ring"
                >
                    <CircleHelp class="size-4" aria-hidden="true" />
                </button>
            </TooltipTrigger>
            <TooltipContent class="max-w-xs">{{ text }}</TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
