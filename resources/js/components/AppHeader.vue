<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        title?: string;
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        title: '',
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="flex h-14 shrink-0 items-center justify-between gap-2 border-b border-border px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <slot name="left">
                <Breadcrumbs v-if="breadcrumbs.length > 0" :breadcrumbs="breadcrumbs" />
                <h2 v-else-if="title" class="text-lg font-semibold">{{ title }}</h2>
            </slot>
        </div>
        <div class="flex items-center gap-2">
            <slot name="right" />
        </div>
    </header>
</template>
