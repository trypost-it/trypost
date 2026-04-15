<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

import AppHeader from '@/components/AppHeader.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import Toast from '@/components/Toast.vue';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';

const page = usePage();
const isOpen = page.props.sidebarOpen;

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
    <SidebarProvider :default-open="isOpen">
        <AppSidebar />
        <SidebarInset class="overflow-x-hidden">
            <AppHeader v-if="$slots['header'] || $slots['header-actions'] || title" :title="$slots['header'] ? '' : title">
                <template v-if="$slots['header']" #left>
                    <slot name="header" />
                </template>
                <template v-if="$slots['header-actions']" #right>
                    <slot name="header-actions" />
                </template>
            </AppHeader>
            <div
                :class="
                    fullWidth
                        ? 'flex min-h-0 flex-1 flex-col overflow-y-auto'
                        : 'flex-1 overflow-y-auto'
                "
            >
                <div
                    :class="
                        fullWidth
                            ? 'flex min-h-0 flex-1 flex-col'
                            : 'mx-auto w-full max-w-7xl'
                    "
                >
                    <slot />
                </div>
            </div>
        </SidebarInset>
    </SidebarProvider>
    <Toast />
</template>
