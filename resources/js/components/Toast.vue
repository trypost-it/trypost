<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { onMounted, watch } from 'vue';
import { toast } from 'vue-sonner';

import { Toaster } from '@/components/ui/sonner';

interface Flash {
    banner?: string;
    bannerStyle?: 'success' | 'danger' | 'warning' | 'info';
    success?: string;
    error?: string;
    warning?: string;
    info?: string;
}

const page = usePage();

const showFlash = (flash: Flash | undefined) => {
    if (!flash) return;

    if (flash.banner) {
        switch (flash.bannerStyle) {
            case 'danger':
                toast.error(flash.banner);
                break;
            case 'warning':
                toast.warning(flash.banner);
                break;
            case 'info':
                toast.info(flash.banner);
                break;
            case 'success':
            default:
                toast.success(flash.banner);
        }
    }

    if (flash.success) toast.success(flash.success);
    if (flash.error) toast.error(flash.error);
    if (flash.warning) toast.warning(flash.warning);
    if (flash.info) toast.info(flash.info);
};

onMounted(() => {
    showFlash(page.props.flash as Flash | undefined);
});

watch(
    () => page.props.flash,
    (flash) => showFlash(flash as Flash | undefined),
    { deep: true },
);
</script>

<template>
    <Toaster />
</template>
