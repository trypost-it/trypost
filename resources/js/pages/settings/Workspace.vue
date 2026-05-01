<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import BrandTab from '@/components/settings/BrandTab.vue';
import UsersTab from '@/components/settings/UsersTab.vue';
import WorkspaceTab from '@/components/settings/WorkspaceTab.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';

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

interface Member {
    id: string;
    name: string;
    email: string;
    role: string;
}

interface Invitation {
    id: string;
    email: string;
    role: string;
}

defineProps<{
    workspace: Workspace;
    members: Member[];
    invitations: Invitation[];
}>();
</script>

<template>
    <AppLayout :title="$t('settings.workspace.title')">
        <Head :title="$t('settings.workspace.title')" />

        <h1 class="sr-only">{{ $t('settings.workspace.title') }}</h1>

        <div class="mx-auto max-w-4xl px-4 py-6">
            <Tabs default-value="workspace">
                <TabsList>
                    <TabsTrigger value="workspace">{{ $t('settings.workspace.tabs.workspace') }}</TabsTrigger>
                    <TabsTrigger value="brand">{{ $t('settings.workspace.tabs.brand') }}</TabsTrigger>
                    <TabsTrigger value="users">{{ $t('settings.workspace.tabs.users') }}</TabsTrigger>
                </TabsList>

                <TabsContent value="workspace" class="mt-6">
                    <WorkspaceTab :workspace="workspace" />
                </TabsContent>

                <TabsContent value="brand" class="mt-6">
                    <BrandTab :workspace="workspace" />
                </TabsContent>

                <TabsContent value="users" class="mt-6">
                    <UsersTab :members="members" :invitations="invitations" />
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
