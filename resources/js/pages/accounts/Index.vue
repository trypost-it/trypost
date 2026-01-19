<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import SocialAccountsGrid, { type Platform } from '@/components/SocialAccountsGrid.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { accounts } from '@/routes';
import { disconnect as disconnectAccount } from '@/routes/accounts';
import { type BreadcrumbItemType } from '@/types';

interface Workspace {
    id: string;
    name: string;
}

interface Props {
    workspace: Workspace;
    platforms: Platform[];
}

defineProps<Props>();

const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Accounts', href: accounts.url() },
];

const handleDisconnect = (accountId: string) => {
    deleteModal.value?.open({
        url: disconnectAccount.url(accountId),
    });
};
</script>

<template>
    <Head title="Connected Accounts" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-8 p-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Connected Accounts</h1>
                <p class="text-muted-foreground">
                    Connect your social networks to schedule and publish posts
                </p>
            </div>

            <SocialAccountsGrid
                :platforms="platforms"
                @disconnect="handleDisconnect"
            />
        </div>
    </AppLayout>

    <ConfirmDeleteModal
        ref="deleteModal"
        title="Disconnect Account"
        description="Are you sure you want to disconnect this account? You can reconnect it at any time."
        action="Disconnect"
        cancel="Cancel"
    />
</template>
