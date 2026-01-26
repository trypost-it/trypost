<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { IconCreditCard, IconFileText, IconBuilding, IconExternalLink, IconSparkles } from '@tabler/icons-vue';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { portal } from '@/routes/billing';
import { type BreadcrumbItemType } from '@/types';

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

const breadcrumbs: BreadcrumbItemType[] = [
    {
        title: trans('billing.title'),
        href: '/settings/billing',
    },
];

function openPortal() {
    window.location.href = portal.url();
}

function getStatusLabel(status: string): string {
    return trans(`billing.status.${status}`) || status;
}

function getStatusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'active' || status === 'trialing') return 'default';
    if (status === 'canceled' || status === 'past_due' || status === 'unpaid') return 'destructive';
    return 'secondary';
}
</script>

<template>
    <Head :title="$t('billing.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">{{ $t('billing.title') }}</h1>
                <p class="text-muted-foreground">
                    {{ $t('billing.description') }}
                </p>
            </div>

            <Alert v-if="onTrial" class="border-primary/50 bg-primary/5">
                <IconSparkles class="h-4 w-4 text-primary" />
                <AlertTitle>{{ $t('billing.trial.title') }}</AlertTitle>
                <AlertDescription>
                    {{ $t('billing.trial.description', { date: trialEndsAt }) }}
                </AlertDescription>
            </Alert>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2">
                                <IconBuilding class="h-5 w-5" />
                                {{ $t('billing.subscription.title') }}
                            </CardTitle>
                            <Badge :variant="getStatusVariant(subscription?.stripe_status || '')">
                                {{ getStatusLabel(subscription?.stripe_status || '') }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex justify-between items-center p-4 bg-muted rounded-lg">
                            <div>
                                <p class="text-sm text-muted-foreground">{{ $t('billing.subscription.workspaces') }}</p>
                                <p class="text-2xl font-bold">{{ workspacesCount }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-muted-foreground">{{ $t('billing.subscription.quantity') }}</p>
                                <p class="text-2xl font-bold">{{ subscription?.quantity || 0 }}</p>
                            </div>
                        </div>

                        <div v-if="defaultPaymentMethod" class="flex items-center gap-3 p-3 border rounded-lg">
                            <IconCreditCard class="h-8 w-8 text-muted-foreground" />
                            <div>
                                <p class="font-medium capitalize">{{ defaultPaymentMethod.brand }} **** {{ defaultPaymentMethod.last4 }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ $t('billing.subscription.expires', { date: `${defaultPaymentMethod.exp_month}/${defaultPaymentMethod.exp_year}` }) }}
                                </p>
                            </div>
                        </div>

                        <div v-if="subscription?.ends_at" class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg dark:bg-yellow-950 dark:border-yellow-800">
                            <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                {{ $t('billing.subscription.canceled_on', { date: subscription.ends_at }) }}
                            </p>
                        </div>
                    </CardContent>
                    <CardFooter>
                        <Button @click="openPortal" variant="outline" class="w-full">
                            <IconExternalLink class="mr-2 h-4 w-4" />
                            {{ $t('billing.subscription.manage') }}
                        </Button>
                    </CardFooter>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <IconFileText class="h-5 w-5" />
                            {{ $t('billing.invoices.title') }}
                        </CardTitle>
                        <CardDescription>
                            {{ $t('billing.invoices.description') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="invoices.length === 0" class="text-center py-6 text-muted-foreground">
                            {{ $t('billing.invoices.empty') }}
                        </div>
                        <div v-else class="space-y-3">
                            <a
                                v-for="invoice in invoices"
                                :key="invoice.id"
                                :href="invoice.invoice_pdf"
                                target="_blank"
                                class="flex items-center justify-between p-3 border rounded-lg hover:bg-muted transition-colors"
                            >
                                <div>
                                    <p class="font-medium">{{ invoice.date }}</p>
                                    <p class="text-sm text-muted-foreground">{{ invoice.total }}</p>
                                </div>
                                <Badge variant="outline">
                                    {{ invoice.status === 'paid' ? $t('billing.invoices.paid') : invoice.status }}
                                </Badge>
                            </a>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
