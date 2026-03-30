<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Toaster } from '@/components/ui/sonner';

const style = computed(() => usePage().props.flash?.bannerStyle || 'success');
const message = computed(() => usePage().props.flash?.banner || '');

const showToast = (msg: string) => {
    switch (style.value) {
        case 'success':
            toast.success(msg);
            break;
        case 'danger':
            toast.error(msg);
            break;
        case 'warning':
            toast.warning(msg);
            break;
        case 'info':
            toast.info(msg);
            break;
        default:
            toast(msg);
    }
};

// Show flash on initial page load (after Toaster is mounted)
onMounted(() => {
    if (message.value) {
        showToast(message.value);
    }
});

// Show flash on SPA navigation (Inertia visits)
watch(message, (newMessage) => {
    if (!newMessage) {
        return;
    }

    showToast(newMessage);
});
</script>

<template>
    <Toaster />
</template>
