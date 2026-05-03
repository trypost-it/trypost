<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { IconCheck } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { useFeatureAccess } from '@/composables/useFeatureAccess';
import { useUpgradeDialog } from '@/composables/useUpgradeDialog';
import { checkout, swap } from '@/routes/app/billing';

interface Plan {
    id: string;
    slug: string;
    name: string;
    stripe_monthly_price_id: string | null;
    stripe_yearly_price_id: string | null;
    social_account_limit: number;
    member_limit: number;
    workspace_limit: number;
    ai_images_limit: number;
}

const { open, reason, closeUpgrade } = useUpgradeDialog();
const { usage } = useFeatureAccess();
const page = usePage();

const plans = computed<Plan[]>(() => (page.props.plans as Plan[]) ?? []);
const currentPlan = computed<Plan | null>(() => (page.props.auth as { plan: Plan | null }).plan ?? null);
const currentPriceId = computed<string | null>(() => (page.props.auth as { currentPriceId?: string | null }).currentPriceId ?? null);
const hasActiveSubscription = computed<boolean>(() => Boolean((page.props.auth as { hasActiveSubscription?: boolean }).hasActiveSubscription));

const isOnYearly = computed(() => {
    if (!currentPriceId.value || !currentPlan.value) return false;
    return currentPriceId.value === currentPlan.value.stripe_yearly_price_id;
});

const isYearly = ref(false);
const processing = ref<string | null>(null);

watch(open, (isOpen) => {
    if (isOpen) {
        isYearly.value = isOnYearly.value;
    }
});

const getPrice = (plan: Plan): string => {
    const key = `billing.subscribe.prices.${plan.slug}.${isYearly.value ? 'yearly' : 'monthly'}`;
    return trans(key);
};

const isSamePlan = (plan: Plan): boolean => currentPlan.value?.id === plan.id;

const isCurrent = (plan: Plan): boolean => {
    if (!isSamePlan(plan)) return false;
    return isYearly.value === isOnYearly.value;
};

const isPopular = (plan: Plan): boolean => plan.slug === 'pro';

const features = (plan: Plan): string[] => [
    trans('billing.subscribe.features.social_accounts', { count: String(plan.social_account_limit) }),
    trans('billing.subscribe.features.workspaces', { count: String(plan.workspace_limit) }),
    trans('billing.subscribe.features.members', { count: String(plan.member_limit) }),
    trans('billing.subscribe.features.ai_images', { count: String(plan.ai_images_limit) }),
];

const downgradeBlocker = (plan: Plan): string | null => {
    if (!usage.value) return null;

    if (usage.value.workspaceCount > plan.workspace_limit) {
        return trans('billing.flash.cannot_downgrade.workspaces', {
            plan: plan.name,
            count: String(usage.value.workspaceCount),
            limit: String(plan.workspace_limit),
        });
    }
    if (usage.value.socialAccountCount > plan.social_account_limit) {
        return trans('billing.flash.cannot_downgrade.social_accounts', {
            plan: plan.name,
            count: String(usage.value.socialAccountCount),
            limit: String(plan.social_account_limit),
        });
    }
    const totalMembers = usage.value.memberCount + usage.value.pendingInviteCount;
    if (totalMembers > plan.member_limit) {
        return trans('billing.flash.cannot_downgrade.members', {
            plan: plan.name,
            count: String(totalMembers),
            limit: String(plan.member_limit),
        });
    }
    return null;
};

const isBlocked = (plan: Plan): boolean => !isCurrent(plan) && downgradeBlocker(plan) !== null;

const ctaLabel = (plan: Plan): string => {
    if (isCurrent(plan)) return trans('billing.upgrade_dialog.current_plan');
    if (isBlocked(plan)) return trans('billing.upgrade_dialog.unavailable');
    if (isSamePlan(plan)) {
        return isYearly.value
            ? trans('billing.upgrade_dialog.switch_to_yearly')
            : trans('billing.upgrade_dialog.switch_to_monthly');
    }
    if (hasActiveSubscription.value) return trans('billing.upgrade_dialog.switch');
    return trans('billing.upgrade_dialog.subscribe');
};

const handleSelect = (plan: Plan) => {
    if (isCurrent(plan)) return;

    processing.value = plan.id;
    const priceId = isYearly.value ? plan.stripe_yearly_price_id : plan.stripe_monthly_price_id;
    const url = hasActiveSubscription.value ? swap.url(plan.id) : checkout.url(plan.id);

    router.post(url, { price_id: priceId }, {
        onSuccess: () => {
            closeUpgrade();
        },
        onFinish: () => {
            processing.value = null;
        },
    });
};

const onOpenChange = (value: boolean) => {
    if (!value) closeUpgrade();
};
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent class="w-[95vw] gap-0 p-0 sm:max-w-4xl">
            <div class="flex flex-col gap-4 px-6 pt-6 pb-4 sm:flex-row sm:items-center sm:justify-between sm:gap-6">
                <div class="min-w-0">
                    <DialogTitle class="text-2xl font-semibold">
                        {{ $t('billing.upgrade_dialog.title') }}
                    </DialogTitle>
                    <DialogDescription class="mt-1">
                        {{ reason ?? $t('billing.upgrade_dialog.description') }}
                    </DialogDescription>
                </div>

                <div
                    v-if="!isOnYearly"
                    class="inline-flex h-9 shrink-0 items-center rounded-lg bg-muted p-[3px] text-sm"
                >
                    <button
                        type="button"
                        class="inline-flex h-full items-center rounded-md px-3 font-medium transition-colors"
                        :class="!isYearly ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        @click="isYearly = false"
                    >
                        {{ $t('billing.subscribe.monthly') }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-full items-center gap-2 rounded-md px-3 font-medium transition-colors"
                        :class="isYearly ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        @click="isYearly = true"
                    >
                        {{ $t('billing.subscribe.yearly') }}
                        <Badge variant="secondary" class="whitespace-nowrap text-[10px]">
                            {{ $t('billing.subscribe.save_months') }}
                        </Badge>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 px-6 pt-8 pb-6 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="plan in plans"
                    :key="plan.id"
                    class="relative flex flex-col rounded-xl border bg-card p-5"
                    :class="[
                        isPopular(plan) ? 'border-primary ring-1 ring-primary' : '',
                        isCurrent(plan) && !isPopular(plan) ? 'border-foreground/40' : '',
                    ]"
                >
                    <Badge v-if="isPopular(plan)" class="absolute -top-3 left-1/2 -translate-x-1/2 whitespace-nowrap">
                        {{ $t('billing.subscribe.popular') }}
                    </Badge>
                    <Badge v-else-if="isCurrent(plan)" variant="secondary" class="absolute -top-3 left-1/2 -translate-x-1/2 whitespace-nowrap">
                        {{ $t('billing.upgrade_dialog.current_badge') }}
                    </Badge>

                    <h3 class="text-lg font-semibold">{{ plan.name }}</h3>
                    <div class="mt-2 flex items-baseline gap-1">
                        <span class="text-3xl font-bold tracking-tight">{{ getPrice(plan) }}</span>
                        <span class="text-sm text-muted-foreground">
                            /{{ isYearly ? $t('billing.subscribe.per_year') : $t('billing.subscribe.per_month') }}
                        </span>
                    </div>

                    <ul class="my-5 flex-1 space-y-2">
                        <li
                            v-for="feature in features(plan)"
                            :key="feature"
                            class="flex items-start gap-2 text-sm"
                        >
                            <IconCheck class="mt-0.5 size-4 shrink-0 text-primary" />
                            <span>{{ feature }}</span>
                        </li>
                    </ul>

                    <Button
                        class="w-full"
                        :variant="isCurrent(plan) || isBlocked(plan) ? 'secondary' : isPopular(plan) ? 'default' : 'outline'"
                        :loading="processing === plan.id"
                        :disabled="(processing !== null && processing !== plan.id) || isCurrent(plan) || isBlocked(plan)"
                        :title="downgradeBlocker(plan) ?? undefined"
                        @click="handleSelect(plan)"
                    >
                        {{ ctaLabel(plan) }}
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
