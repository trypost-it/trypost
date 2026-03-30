<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import Toast from '@/components/Toast.vue';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

const page = usePage();
const isOpen = page.props.sidebarOpen;

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
    <SidebarProvider :default-open="isOpen">
        <AppSidebar />
        <SidebarInset class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs">
                <template v-if="$slots['header-right']" #right>
                    <slot name="header-right" />
                </template>
            </AppSidebarHeader>
            <div
                :class="
                    fullWidth
                        ? 'flex min-h-0 flex-1 flex-col'
                        : 'mx-auto w-full max-w-7xl'
                "
            >
                <slot />
            </div>
        </SidebarInset>
    </SidebarProvider>
    <Toast />
</template>
