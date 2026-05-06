<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

type Props = {
    fullWidth?: boolean;
};

withDefaults(defineProps<Props>(), {
    fullWidth: false,
});
</script>

<template>
    <AppLayout :full-width="fullWidth">
        <template v-if="$slots['header']" #header>
            <slot name="header" />
        </template>
        <template v-if="$slots['header-actions']" #header-actions>
            <slot name="header-actions" />
        </template>
        <slot />
    </AppLayout>
</template>
