<script setup lang="ts">
import type { ToggleGroupRootEmits, ToggleGroupRootProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import type { ToggleVariants } from "@/components/ui/toggle"
import { reactiveOmit } from "@vueuse/core"
import { ToggleGroupRoot, useForwardPropsEmits } from "reka-ui"
import { toRef } from "vue"
import { cn } from "@/lib/utils"
import { provideToggleGroupContext } from "."

const props = defineProps<ToggleGroupRootProps & {
  variant?: ToggleVariants["variant"]
  size?: ToggleVariants["size"]
  class?: HTMLAttributes["class"]
}>()
const emits = defineEmits<ToggleGroupRootEmits>()

const delegatedProps = reactiveOmit(props, "class", "variant", "size")
const forwarded = useForwardPropsEmits(delegatedProps, emits)

provideToggleGroupContext({
  variant: toRef(props, "variant"),
  size: toRef(props, "size"),
})
</script>

<template>
  <ToggleGroupRoot
    data-slot="toggle-group"
    v-bind="forwarded"
    :class="cn('flex items-center gap-1', props.class)"
  >
    <slot />
  </ToggleGroupRoot>
</template>
