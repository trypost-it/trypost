<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    title?: string;
    breadcrumbs?: BreadcrumbItem[];
    fullWidth?: boolean;
};

withDefaults(defineProps<Props>(), {
    title: '',
    breadcrumbs: () => [],
    fullWidth: false,
});
</script>

<template>
    <AppLayout :title="title" :breadcrumbs="breadcrumbs" :full-width="fullWidth">
        <template v-if="$slots['header']" #header>
            <slot name="header" />
        </template>
        <template v-if="$slots['header-actions']" #header-actions>
            <slot name="header-actions" />
        </template>
        <slot />
    </AppLayout>
</template>
