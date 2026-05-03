<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import BrandTab from '@/components/settings/BrandTab.vue';
import SettingsTabsNav from '@/components/settings/SettingsTabsNav.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as apiKeysRoute } from '@/routes/app/api-keys';
import { members as membersRoute, settings as settingsHub } from '@/routes/app';
import { brand as brandRoute, settings as workspaceSettings } from '@/routes/app/workspace';
import type { BreadcrumbItem } from '@/types';

interface Workspace {
    id: string;
    name: string;
    brand_website: string | null;
    brand_description: string | null;
    brand_tone: string;
    brand_voice_notes: string | null;
    brand_color: string | null;
    background_color: string | null;
    text_color: string | null;
    brand_font: string;
    content_language: string;
}

defineProps<{
    workspace: Workspace;
    availableFonts: string[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('settings.hub.title'), href: settingsHub().url },
    { title: trans('settings.workspace.title'), href: workspaceSettings.url() },
    { title: trans('settings.workspace.tabs.brand') },
]);

const tabs = computed(() => [
    { name: 'workspace', label: trans('settings.workspace.tabs.workspace'), href: workspaceSettings.url() },
    { name: 'brand', label: trans('settings.workspace.tabs.brand'), href: brandRoute.url() },
    { name: 'members', label: trans('settings.workspace.tabs.users'), href: membersRoute.url() },
    { name: 'api-keys', label: trans('settings.workspace.tabs.api_keys'), href: apiKeysRoute.url() },
]);
</script>

<template>
    <Head :title="$t('settings.workspace.tabs.brand')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl space-y-6 px-4 py-6">
            <SettingsTabsNav :tabs="tabs" active="brand" />

            <BrandTab :workspace="workspace" :available-fonts="availableFonts" />
        </div>
    </AppLayout>
</template>
