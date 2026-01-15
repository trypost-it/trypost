<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { InputMask } from '@/components/ui/input';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import { useDateMaska } from '@/composables/useDateMaska';
import dayjs from '@/dayjs';
import { parseDate } from '@internationalized/date';
import { IconCalendar } from '@tabler/icons-vue';
import { ref, watch } from 'vue';

const props = defineProps({
  name: {
    type: String,
    required: true,
  },
  modelValue: {
    type: String,
    default: '',
  },
  align: {
    type: String as () => 'start' | 'center' | 'end',
    default: 'end',
    validator: (value: string) => ['start', 'end'].includes(value),
  },
  disabled: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:modelValue']);

const { dateOptions } = useDateMaska();

// Parse input value into date
function parseInput(value: string) {
  if (!value) return undefined;

  // Parse date string (YYYY-MM-DD format)
  try {
    return parseDate(value);
  } catch {
    return undefined;
  }
}

const internalDate = ref(parseInput(props.modelValue));
const displayValue = ref(
  props.modelValue && dayjs(props.modelValue).isValid()
    ? dayjs(props.modelValue).format('MM/DD/YYYY')
    : '',
);
const popoverOpen = ref(false);

// Parse American date format (MM/DD/YYYY) to YYYY-MM-DD
function parseAmericanDate(value: string): string | null {
  if (!value) return null;

  // Remove any non-digit characters
  const digits = value.replace(/\D/g, '');

  // Try to parse MM/DD/YYYY format
  if (digits.length === 8) {
    const month = digits.substring(0, 2);
    const day = digits.substring(2, 4);
    const year = digits.substring(4, 8);

    const dateStr = `${year}-${month}-${day}`;
    const parsed = dayjs(dateStr, 'YYYY-MM-DD');

    if (parsed.isValid()) {
      return dateStr;
    }
  }

  return null;
}

// Handle manual input change
function onInputChange(event: Event) {
  const value = (event.target as HTMLInputElement).value;
  displayValue.value = value;

  const parsedDate = parseAmericanDate(value);
  if (parsedDate) {
    internalDate.value = parseInput(parsedDate);
    emit('update:modelValue', parsedDate);
  }
}

// Parse input value into date component
const isInternalUpdate = ref(false);

watch(
  () => props.modelValue,
  (newVal) => {
    if (isInternalUpdate.value) {
      isInternalUpdate.value = false;
      return;
    }

    internalDate.value = parseInput(newVal);

    // Update display value
    if (newVal) {
      const parsed = dayjs(newVal);
      if (parsed.isValid()) {
        displayValue.value = parsed.format('MM/DD/YYYY');
      }
    } else {
      displayValue.value = '';
    }
  },
  { immediate: true },
);

// Watch internal date changes from calendar
watch(internalDate, (newDate) => {
  if (!newDate || isInternalUpdate.value) return;

  isInternalUpdate.value = true;
  const formatted = newDate.toString();
  emit('update:modelValue', formatted);
  popoverOpen.value = false;

  // Update display value when date is selected from calendar
  if (formatted) {
    const parsed = dayjs(formatted);
    if (parsed.isValid()) {
      displayValue.value = parsed.format('MM/DD/YYYY');
    }
  }
});
</script>

<template>
  <div class="relative">
    <InputMask :id="name" v-model="displayValue" :mask-options="dateOptions" placeholder="MM/DD/YYYY"
      @input="onInputChange" class="pr-10" :disabled="disabled" />
    <Popover v-model:open="popoverOpen">
      <PopoverTrigger :disabled="disabled">
        <Button type="button" variant="ghost" size="sm" class="absolute top-0 right-0 z-10 h-full" :disabled="disabled">
          <IconCalendar class="h-4 w-4" />
        </Button>
      </PopoverTrigger>
      <PopoverContent class="w-auto p-0" :align="align">
        <Calendar v-model="internalDate" :placeholder="internalDate" layout="month-and-year" locale="en-US"
          calendar-label="Date picker" initial-focus />
      </PopoverContent>
    </Popover>
  </div>
</template>