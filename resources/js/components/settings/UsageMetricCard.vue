<script setup lang="ts">
import type { FunctionalComponent } from 'vue';
import { computed } from 'vue';

import { formatNumber } from '@/lib/utils';

type Tone =
    | 'violet'
    | 'amber'
    | 'emerald'
    | 'sky'
    | 'rose'
    | 'fuchsia'
    | 'cyan';
type Rotate = '-rotate-2' | '-rotate-1' | 'rotate-1' | 'rotate-2';

interface Props {
    label: string;
    icon: FunctionalComponent;
    current: number;
    limit?: number;
    tone?: Tone;
    rotate?: Rotate;
}

const props = withDefaults(defineProps<Props>(), {
    tone: 'violet',
    rotate: '-rotate-2',
});

const TONE_BG: Record<Tone, string> = {
    violet: 'bg-violet-200',
    amber: 'bg-amber-200',
    emerald: 'bg-emerald-200',
    sky: 'bg-sky-200',
    rose: 'bg-rose-200',
    fuchsia: 'bg-fuchsia-200',
    cyan: 'bg-cyan-200',
};

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
</script>

<template>
    <div
        class="space-y-4 rounded-xl border-2 border-foreground bg-card p-5 shadow-2xs"
    >
        <div class="flex items-start justify-between gap-3">
            <p
                class="text-[11px] font-black tracking-widest text-foreground/60 uppercase"
            >
                {{ label }}
            </p>
            <span
                :class="[
                    'inline-flex size-10 shrink-0 items-center justify-center rounded-2xl border-2 border-foreground shadow-2xs',
                    TONE_BG[tone],
                    rotate,
                ]"
            >
                <component
                    :is="icon"
                    class="size-5 text-foreground"
                    stroke-width="2"
                />
            </span>
        </div>

        <div class="space-y-3">
            <p class="tabular-nums">
                <span
                    class="text-3xl font-bold tracking-tight"
                    :class="isOverLimit ? 'text-rose-600' : 'text-foreground'"
                    >{{ formatNumber(current) }}</span
                >
                <span
                    v-if="hasLimit"
                    class="text-base font-bold text-foreground/40"
                >
                    / {{ formatNumber(limit as number) }}
                </span>
            </p>

            <div
                v-if="hasLimit"
                class="h-2 overflow-hidden rounded-full border-2 border-foreground bg-foreground/5"
            >
                <div
                    class="h-full rounded-full transition-all duration-700 ease-out"
                    :class="isOverLimit ? 'bg-rose-400' : 'bg-violet-400'"
                    :style="{ width: `${percentage}%` }"
                />
            </div>
        </div>
    </div>
</template>
