<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

import AppHeader from '@/components/AppHeader.vue';
import AppSidebar from '@/components/AppSidebar.vue';
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
        <SidebarInset class="flex h-screen flex-col overflow-hidden">
            <AppHeader :breadcrumbs="$slots['header-left'] ? [] : breadcrumbs" :show-sidebar-trigger="!$slots['header-left']">
                <template v-if="$slots['header-left']" #left>
                    <slot name="header-left" />
                </template>
                <template v-if="$slots['header-center']" #center>
                    <slot name="header-center" />
                </template>
                <template v-if="$slots['header-right']" #right>
                    <slot name="header-right" />
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
