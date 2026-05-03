<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { IconBuildingCommunity, IconChevronRight, IconCreditCard, IconUser } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { edit as accountEdit } from '@/routes/app/account';
import { edit as profileEdit } from '@/routes/app/profile';
import { settings as workspaceSettings } from '@/routes/app/workspace';
import type { BreadcrumbItem } from '@/types';

interface Permissions {
    canManageProfile: boolean;
    canManageWorkspace: boolean;
    canManageAccount: boolean;
}

defineProps<{
    permissions: Permissions;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('settings.hub.title') },
]);
</script>

<template>
    <Head :title="$t('settings.hub.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl space-y-6 px-4 py-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ $t('settings.hub.title') }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ $t('settings.hub.description') }}
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-if="permissions.canManageProfile"
                    :href="profileEdit().url"
                    class="group flex flex-col gap-4 rounded-xl border bg-card p-5 transition hover:border-foreground/30 hover:shadow-sm"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex size-10 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                            <IconUser class="size-5" />
                        </div>
                        <IconChevronRight class="size-4 text-muted-foreground transition group-hover:translate-x-0.5 group-hover:text-foreground" />
                    </div>
                    <div>
                        <h2 class="text-base font-medium">{{ $t('settings.hub.profile.title') }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ $t('settings.hub.profile.description') }}
                        </p>
                    </div>
                </Link>

                <Link
                    v-if="permissions.canManageWorkspace"
                    :href="workspaceSettings().url"
                    class="group flex flex-col gap-4 rounded-xl border bg-card p-5 transition hover:border-foreground/30 hover:shadow-sm"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex size-10 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                            <IconBuildingCommunity class="size-5" />
                        </div>
                        <IconChevronRight class="size-4 text-muted-foreground transition group-hover:translate-x-0.5 group-hover:text-foreground" />
                    </div>
                    <div>
                        <h2 class="text-base font-medium">{{ $t('settings.hub.workspace.title') }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ $t('settings.hub.workspace.description') }}
                        </p>
                    </div>
                </Link>

                <Link
                    v-if="permissions.canManageAccount"
                    :href="accountEdit().url"
                    class="group flex flex-col gap-4 rounded-xl border bg-card p-5 transition hover:border-foreground/30 hover:shadow-sm"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex size-10 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                            <IconCreditCard class="size-5" />
                        </div>
                        <IconChevronRight class="size-4 text-muted-foreground transition group-hover:translate-x-0.5 group-hover:text-foreground" />
                    </div>
                    <div>
                        <h2 class="text-base font-medium">{{ $t('settings.hub.account.title') }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ $t('settings.hub.account.description') }}
                        </p>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
