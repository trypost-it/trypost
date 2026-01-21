<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

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

const breadcrumbs = computed<BreadcrumbItemType[]>(() => [
    { title: trans('accounts.title'), href: accounts.url() },
]);

const handleDisconnect = (accountId: string) => {
    deleteModal.value?.open({
        url: disconnectAccount.url(accountId),
    });
};
</script>

<template>
    <Head :title="$t('accounts.page_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-8 p-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">{{ $t('accounts.page_title') }}</h1>
                <p class="text-muted-foreground">
                    {{ $t('accounts.description') }}
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
        :title="$t('accounts.disconnect_modal.title')"
        :description="$t('accounts.disconnect_modal.description')"
        :action="$t('accounts.disconnect_modal.confirm')"
        :cancel="$t('accounts.disconnect_modal.cancel')"
    />
</template>
