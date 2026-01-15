<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxList,
    ComboboxTrigger,
} from '@/components/ui/combobox';
import { IconCheck, IconChevronDown, IconSearch } from '@tabler/icons-vue';
import { FocusScope } from 'reka-ui';
import { ref, watchEffect } from 'vue';

interface Timezone {
    value: string;
    label: string;
}

interface Props {
    modelValue?: string | null;
    timezones: Record<string, string>;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:modelValue': [value: string | null];
}>();

// Common timezones to show first
const commonTimezoneIds = [
    'America/New_York',
    'America/Chicago',
    'America/Denver',
    'America/Los_Angeles',
    'America/Sao_Paulo',
    'America/Mexico_City',
    'Europe/London',
    'Europe/Paris',
    'Europe/Berlin',
    'Asia/Tokyo',
    'Asia/Shanghai',
    'Asia/Dubai',
    'Australia/Sydney',
    'Pacific/Auckland',
];

// Build timezone list from props
const timezones = Object.keys(props.timezones)
    .sort((a, b) => {
        const aIsCommon = commonTimezoneIds.includes(a);
        const bIsCommon = commonTimezoneIds.includes(b);
        if (aIsCommon && !bIsCommon) return -1;
        if (!aIsCommon && bIsCommon) return 1;
        return a.localeCompare(b);
    })
    .map((tz) => ({
        value: tz,
        label: tz.replace(/_/g, ' '),
    }));

const selectedTimezone = ref<Timezone | undefined>();

watchEffect(() => {
    selectedTimezone.value = timezones.find(
        (tz) => tz.value === props.modelValue,
    );
});
</script>

<template>
    <FocusScope as-child>
        <Combobox
            :model-value="selectedTimezone"
            @update:model-value="
                (v: Timezone) => {
                    selectedTimezone = v;
                    emit('update:modelValue', v?.value || null);
                }
            "
        >
            <ComboboxAnchor as-child>
                <ComboboxTrigger as-child>
                    <Button
                        variant="outline"
                        class="w-full justify-between"
                    >
                        {{
                            selectedTimezone
                                ? selectedTimezone.label
                                : 'Select timezone'
                        }}
                        <IconChevronDown
                            class="ml-2 h-4 w-4 shrink-0 opacity-50"
                        />
                    </Button>
                </ComboboxTrigger>
            </ComboboxAnchor>
            <ComboboxList class="w-full">
                <div class="relative">
                    <ComboboxInput placeholder="Search timezone..." />
                    <span
                        class="absolute inset-y-0 start-0 flex items-center justify-center px-3"
                    >
                        <IconSearch class="size-4 text-muted-foreground" />
                    </span>
                </div>
                <ComboboxEmpty>No timezone found</ComboboxEmpty>
                <ComboboxGroup>
                    <ComboboxItem
                        v-for="tz in timezones"
                        :key="tz.value"
                        :value="tz"
                    >
                        <span class="min-w-0 flex-1 truncate">{{
                            tz.label
                        }}</span>
                        <ComboboxItemIndicator>
                            <IconCheck class="ml-auto h-4 w-4" />
                        </ComboboxItemIndicator>
                    </ComboboxItem>
                </ComboboxGroup>
            </ComboboxList>
        </Combobox>
    </FocusScope>
</template>
