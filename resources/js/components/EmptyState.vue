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
        class="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed p-12"
    >
        <component :is="icon" class="mb-4 size-12 text-muted-foreground" />
        <h3 class="mb-1 text-lg font-medium">{{ title }}</h3>
        <p class="text-sm text-muted-foreground">{{ description }}</p>
    </div>
</template>
