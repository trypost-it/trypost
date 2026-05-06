<script lang="ts" setup>
import type { RangeCalendarNextProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { IconChevronRight } from "@tabler/icons-vue"
import { RangeCalendarNext, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"
import { buttonVariants } from '@/components/ui/button'

const props = defineProps<RangeCalendarNextProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")

const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <RangeCalendarNext
    data-slot="range-calendar-next-button"
    :class="cn(
      buttonVariants({ variant: 'outline' }),
      'size-7 p-0',
      props.class,
    )"
    v-bind="forwardedProps"
  >
    <slot>
      <IconChevronRight class="size-4" />
    </slot>
  </RangeCalendarNext>
</template>
