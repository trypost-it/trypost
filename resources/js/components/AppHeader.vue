<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
        showSidebarTrigger?: boolean;
    }>(),
    {
        breadcrumbs: () => [],
        showSidebarTrigger: true,
    },
);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-border px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
        <div class="flex items-center gap-2">
            <SidebarTrigger v-if="showSidebarTrigger" class="-ml-1" />
            <slot name="left">
                <template v-if="breadcrumbs && breadcrumbs.length > 0">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                </template>
            </slot>
        </div>
        <div v-if="$slots.center" class="flex items-center">
            <slot name="center" />
        </div>
        <div class="flex items-center gap-2">
            <slot name="right" />
        </div>
    </header>
</template>
