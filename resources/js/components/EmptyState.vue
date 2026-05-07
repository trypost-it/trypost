<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    icon: Component;
    title: string;
    description: string;
}>();
</script>

<template>
    <div
        class="flex flex-1 flex-col items-center justify-center gap-4 rounded-xl border-2 border-dashed border-foreground/25 bg-card p-12 text-center"
    >
        <div
            class="inline-flex size-16 -rotate-2 items-center justify-center rounded-2xl border-2 border-foreground bg-violet-200 shadow-2xs"
        >
            <component
                :is="icon"
                class="size-8 text-foreground"
                stroke-width="1.75"
            />
        </div>
        <div class="space-y-1">
            <h3
                class="text-xl text-foreground"
                style="font-family: var(--font-display)"
            >
                {{ title }}
            </h3>
            <p class="text-sm text-foreground/60">{{ description }}</p>
        </div>
        <div v-if="$slots.action" class="mt-2">
            <slot name="action" />
        </div>
    </div>
</template>
