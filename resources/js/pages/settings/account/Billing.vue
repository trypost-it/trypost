<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    IconCreditCard,
    IconDownload,
    IconFileText,
    IconSparkles,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import PageHeader from '@/components/PageHeader.vue';
import SettingsTabsNav from '@/components/settings/SettingsTabsNav.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useUpgradeDialog } from '@/composables/useUpgradeDialog';
import date from '@/date';
import AppLayout from '@/layouts/AppLayout.vue';
import { edit as accountEdit } from '@/routes/app/account';
import { index as billingIndex, portal } from '@/routes/app/billing';
import { index as usageIndex } from '@/routes/app/usage';
import type { AuthPlan } from '@/types';

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

const tabs = computed(() => [
    {
        name: 'account',
        label: trans('settings.account.tabs.account'),
        href: accountEdit().url,
    },
    {
        name: 'usage',
        label: trans('settings.account.tabs.usage'),
        href: usageIndex().url,
    },
    {
        name: 'billing',
        label: trans('settings.account.tabs.billing'),
        href: billingIndex().url,
    },
]);

const page = usePage();
const authPlan = computed<AuthPlan | null>(
    () => (page.props.auth as { plan: AuthPlan | null }).plan ?? null,
);
const isYearly = computed(() => authPlan.value?.interval === 'yearly');

const displayPrice = (slug: string | undefined): string => {
    if (!slug) return 'Free';
    const key = isYearly.value ? 'yearly_per_month' : 'monthly';
    return trans(`billing.subscribe.prices.${slug}.${key}`);
};

const { openUpgrade } = useUpgradeDialog();
</script>

<template>
    <Head :title="$t('billing.title')" />

    <AppLayout>
        <div class="mx-auto max-w-4xl space-y-8 px-6 py-8">
            <PageHeader
                :title="$t('settings.hub.title')"
                :description="$t('settings.hub.description')"
            />

            <SettingsTabsNav :tabs="tabs" active="billing" />

            <section class="space-y-12">
                <!-- ───── Current plan hero card ───── -->
                <div class="space-y-6">
                    <HeadingSmall
                        :title="$t('billing.plan.title')"
                        :description="$t('billing.plan.description')"
                    />

                    <div
                        class="rounded-2xl border-2 border-foreground bg-card p-6 shadow-2xs"
                    >
                        <div class="flex items-start justify-between gap-6">
                            <div class="space-y-2">
                                <p
                                    class="text-[11px] font-black tracking-widest text-foreground/60 uppercase"
                                >
                                    {{ $t('billing.plan.label') }}
                                </p>
                                <div class="flex flex-wrap items-center gap-3">
                                    <h3
                                        class="text-3xl leading-tight font-semibold text-foreground"
                                        style="font-family: var(--font-display)"
                                    >
                                        {{ plan?.name ?? 'No plan' }}
                                    </h3>
                                    <Badge v-if="onTrial" variant="secondary">{{
                                        $t('billing.plan.trial')
                                    }}</Badge>
                                    <Badge
                                        v-else-if="
                                            subscription?.stripe_status ===
                                            'active'
                                        "
                                        variant="success"
                                        >{{ $t('billing.plan.active') }}</Badge
                                    >
                                    <Badge
                                        v-else-if="
                                            subscription?.stripe_status ===
                                            'past_due'
                                        "
                                        variant="destructive"
                                        >{{
                                            $t('billing.plan.past_due')
                                        }}</Badge
                                    >
                                    <Badge
                                        v-else-if="subscription?.ends_at"
                                        variant="secondary"
                                        >{{
                                            $t('billing.plan.cancelling')
                                        }}</Badge
                                    >
                                </div>
                                <p class="text-base text-foreground/70">
                                    <span
                                        class="text-2xl font-bold text-foreground tabular-nums"
                                        >{{ displayPrice(plan?.slug) }}</span
                                    >
                                    <span class="ml-1"
                                        >/{{ $t('billing.plan.month') }}</span
                                    >
                                </p>
                                <p
                                    v-if="plan"
                                    class="text-xs font-medium text-foreground/60"
                                >
                                    {{
                                        isYearly
                                            ? $t(
                                                  'billing.subscribe.billed_yearly',
                                              )
                                            : $t(
                                                  'billing.subscribe.billed_monthly',
                                              )
                                    }}
                                </p>
                                <p
                                    v-if="onTrial && trialEndsAt"
                                    class="text-sm font-semibold text-foreground/70"
                                >
                                    {{ $t('billing.plan.trial_ends') }}:
                                    <span class="text-foreground">{{
                                        date.formatDate(trialEndsAt)
                                    }}</span>
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-4">
                                <span
                                    class="inline-flex size-14 -rotate-3 items-center justify-center rounded-2xl border-2 border-foreground bg-amber-200 shadow-2xs"
                                >
                                    <IconSparkles
                                        class="size-7 text-foreground"
                                        stroke-width="2"
                                    />
                                </span>
                                <Button @click="openUpgrade()">
                                    {{ $t('billing.plan.change') }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ───── Payment method ───── -->
                <div v-if="hasSubscription" class="space-y-6">
                    <HeadingSmall
                        :title="$t('billing.subscription.title')"
                        :description="$t('billing.subscription.description')"
                    />

                    <div
                        class="flex flex-wrap items-center gap-4 rounded-2xl border-2 border-foreground bg-card p-4 shadow-2xs"
                    >
                        <span
                            class="inline-flex size-12 rotate-2 items-center justify-center rounded-2xl border-2 border-foreground bg-violet-200 shadow-2xs"
                        >
                            <IconCreditCard
                                class="size-6 text-foreground"
                                stroke-width="2"
                            />
                        </span>
                        <div v-if="defaultPaymentMethod" class="min-w-0 flex-1">
                            <p
                                class="text-[11px] font-black tracking-widest text-foreground/60 uppercase"
                            >
                                {{ $t('billing.subscription.payment_method') }}
                            </p>
                            <p
                                class="text-base font-bold text-foreground capitalize"
                            >
                                {{ defaultPaymentMethod.brand }} ••••
                                {{ defaultPaymentMethod.last4 }}
                            </p>
                            <p class="text-xs font-medium text-foreground/60">
                                {{
                                    $t('billing.subscription.expires_on', {
                                        month: defaultPaymentMethod.exp_month
                                            .toString()
                                            .padStart(2, '0'),
                                        year: defaultPaymentMethod.exp_year.toString(),
                                    })
                                }}
                            </p>
                        </div>
                        <div v-else class="min-w-0 flex-1">
                            <p
                                class="text-[11px] font-black tracking-widest text-foreground/60 uppercase"
                            >
                                {{ $t('billing.subscription.payment_method') }}
                            </p>
                            <p class="text-sm font-semibold text-foreground/70">
                                {{
                                    $t('billing.subscription.no_payment_method')
                                }}
                            </p>
                        </div>
                        <Button as="a" :href="portal.url()" class="shrink-0">
                            {{ $t('billing.subscription.manage_stripe') }}
                        </Button>
                    </div>
                </div>

                <!-- ───── Invoices ───── -->
                <div v-if="invoices.length > 0" class="space-y-6">
                    <HeadingSmall
                        :title="$t('billing.invoices.title')"
                        :description="$t('billing.invoices.description')"
                    />

                    <div class="space-y-3">
                        <div
                            v-for="invoice in invoices"
                            :key="invoice.id"
                            class="flex items-center gap-4 rounded-xl border-2 border-foreground bg-card p-4 shadow-2xs"
                        >
                            <span
                                class="inline-flex size-10 -rotate-2 items-center justify-center rounded-2xl border-2 border-foreground bg-violet-100 shadow-2xs"
                            >
                                <IconFileText
                                    class="size-5 text-foreground"
                                    stroke-width="2"
                                />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-foreground">
                                    {{ date.formatDate(invoice.date) }}
                                </p>
                                <p
                                    class="text-xs font-medium text-foreground/60 tabular-nums"
                                >
                                    {{ invoice.total }}
                                </p>
                            </div>
                            <Badge
                                :variant="
                                    invoice.status === 'paid'
                                        ? 'success'
                                        : 'outline'
                                "
                            >
                                {{
                                    invoice.status === 'paid'
                                        ? $t('billing.invoices.paid')
                                        : invoice.status
                                }}
                            </Badge>
                            <Button
                                variant="outline"
                                size="icon"
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
