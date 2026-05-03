<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconDownload, IconFileText } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import SettingsTabsNav from '@/components/settings/SettingsTabsNav.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { settings as settingsHub } from '@/routes/app';
import { edit as accountEdit } from '@/routes/app/account';
import { index as billingIndex, portal } from '@/routes/app/billing';
import { index as usageIndex } from '@/routes/app/usage';
import type { BreadcrumbItem } from '@/types';

interface Plan {
    name: string;
    slug: string;
}

interface Subscription {
    stripe_status: string;
    ends_at: string | null;
}

interface PaymentMethod {
    brand: string;
    last4: string;
    exp_month: number;
    exp_year: number;
}

interface Invoice {
    id: string;
    date: string;
    total: string;
    status: string;
    invoice_pdf: string;
}

defineProps<{
    hasSubscription: boolean;
    onTrial: boolean;
    trialEndsAt: string | null;
    subscription: Subscription | null;
    plan: Plan | null;
    plans: Plan[];
    invoices: Invoice[];
    defaultPaymentMethod: PaymentMethod | null;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('settings.hub.title'), href: settingsHub().url },
    { title: trans('settings.account.title'), href: accountEdit().url },
    { title: trans('settings.account.tabs.billing') },
]);

const tabs = computed(() => [
    { name: 'account', label: trans('settings.account.tabs.account'), href: accountEdit().url },
    { name: 'usage', label: trans('settings.account.tabs.usage'), href: usageIndex().url },
    { name: 'billing', label: trans('settings.account.tabs.billing'), href: billingIndex().url },
]);

const monthlyPrice = (slug: string | undefined): string => {
    if (! slug) return 'Free';
    return trans(`billing.subscribe.prices.${slug}.monthly`);
};
</script>

<template>
    <Head :title="$t('billing.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl space-y-6 px-4 py-6">
            <SettingsTabsNav :tabs="tabs" active="billing" />

            <section class="space-y-12">
                <div class="grid grid-cols-1 gap-8 md:grid-cols-[280px_1fr] md:gap-16">
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight">{{ $t('billing.plan.title') }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ $t('billing.plan.description') }}
                        </p>
                        <Button
                            variant="outline"
                            size="sm"
                            class="mt-4"
                        >
                            {{ $t('billing.plan.change') }}
                        </Button>
                    </div>

                    <div class="divide-y">
                        <div class="flex items-center gap-3 py-3">
                            <span class="flex items-center gap-2 text-sm">
                                <span>{{ $t('billing.plan.label') }}</span>
                                <Badge v-if="onTrial" variant="secondary">{{ $t('billing.plan.trial') }}</Badge>
                                <Badge v-else-if="subscription?.stripe_status === 'active'" variant="default">{{ $t('billing.plan.active') }}</Badge>
                                <Badge v-else-if="subscription?.stripe_status === 'past_due'" variant="destructive">{{ $t('billing.plan.past_due') }}</Badge>
                                <Badge v-else-if="subscription?.ends_at" variant="secondary">{{ $t('billing.plan.cancelling') }}</Badge>
                            </span>
                            <span class="ml-auto text-sm font-medium">{{ plan?.name ?? 'No plan' }}</span>
                        </div>

                        <div class="flex items-center gap-3 py-3">
                            <span class="text-sm">{{ $t('billing.plan.price') }}</span>
                            <span class="ml-auto text-sm font-medium tabular-nums">
                                {{ monthlyPrice(plan?.slug) }}/{{ $t('billing.plan.month') }}
                            </span>
                        </div>

                        <div v-if="onTrial && trialEndsAt" class="flex items-center gap-3 py-3">
                            <span class="text-sm">{{ $t('billing.plan.trial_ends') }}</span>
                            <span class="ml-auto text-sm font-medium">{{ trialEndsAt }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="hasSubscription" class="grid grid-cols-1 gap-8 md:grid-cols-[280px_1fr] md:gap-16">
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight">{{ $t('billing.subscription.title') }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ $t('billing.subscription.description') }}
                        </p>
                    </div>

                    <div class="divide-y">
                        <div v-if="defaultPaymentMethod" class="flex items-center gap-3 py-3">
                            <span class="text-sm">{{ $t('billing.subscription.payment_method') }}</span>
                            <span class="ml-auto text-sm font-medium capitalize">
                                {{ defaultPaymentMethod.brand }} **** {{ defaultPaymentMethod.last4 }}
                                <span class="text-muted-foreground">
                                    ({{ defaultPaymentMethod.exp_month }}/{{ defaultPaymentMethod.exp_year }})
                                </span>
                            </span>
                        </div>

                        <div class="flex items-center gap-3 py-3">
                            <span class="text-sm">{{ $t('billing.subscription.manage_label') }}</span>
                            <a :href="portal.url()" class="ml-auto">
                                <Button variant="outline" size="sm">
                                    {{ $t('billing.subscription.manage_stripe') }}
                                </Button>
                            </a>
                        </div>
                    </div>
                </div>

                <div v-if="invoices.length > 0" class="grid grid-cols-1 gap-8 md:grid-cols-[280px_1fr] md:gap-16">
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight">{{ $t('billing.invoices.title') }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ $t('billing.invoices.description') }}
                        </p>
                    </div>

                    <div class="divide-y">
                        <div
                            v-for="invoice in invoices"
                            :key="invoice.id"
                            class="flex items-center gap-3 py-3"
                        >
                            <IconFileText class="size-4 shrink-0 text-muted-foreground" />
                            <div class="min-w-0 flex-1">
                                <span class="text-sm">{{ invoice.date }}</span>
                                <span class="ml-2 text-sm text-muted-foreground">{{ invoice.total }}</span>
                            </div>
                            <Badge variant="outline">
                                {{ invoice.status === 'paid' ? $t('billing.invoices.paid') : invoice.status }}
                            </Badge>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8"
                                as="a"
                                :href="invoice.invoice_pdf"
                                target="_blank"
                            >
                                <IconDownload class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
