<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { IconCheck } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { checkout } from '@/routes/app/billing';

interface Plan {
    id: string;
    slug: string;
    name: string;
    stripe_monthly_price_id: string | null;
    stripe_yearly_price_id: string | null;
    monthly_price: number;
    yearly_price: number;
    social_account_limit: number;
    member_limit: number;
    workspace_limit: number;
    ai_images_limit: number;
    ai_videos_limit: number;
    data_retention_days: number;
}

const props = defineProps<{
    plans: Plan[];
    trialDays: number;
}>();

const isYearly = ref(false);
const processing = ref<string | null>(null);

const formatPrice = (cents: number): string => {
    return '$' + (cents / 100).toFixed(0);
};

const getPrice = (plan: Plan): string => {
    return formatPrice(isYearly.value ? plan.yearly_price / 12 : plan.monthly_price);
};

const getTotalPrice = (plan: Plan): string => {
    return formatPrice(isYearly.value ? plan.yearly_price : plan.monthly_price);
};

const getSavings = (plan: Plan): number => {
    const monthlyTotal = plan.monthly_price * 12;
    const yearlyTotal = plan.yearly_price;
    return Math.round(((monthlyTotal - yearlyTotal) / monthlyTotal) * 100);
};

const formatRetention = (days: number): string => {
    if (days >= 730) return '2 years';
    return `${days} days`;
};

const selectPlan = (plan: Plan) => {
    processing.value = plan.id;
    const priceId = isYearly.value ? plan.stripe_yearly_price_id : plan.stripe_monthly_price_id;
    router.post(checkout.url(plan.id), {
        price_id: priceId,
    });
};

const features = (plan: Plan): string[] => [
    trans('billing.subscribe.features.social_accounts', { count: String(plan.social_account_limit) }),
    trans('billing.subscribe.features.workspaces', { count: String(plan.workspace_limit) }),
    trans('billing.subscribe.features.members', { count: String(plan.member_limit) }),
    trans('billing.subscribe.features.ai_images', { count: String(plan.ai_images_limit) }),
    trans('billing.subscribe.features.ai_videos', { count: String(plan.ai_videos_limit) }),
    trans('billing.subscribe.features.data_retention', { days: formatRetention(plan.data_retention_days) }),
];

const isPopular = (plan: Plan): boolean => {
    return plan.slug === 'pro';
};
</script>

<template>
    <Head :title="$t('billing.subscribe.page_title')" />

    <div class="min-h-screen bg-background">
        <!-- Top bar -->
        <div class="border-b">
            <div class="mx-auto flex max-w-7xl items-center px-6 py-4">
                <img
                    src="/images/trypost/logo-light.png"
                    alt="TryPost"
                    class="h-7 w-auto dark:hidden"
                />
                <img
                    src="/images/trypost/logo-dark.png"
                    alt="TryPost"
                    class="hidden h-7 w-auto dark:block"
                />
            </div>
        </div>

        <!-- Content -->
        <div class="mx-auto max-w-7xl px-6 py-16">
            <!-- Header -->
            <div class="mb-12 text-center">
                <h1 class="text-4xl font-bold tracking-tight">
                    {{ $t('billing.subscribe.title') }}
                </h1>
                <p class="mt-3 text-lg text-muted-foreground">
                    {{ trans('billing.subscribe.description', { days: String(trialDays) }) }}
                </p>
            </div>

            <!-- Billing toggle -->
            <div class="mb-10 flex items-center justify-center gap-3">
                <span class="text-sm" :class="!isYearly ? 'font-medium' : 'text-muted-foreground'">
                    {{ $t('billing.subscribe.monthly') }}
                </span>
                <Switch v-model="isYearly" />
                <span class="flex items-center gap-2 text-sm" :class="isYearly ? 'font-medium' : 'text-muted-foreground'">
                    {{ $t('billing.subscribe.yearly') }}
                    <Badge variant="secondary" class="whitespace-nowrap">
                        {{ $t('billing.subscribe.save_months') }}
                    </Badge>
                </span>
            </div>

            <!-- Plan cards -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="plan in plans"
                    :key="plan.id"
                    class="relative flex flex-col rounded-xl border bg-card p-6 transition-shadow hover:shadow-lg"
                    :class="isPopular(plan) ? 'border-primary ring-1 ring-primary' : ''"
                >
                    <Badge v-if="isPopular(plan)" class="absolute -top-3 left-1/2 -translate-x-1/2">
                        {{ $t('billing.subscribe.popular') }}
                    </Badge>

                    <div class="mb-6">
                        <h3 class="text-xl font-semibold">{{ plan.name }}</h3>
                        <div class="mt-3 flex items-baseline gap-1">
                            <span class="text-4xl font-bold tracking-tight">{{ getPrice(plan) }}</span>
                            <span class="text-muted-foreground">/{{ $t('billing.subscribe.per_month') }}</span>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ isYearly ? $t('billing.subscribe.billed_yearly') : $t('billing.subscribe.billed_monthly') }}
                        </p>
                    </div>

                    <ul class="mb-8 flex-1 space-y-3">
                        <li
                            v-for="feature in features(plan)"
                            :key="feature"
                            class="flex items-start gap-2.5 text-sm"
                        >
                            <IconCheck class="mt-0.5 size-4 shrink-0 text-primary" />
                            <span>{{ feature }}</span>
                        </li>
                    </ul>

                    <Button
                        class="w-full"
                        :variant="isPopular(plan) ? 'default' : 'outline'"
                        size="lg"
                        :disabled="processing !== null"
                        @click="selectPlan(plan)"
                    >
                        {{ trans('billing.subscribe.start_trial', { days: String(trialDays) }) }}
                    </Button>
                </div>
            </div>

            <!-- Footer -->
            <p class="mt-10 text-center text-sm text-muted-foreground">
                {{ $t('billing.subscribe.cancel_anytime') }}
            </p>
        </div>
    </div>
</template>
