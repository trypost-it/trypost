<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

import AnalyticsSidebar, { type AnalyticsAccount } from '@/components/analytics/AnalyticsSidebar.vue';
import FacebookAnalytics from '@/components/analytics/FacebookAnalytics.vue';
import InstagramAnalytics from '@/components/analytics/InstagramAnalytics.vue';
import LinkedInPageAnalytics from '@/components/analytics/LinkedInPageAnalytics.vue';
import PinterestAnalytics from '@/components/analytics/PinterestAnalytics.vue';
import ThreadsAnalytics from '@/components/analytics/ThreadsAnalytics.vue';
import TikTokAnalytics from '@/components/analytics/TikTokAnalytics.vue';
import XAnalytics from '@/components/analytics/XAnalytics.vue';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import dayjs from '@/dayjs';
import AppLayout from '@/layouts/AppLayout.vue';
import { analytics } from '@/routes/app';
import { type BreadcrumbItemType } from '@/types';

const props = defineProps<{
    accounts: AnalyticsAccount[];
}>();

const breadcrumbs = computed<BreadcrumbItemType[]>(() => [
    { title: trans('sidebar.analytics'), href: analytics.url() },
]);

const selectedAccountId = ref<string | null>(props.accounts[0]?.id ?? null);

const dateRange = ref({
    start: dayjs().subtract(6, 'day').toDate(),
    end: dayjs().toDate(),
});

const selectedAccount = computed(() =>
    props.accounts.find((a) => a.id === selectedAccountId.value),
);

const platformSupportsDateRange = computed(() => {
    if (!selectedAccount.value) return false;
    return ['instagram', 'instagram-facebook', 'facebook', 'youtube', 'pinterest', 'threads', 'x', 'linkedin-page'].includes(selectedAccount.value.platform);
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :full-width="true">
        <template #header-right>
            <DateRangePicker v-if="platformSupportsDateRange" v-model="dateRange" />
        </template>

        <Head :title="trans('sidebar.analytics')" />

        <div class="flex h-full">
            <AnalyticsSidebar
                :accounts="accounts"
                :selected-id="selectedAccountId"
                @select="selectedAccountId = $event"
            />

            <div class="flex min-w-0 flex-1 flex-col">
                <div class="flex-1 overflow-y-auto p-6">
                    <div v-if="!selectedAccountId" class="flex h-full items-center justify-center text-muted-foreground">
                        {{ $t('analytics.select_account') }}
                    </div>

                    <TikTokAnalytics
                        v-else-if="selectedAccount?.platform === 'tiktok'"
                        :account-id="selectedAccountId"
                    />

                    <InstagramAnalytics
                        v-else-if="selectedAccount?.platform === 'instagram' || selectedAccount?.platform === 'instagram-facebook'"
                        :account-id="selectedAccountId"
                        :date-range="dateRange"
                    />

                    <ThreadsAnalytics
                        v-else-if="selectedAccount?.platform === 'threads'"
                        :account-id="selectedAccountId"
                        :date-range="dateRange"
                    />

                    <FacebookAnalytics
                        v-else-if="selectedAccount?.platform === 'facebook'"
                        :account-id="selectedAccountId"
                        :date-range="dateRange"
                    />

                    <XAnalytics
                        v-else-if="selectedAccount?.platform === 'x'"
                        :account-id="selectedAccountId"
                        :date-range="dateRange"
                    />

                    <LinkedInPageAnalytics
                        v-else-if="selectedAccount?.platform === 'linkedin-page'"
                        :account-id="selectedAccountId"
                        :date-range="dateRange"
                    />

                    <PinterestAnalytics
                        v-else-if="selectedAccount?.platform === 'pinterest'"
                        :account-id="selectedAccountId"
                        :date-range="dateRange"
                    />

                    <div v-else class="flex h-full items-center justify-center text-muted-foreground">
                        {{ $t('analytics.no_data') }}
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
