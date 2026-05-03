<script setup lang="ts">
import { computed } from 'vue';

import { formatNumber } from '@/lib/utils';

type Props = {
    label: string;
    current: number;
    limit?: number;
};

const props = defineProps<Props>();

const RING_RADIUS = 8;
const RING_CIRCUMFERENCE = 2 * Math.PI * RING_RADIUS;

const hasLimit = computed(() => props.limit !== undefined);

const percentage = computed(() => {
    if (!hasLimit.value || props.limit === 0) {
        return 0;
    }
    return Math.min(100, (props.current / (props.limit as number)) * 100);
});

const isOverLimit = computed(
    () => hasLimit.value && props.current >= (props.limit as number),
);

const dashoffset = computed(
    () => RING_CIRCUMFERENCE * (1 - percentage.value / 100),
);
</script>

<template>
    <div class="flex items-center gap-3 py-3">
        <svg class="size-5 shrink-0 -rotate-90" viewBox="0 0 20 20">
            <circle
                cx="10"
                cy="10"
                :r="RING_RADIUS"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                class="opacity-10"
            />
            <circle
                v-if="hasLimit"
                cx="10"
                cy="10"
                :r="RING_RADIUS"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                stroke-linecap="round"
                class="transition-all duration-700 ease-out"
                :class="isOverLimit ? 'text-destructive' : ''"
                :stroke-dasharray="RING_CIRCUMFERENCE"
                :stroke-dashoffset="dashoffset"
            />
        </svg>
        <span class="text-sm">{{ label }}</span>
        <span class="ml-auto text-sm tabular-nums">
            <span
                class="font-medium"
                :class="isOverLimit ? 'text-destructive' : ''"
            >
                {{ formatNumber(current) }}
            </span>
            <span v-if="hasLimit" class="text-muted-foreground">
                / {{ formatNumber(limit as number) }}
            </span>
        </span>
    </div>
</template>
