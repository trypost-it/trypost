<script setup lang="ts">
withDefaults(
    defineProps<{
        title?: string;
    }>(),
    {
        title: '',
    },
);
</script>

<template>
    <header
        class="flex h-14 shrink-0 items-center justify-between gap-2 border-b border-border px-4">
        <div class="flex items-center gap-2">
            <slot name="left">
                <h2 v-if="title" class="text-lg font-semibold">{{ title }}</h2>
            </slot>
        </div>
        <div class="flex items-center gap-2">
            <slot name="right" />
        </div>
    </header>
</template>
