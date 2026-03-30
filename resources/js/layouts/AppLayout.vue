<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
    fullWidth?: boolean;
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    fullWidth: false,
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :full-width="fullWidth">
        <template v-if="$slots['header-right']" #header-right>
            <slot name="header-right" />
        </template>
        <slot />
    </AppLayout>
</template>
