<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import SettingsTabsNav from '@/components/settings/SettingsTabsNav.vue';
import UsersTab from '@/components/settings/UsersTab.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { members as membersRoute, settings as settingsHub } from '@/routes/app';
import { index as apiKeysRoute } from '@/routes/app/api-keys';
import { brand as brandRoute, settings as workspaceSettings } from '@/routes/app/workspace';
import type { BreadcrumbItem } from '@/types';

interface Workspace {
    id: string;
    name: string;
}

interface Member {
    id: string;
    name: string;
    email: string;
    role: string;
}

interface Invite {
    id: string;
    email: string;
    role: string;
}

interface Role {
    value: string;
    label: string;
}

defineProps<{
    workspace: Workspace;
    owner: Member;
    members: Member[];
    invites: Invite[];
    roles: Role[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('settings.hub.title'), href: settingsHub().url },
    { title: trans('settings.workspace.title'), href: workspaceSettings.url() },
    { title: trans('settings.workspace.tabs.users') },
]);

const tabs = computed(() => [
    { name: 'workspace', label: trans('settings.workspace.tabs.workspace'), href: workspaceSettings.url() },
    { name: 'brand', label: trans('settings.workspace.tabs.brand'), href: brandRoute.url() },
    { name: 'members', label: trans('settings.workspace.tabs.users'), href: membersRoute.url() },
    { name: 'api-keys', label: trans('settings.workspace.tabs.api_keys'), href: apiKeysRoute.url() },
]);
</script>

<template>
    <Head :title="$t('settings.members.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl space-y-8 px-4 py-8">
            <SettingsTabsNav :tabs="tabs" active="members" />

            <UsersTab :members="members" :invitations="invites" />
        </div>
    </AppLayout>
</template>
