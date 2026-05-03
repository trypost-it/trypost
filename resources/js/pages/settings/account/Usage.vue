<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import SettingsTabsNav from '@/components/settings/SettingsTabsNav.vue';
import UsageMetricRow from '@/components/settings/UsageMetricRow.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { settings as settingsHub } from '@/routes/app';
import { edit as accountEdit } from '@/routes/app/account';
import { index as billingIndex } from '@/routes/app/billing';
import { index as usageIndex } from '@/routes/app/usage';
import type { BreadcrumbItem } from '@/types';

interface Plan {
    name: string;
    slug: string;
}

interface UsageData {
    workspaceCount: number;
    workspaceLimit: number;
    socialAccountCount: number;
    socialAccountLimit: number;
    memberCount: number;
    memberLimit: number;
    aiImagesUsed: number;
    aiImagesLimit: number;
    aiTextUsed: number;
}

defineProps<{
    plan: Plan | null;
    usage: UsageData;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('settings.hub.title'), href: settingsHub().url },
    { title: trans('settings.account.title'), href: accountEdit().url },
    { title: trans('settings.account.tabs.usage') },
]);

const tabs = computed(() => [
    { name: 'account', label: trans('settings.account.tabs.account'), href: accountEdit().url },
    { name: 'usage', label: trans('settings.account.tabs.usage'), href: usageIndex().url },
    { name: 'billing', label: trans('settings.account.tabs.billing'), href: billingIndex().url },
]);
</script>

<template>
    <Head :title="$t('usage.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl space-y-6 px-4 py-6">
            <SettingsTabsNav :tabs="tabs" active="usage" />

            <section class="space-y-12">
                <div class="grid grid-cols-1 gap-8 md:grid-cols-[280px_1fr] md:gap-16">
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight">{{ $t('usage.section_account') }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ $t('usage.section_account_description', { plan: plan?.name ?? 'Free' }) }}
                        </p>
                    </div>

                    <div class="divide-y">
                        <UsageMetricRow
                            :label="$t('usage.workspaces')"
                            :current="usage.workspaceCount"
                            :limit="usage.workspaceLimit"
                        />

                        <UsageMetricRow
                            :label="$t('usage.social_accounts')"
                            :current="usage.socialAccountCount"
                            :limit="usage.socialAccountLimit"
                        />

                        <UsageMetricRow
                            :label="$t('usage.members')"
                            :current="usage.memberCount"
                            :limit="usage.memberLimit"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-8 md:grid-cols-[280px_1fr] md:gap-16">
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight">{{ $t('usage.section_ai') }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ $t('usage.section_ai_description') }}
                        </p>
                    </div>

                    <div class="divide-y">
                        <UsageMetricRow
                            :label="$t('usage.ai_images')"
                            :current="usage.aiImagesUsed"
                            :limit="usage.aiImagesLimit"
                        />

                        <UsageMetricRow
                            :label="$t('usage.ai_text')"
                            :current="usage.aiTextUsed"
                        />
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
