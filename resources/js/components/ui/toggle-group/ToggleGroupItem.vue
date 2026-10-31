<script setup lang="ts">
import type { ToggleGroupItemProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import type { ToggleVariants } from "@/components/ui/toggle"
import { reactiveOmit } from "@vueuse/core"
import { ToggleGroupItem, useForwardProps } from "reka-ui"
import { toggleVariants } from "@/components/ui/toggle"
import { cn } from "@/lib/utils"
import { injectToggleGroupContext } from "."

const props = defineProps<ToggleGroupItemProps & {
  variant?: ToggleVariants["variant"]
  size?: ToggleVariants["size"]
  class?: HTMLAttributes["class"]
}>()

const delegatedProps = reactiveOmit(props, "class", "variant", "size")
const forwarded = useForwardProps(delegatedProps)
const context = injectToggleGroupContext()
</script>

<template>
  <ToggleGroupItem
    data-slot="toggle-group-item"
    v-bind="forwarded"
    :class="cn(toggleVariants({ variant: props.variant ?? context.variant.value, size: props.size ?? context.size.value }), props.class)"
  >
    <slot />
  </ToggleGroupItem>
</template>
