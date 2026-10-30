<script setup lang="ts">
import type { RadioGroupItemProps, RadioGroupItemEmits } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { RadioGroupItem, RadioGroupIndicator, useForwardPropsEmits } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<RadioGroupItemProps & { class?: HTMLAttributes["class"] }>()
const emits = defineEmits<RadioGroupItemEmits>()
const delegatedProps = reactiveOmit(props, "class")
const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <RadioGroupItem
    data-slot="radio-group-item"
    v-bind="forwarded"
    :class="cn('aspect-square size-4 shrink-0 rounded-full border border-input text-primary-strong shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50', props.class)"
  >
    <RadioGroupIndicator class="flex items-center justify-center">
      <span class="size-2 rounded-full bg-current" />
    </RadioGroupIndicator>
  </RadioGroupItem>
</template>
