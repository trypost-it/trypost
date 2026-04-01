<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSplitLayout.vue';

withDefaults(defineProps<{
    title?: string;
    description?: string;
    showLegal?: boolean;
}>(), {
    showLegal: false,
});
</script>

<template>
    <AuthLayout :title="title" :description="description" :show-legal="showLegal">
        <slot />
    </AuthLayout>
</template>
