<script setup lang="ts">
import type { CalendarRootEmits, CalendarRootProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { ChevronLeft, ChevronRight } from "@lucide/vue"
import { reactiveOmit } from "@vueuse/core"
import {
  CalendarCell,
  CalendarCellTrigger,
  CalendarGrid,
  CalendarGridBody,
  CalendarGridHead,
  CalendarGridRow,
  CalendarHeadCell,
  CalendarHeader,
  CalendarHeading,
  CalendarNext,
  CalendarPrev,
  CalendarRoot,
  useForwardPropsEmits,
} from "reka-ui"
import { buttonVariants } from "@/components/ui/button"
import { cn } from "@/lib/utils"

const props = defineProps<CalendarRootProps & { class?: HTMLAttributes["class"] }>()
const emits = defineEmits<CalendarRootEmits>()
const forwarded = useForwardPropsEmits(reactiveOmit(props, "class"), emits)
</script>

<template>
  <CalendarRoot v-slot="{ grid, weekDays }" data-slot="calendar" v-bind="forwarded" :class="cn('p-3', props.class)">
    <CalendarHeader class="flex items-center justify-between gap-2">
      <CalendarPrev :class="cn(buttonVariants({ variant: 'outline', size: 'icon' }), 'size-7 bg-transparent p-0 opacity-50 hover:opacity-100')">
        <ChevronLeft class="size-4" aria-hidden="true" />
      </CalendarPrev>
      <CalendarHeading class="text-sm font-medium" />
      <CalendarNext :class="cn(buttonVariants({ variant: 'outline', size: 'icon' }), 'size-7 bg-transparent p-0 opacity-50 hover:opacity-100')">
        <ChevronRight class="size-4" aria-hidden="true" />
      </CalendarNext>
    </CalendarHeader>
    <div class="mt-4 flex flex-col gap-4 sm:flex-row">
      <CalendarGrid v-for="month in grid" :key="month.value.toString()" class="w-full border-collapse space-y-1">
        <CalendarGridHead>
          <CalendarGridRow class="flex">
            <CalendarHeadCell v-for="day in weekDays" :key="day" class="w-9 rounded-md text-[0.8rem] font-normal text-muted-foreground">{{ day }}</CalendarHeadCell>
          </CalendarGridRow>
        </CalendarGridHead>
        <CalendarGridBody>
          <CalendarGridRow v-for="(week, index) in month.rows" :key="index" class="mt-2 flex w-full">
            <CalendarCell v-for="date in week" :key="date.toString()" :date="date" class="relative p-0 text-center text-sm">
              <CalendarCellTrigger :day="date" :month="month.value" :class="cn(buttonVariants({ variant: 'ghost' }), 'size-9 p-0 font-normal aria-selected:opacity-100 data-[selected]:bg-primary data-[selected]:text-primary-foreground data-[today]:font-semibold data-[today]:underline data-[disabled]:text-muted-foreground data-[disabled]:opacity-50 data-[outside-view]:text-muted-foreground data-[outside-view]:opacity-50')" />
            </CalendarCell>
          </CalendarGridRow>
        </CalendarGridBody>
      </CalendarGrid>
    </div>
  </CalendarRoot>
</template>
