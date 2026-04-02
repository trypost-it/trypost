<script setup lang="ts">
import type { DateRange } from "reka-ui"
import type { Ref } from "vue"
import {
  CalendarDate,
  getLocalTimeZone,
} from "@internationalized/date"
import { IconCalendar } from "@tabler/icons-vue"
import { computed, nextTick, ref, watch } from "vue"
import { useWindowSize } from "@vueuse/core"
import { Button } from "@/components/ui/button"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import { RangeCalendar } from "@/components/ui/range-calendar"
import { cn } from "@/lib/utils"
import dayjs from "@/dayjs"

const props = defineProps<{
  modelValue: { start: Date, end: Date }
}>()

const emit = defineEmits<{
  'update:modelValue': [value: { start: Date, end: Date }]
}>()

const toCalendarDate = (dateValue: Date) => {
  return new CalendarDate(
    dateValue.getFullYear(),
    dateValue.getMonth() + 1,
    dateValue.getDate(),
  )
}

const toDate = (calendarDate: any) => {
  if (!calendarDate) return new Date()
  return calendarDate.toDate(getLocalTimeZone())
}

const value = ref({
  start: toCalendarDate(props.modelValue.start),
  end: toCalendarDate(props.modelValue.end),
}) as Ref<DateRange>

const isUpdating = ref(false)
const isOpen = ref(false)
const { width } = useWindowSize()
const numberOfMonths = computed(() => width.value < 640 ? 1 : 2)

const range = (start: dayjs.Dayjs, end: dayjs.Dayjs) => ({
  start: toCalendarDate(start.toDate()),
  end: toCalendarDate(end.toDate()),
})

const presetGroups = [
  [
    { label: "Today", getValue: () => range(dayjs(), dayjs()) },
    { label: "Yesterday", getValue: () => range(dayjs().subtract(1, "day"), dayjs().subtract(1, "day")) },
  ],
  [
    { label: "Last 7 days", getValue: () => range(dayjs().subtract(6, "day"), dayjs()) },
    { label: "Last 30 days", getValue: () => range(dayjs().subtract(29, "day"), dayjs()) },
    { label: "Last 3 months", getValue: () => range(dayjs().subtract(3, "month"), dayjs()) },
    { label: "Last 6 months", getValue: () => range(dayjs().subtract(6, "month"), dayjs()) },
    { label: "Last 12 months", getValue: () => range(dayjs().subtract(12, "month").add(1, "day"), dayjs()) },
  ],
  [
    { label: "This month", getValue: () => range(dayjs().startOf("month"), dayjs().endOf("month")) },
    { label: "Last month", getValue: () => range(dayjs().subtract(1, "month").startOf("month"), dayjs().subtract(1, "month").endOf("month")) },
    { label: "Year to date", getValue: () => range(dayjs().startOf("year"), dayjs()) },
    { label: "Last year", getValue: () => range(dayjs().subtract(1, "year").startOf("year"), dayjs().subtract(1, "year").endOf("year")) },
  ],
]

type Preset = { label: string, getValue: () => { start: any, end: any } }

const applyPreset = (preset: Preset) => {
  value.value = preset.getValue()
  isOpen.value = false
}

watch(
  () => props.modelValue,
  (newVal) => {
    if (!isUpdating.value) {
      value.value = {
        start: toCalendarDate(newVal.start),
        end: toCalendarDate(newVal.end),
      }
    }
  },
  { deep: true },
)

watch(
  value,
  (newVal) => {
    if (newVal.start && newVal.end) {
      isUpdating.value = true
      emit("update:modelValue", {
        start: toDate(newVal.start),
        end: toDate(newVal.end),
      })
      nextTick(() => {
        isUpdating.value = false
      })
    }
  },
  { deep: true },
)
</script>

<template>
  <Popover v-model:open="isOpen">
    <PopoverTrigger as-child>
      <Button
        variant="outline"
        :class="cn(
          'w-full justify-start text-left font-normal sm:w-auto',
          !value && 'text-muted-foreground',
        )"
      >
        <template v-if="value.start">
          <template v-if="value.end">
            {{ dayjs(toDate(value.start)).format('MMM D, YYYY') }} -
            {{ dayjs(toDate(value.end)).format('MMM D, YYYY') }}
          </template>
          <template v-else>
            {{ dayjs(toDate(value.start)).format('MMM D, YYYY') }}
          </template>
        </template>
        <template v-else>
          Pick a date range
        </template>
        <IconCalendar class="ml-auto size-4 opacity-50" />
      </Button>
    </PopoverTrigger>
    <PopoverContent class="w-auto p-0" align="end">
      <div class="flex flex-col sm:flex-row">
        <div class="hidden flex-col border-b border-border py-2 sm:flex sm:w-[150px] sm:shrink-0 sm:border-b-0 sm:border-r">
          <template v-for="(group, groupIndex) in presetGroups" :key="groupIndex">
            <div v-if="groupIndex > 0" class="border-t border-border my-1" />
            <div class="space-y-0.5 px-2">
              <Button
                v-for="preset in group"
                :key="preset.label"
                variant="ghost"
                size="sm"
                class="w-full justify-start text-xs font-normal h-7"
                @click="applyPreset(preset)"
              >
                {{ preset.label }}
              </Button>
            </div>
          </template>
        </div>

        <div class="shrink-0">
          <RangeCalendar
            v-model="value"
            initial-focus
            :number-of-months="numberOfMonths"
          />
        </div>
      </div>
    </PopoverContent>
  </Popover>
</template>
