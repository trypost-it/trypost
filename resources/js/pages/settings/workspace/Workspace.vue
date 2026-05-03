<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import SettingsTabsNav from '@/components/settings/SettingsTabsNav.vue';
import WorkspaceTab from '@/components/settings/WorkspaceTab.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as apiKeysRoute } from '@/routes/app/api-keys';
import { members as membersRoute } from '@/routes/app';
import { brand as brandRoute, settings as workspaceSettings } from '@/routes/app/workspace';
import type { BreadcrumbItem } from '@/types';

interface Workspace {
    id: string;
    name: string;
    has_logo: boolean;
    logo_url: string | null;
    brand_website: string | null;
    brand_description: string | null;
    brand_tone: string;
    brand_voice_notes: string | null;
    content_language: string;
}

defineProps<{
    workspace: Workspace;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('settings.workspace.title') },
]);

const tabs = computed(() => [
    { name: 'workspace', label: trans('settings.workspace.tabs.workspace'), href: workspaceSettings.url() },
    { name: 'brand', label: trans('settings.workspace.tabs.brand'), href: brandRoute.url() },
    { name: 'members', label: trans('settings.workspace.tabs.users'), href: membersRoute.url() },
    { name: 'api-keys', label: trans('settings.workspace.tabs.api_keys'), href: apiKeysRoute.url() },
]);
</script>

<template>
    <Head :title="$t('settings.workspace.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl space-y-6 px-4 py-6">
            <SettingsTabsNav :tabs="tabs" active="workspace" />

            <WorkspaceTab :workspace="workspace" />
        </div>
    </AppLayout>
</template>
