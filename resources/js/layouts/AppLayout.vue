<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

type Props = {
    title?: string;
    fullWidth?: boolean;
};

withDefaults(defineProps<Props>(), {
    title: '',
    fullWidth: false,
});
</script>

<template>
    <AppLayout :title="title" :full-width="fullWidth">
        <template v-if="$slots['header']" #header>
            <slot name="header" />
        </template>
        <template v-if="$slots['header-actions']" #header-actions>
            <slot name="header-actions" />
        </template>
        <slot />
    </AppLayout>
</template>
