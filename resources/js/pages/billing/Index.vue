<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconCreditCard, IconDownload, IconExternalLink, IconFileText, IconSparkles } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { index as billingIndex, portal } from '@/routes/app/billing';
import { type BreadcrumbItem } from '@/types';

interface Subscription {
    stripe_status: string;
    quantity: number;
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

interface Props {
    hasSubscription: boolean;
    onTrial: boolean;
    trialEndsAt: string | null;
    subscription: Subscription | null;
    workspacesCount: number;
    invoices: Invoice[];
    defaultPaymentMethod: PaymentMethod | null;
}

defineProps<Props>();

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    { title: trans('settings.title'), href: billingIndex.url() },
    { title: trans('billing.title'), href: billingIndex.url() },
]);

const openPortal = () => {
    window.location.href = portal.url();
};

const getStatusLabel = (status: string): string => {
    return trans(`billing.status.${status}`) || status;
};

const getStatusVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'active' || status === 'trialing') return 'default';
    if (status === 'canceled' || status === 'past_due' || status === 'unpaid') return 'destructive';
    return 'secondary';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="$t('billing.title')" />

        <h1 class="sr-only">{{ $t('billing.title') }}</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    :title="$t('billing.subscription.title')"
                    :description="$t('billing.description')"
                />

                <Alert v-if="onTrial" class="border-primary/50 bg-primary/5">
                    <IconSparkles class="h-4 w-4 text-primary" />
                    <AlertTitle>{{ $t('billing.trial.title') }}</AlertTitle>
                    <AlertDescription>
                        {{ $t('billing.trial.description', { date: trialEndsAt ?? '' }) }}
                    </AlertDescription>
                </Alert>

                <div class="space-y-4">
                    <div class="flex items-center justify-between rounded-lg border p-4">
                        <div>
                            <p class="text-sm text-muted-foreground">{{ $t('billing.subscription.status') }}</p>
                            <Badge :variant="getStatusVariant(subscription?.stripe_status || '')" class="mt-1">
                                {{ getStatusLabel(subscription?.stripe_status || '') }}
                            </Badge>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-muted-foreground">{{ $t('billing.subscription.workspaces') }}</p>
                            <p class="text-lg font-semibold">{{ workspacesCount }} / {{ subscription?.quantity || 0 }}</p>
                        </div>
                    </div>

                    <div v-if="defaultPaymentMethod" class="flex items-center gap-3 rounded-lg border p-4">
                        <IconCreditCard class="h-6 w-6 text-muted-foreground" />
                        <div>
                            <p class="text-sm font-medium capitalize">{{ defaultPaymentMethod.brand }} **** {{ defaultPaymentMethod.last4 }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ $t('billing.subscription.expires', { date: `${defaultPaymentMethod.exp_month}/${defaultPaymentMethod.exp_year}` }) }}
                            </p>
                        </div>
                    </div>

                    <div v-if="subscription?.ends_at" class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-950">
                        <p class="text-sm text-yellow-800 dark:text-yellow-200">
                            {{ $t('billing.subscription.canceled_on', { date: subscription.ends_at }) }}
                        </p>
                    </div>

                    <Button variant="outline" @click="openPortal">
                        <IconExternalLink class="h-4 w-4" />
                        {{ $t('billing.subscription.manage') }}
                    </Button>
                </div>
            </div>

            <Separator />

            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    :title="$t('billing.invoices.title')"
                    :description="$t('billing.invoices.description')"
                />

                <div v-if="invoices.length === 0" class="text-sm text-muted-foreground">
                    {{ $t('billing.invoices.empty') }}
                </div>

                <div v-else class="space-y-2">
                    <div
                        v-for="invoice in invoices"
                        :key="invoice.id"
                        class="flex items-center justify-between rounded-lg border p-3"
                    >
                        <div class="flex items-center gap-3">
                            <IconFileText class="h-4 w-4 text-muted-foreground" />
                            <div>
                                <p class="text-sm font-medium">{{ invoice.date }}</p>
                                <p class="text-xs text-muted-foreground">{{ invoice.total }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge variant="outline">
                                {{ invoice.status === 'paid' ? $t('billing.invoices.paid') : invoice.status }}
                            </Badge>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8"
                                as="a"
                                :href="invoice.invoice_pdf"
                                target="_blank"
                            >
                                <IconDownload class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
