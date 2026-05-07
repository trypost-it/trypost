<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { IconCheck, IconInfoCircle, IconLoader2 } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useFeatureAccess } from '@/composables/useFeatureAccess';
import { useUpgradeDialog } from '@/composables/useUpgradeDialog';
import { checkout, swap } from '@/routes/app/billing';
import type { AuthPlan } from '@/types';

type PlanSlug = 'starter' | 'plus' | 'pro' | 'max';

interface Plan {
    id: string;
    slug: PlanSlug;
    name: string;
    stripe_monthly_price_id: string | null;
    stripe_yearly_price_id: string | null;
    social_account_limit: number;
    member_limit: number;
    workspace_limit: number;
    monthly_credits_limit: number;
}

interface Highlight {
    text: string;
    tooltip?: string;
}

const { open, reason, closeUpgrade } = useUpgradeDialog();
const { usage } = useFeatureAccess();
const page = usePage();

const plans = computed<Plan[]>(() => (page.props.plans as Plan[]) ?? []);
const currentPlan = computed<AuthPlan | null>(() => (page.props.auth as { plan: AuthPlan | null }).plan ?? null);
const hasActiveSubscription = computed<boolean>(() => Boolean((page.props.auth as { hasActiveSubscription?: boolean }).hasActiveSubscription));

const isOnYearly = computed(() => currentPlan.value?.interval === 'yearly');

const isYearly = ref(true);
const processing = ref<string | null>(null);

watch(open, (isOpen) => {
    if (isOpen) {
        isYearly.value = true;
    }
});

// Mirrors Subscribe.vue: headline price = the per-month figure regardless
// of toggle. When yearly is selected, we read the pre-computed per-month
// rate so the price stays comparable visually.
const getDisplayPrice = (plan: Plan): string => {
    const key = `billing.subscribe.prices.${plan.slug}.${isYearly.value ? 'yearly_per_month' : 'monthly'}`;
    return trans(key);
};

const isSamePlan = (plan: Plan): boolean => currentPlan.value?.id === plan.id;

const isCurrent = (plan: Plan): boolean => {
    if (!isSamePlan(plan)) return false;
    return isYearly.value === isOnYearly.value;
};

const isPopular = (plan: Plan): boolean => plan.slug === 'pro';

const inheritsFrom: Partial<Record<PlanSlug, PlanSlug>> = {
    plus: 'starter',
    pro: 'plus',
    max: 'pro',
};

const planNameBySlug = (list: Plan[], slug: PlanSlug): string =>
    list.find((plan) => plan.slug === slug)?.name ?? slug;

const planTones: Record<PlanSlug, string> = {
    starter: 'bg-amber-50',
    plus: 'bg-rose-50',
    pro: 'bg-violet-100',
    max: 'bg-emerald-50',
};

const highlights = (plan: Plan): Highlight[] => [
    { text: trans('billing.subscribe.features.social_accounts', { count: String(plan.social_account_limit) }) },
    {
        text: trans('billing.subscribe.features.credits', { count: plan.monthly_credits_limit.toLocaleString() }),
        tooltip: trans(`billing.subscribe.credit_tooltips.${plan.slug}`),
    },
    { text: trans('billing.subscribe.features.workspaces', { count: String(plan.workspace_limit) }) },
    { text: trans('billing.subscribe.features.members', { count: String(plan.member_limit) }) },
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
        <DialogContent class="w-[95vw] gap-0 p-0 sm:max-w-6xl">
            <TooltipProvider :delay-duration="150">
                <div class="px-6 pt-6 text-center">
                    <DialogTitle
                        class="text-3xl font-semibold leading-tight"
                        style="font-family: var(--font-display)"
                    >
                        {{ $t('billing.upgrade_dialog.title') }}
                    </DialogTitle>
                    <DialogDescription class="mx-auto mt-2 max-w-2xl text-balance text-base text-foreground/70">
                        {{ reason ?? $t('billing.upgrade_dialog.description') }}
                    </DialogDescription>
                </div>

                <!-- Billing toggle pill -->
                <div class="flex justify-center pt-6 pb-2">
                    <div class="relative">
                        <div
                            class="inline-flex items-center gap-1 rounded-full border-2 border-foreground bg-card p-1 shadow-xs"
                            role="tablist"
                        >
                            <button
                                type="button"
                                role="tab"
                                :aria-selected="!isYearly"
                                :class="[
                                    'cursor-pointer whitespace-nowrap rounded-full px-5 py-1.5 text-sm font-semibold transition-colors',
                                    !isYearly ? 'bg-foreground text-background' : 'text-foreground hover:bg-foreground/5',
                                ]"
                                @click="isYearly = false"
                            >
                                {{ $t('billing.subscribe.monthly') }}
                            </button>
                            <button
                                type="button"
                                role="tab"
                                :aria-selected="isYearly"
                                :class="[
                                    'cursor-pointer whitespace-nowrap rounded-full px-5 py-1.5 text-sm font-semibold transition-colors',
                                    isYearly ? 'bg-foreground text-background' : 'text-foreground hover:bg-foreground/5',
                                ]"
                                @click="isYearly = true"
                            >
                                {{ $t('billing.subscribe.yearly') }}
                            </button>
                        </div>

                        <span
                            :class="[
                                'pointer-events-none absolute top-1/2 left-full ml-3 -translate-y-1/2 -rotate-3 inline-flex items-center gap-1 whitespace-nowrap rounded-md border-2 border-foreground bg-amber-200 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-foreground shadow-xs transition-all',
                                isYearly ? 'opacity-100' : 'opacity-30 grayscale',
                            ]"
                        >
                            <span>←</span>
                            {{ $t('billing.subscribe.save_months') }}
                        </span>
                    </div>
                </div>

                <!-- Plan cards -->
                <div class="grid grid-cols-1 gap-5 px-6 pt-8 pb-6 md:grid-cols-2 xl:grid-cols-4">
                    <div
                        v-for="plan in plans"
                        :key="plan.id"
                        :class="[
                            planTones[plan.slug],
                            'relative flex flex-col rounded-2xl border-2 border-foreground p-6 transition-shadow',
                            isPopular(plan) ? 'shadow-xl' : 'shadow-sm hover:shadow-md',
                        ]"
                    >
                        <span
                            v-if="isPopular(plan)"
                            class="absolute -top-3.5 left-1/2 inline-flex -translate-x-1/2 items-center gap-1 whitespace-nowrap rounded-md border-2 border-foreground bg-foreground px-3 py-1 text-[11px] font-black uppercase tracking-widest text-background shadow-2xs"
                        >
                            ⭐ {{ $t('billing.subscribe.popular') }}
                        </span>
                        <span
                            v-else-if="isCurrent(plan)"
                            class="absolute -top-3.5 left-1/2 inline-flex -translate-x-1/2 items-center whitespace-nowrap rounded-md border-2 border-foreground bg-card px-3 py-1 text-[11px] font-black uppercase tracking-widest text-foreground shadow-2xs"
                        >
                            {{ $t('billing.upgrade_dialog.current_badge') }}
                        </span>

                        <div class="mb-4">
                            <h3 class="text-xl font-bold tracking-tight text-foreground">
                                {{ plan.name }}
                            </h3>
                        </div>

                        <div class="mb-2">
                            <div class="flex items-baseline gap-1">
                                <span class="text-4xl font-black tabular-nums text-foreground">
                                    {{ getDisplayPrice(plan) }}
                                </span>
                                <span class="text-sm font-semibold text-foreground/60">
                                    {{ $t('billing.subscribe.per_month') }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs leading-tight text-foreground/60">
                                {{ isYearly ? $t('billing.subscribe.billed_yearly') : $t('billing.subscribe.billed_monthly') }}
                            </p>
                        </div>

                        <button
                            type="button"
                            :disabled="processing !== null || isCurrent(plan) || isBlocked(plan)"
                            :title="downgradeBlocker(plan) ?? undefined"
                            :class="[
                                'my-5 inline-flex w-full cursor-pointer items-center justify-center gap-1.5 whitespace-nowrap rounded-full border-2 border-foreground px-4 py-2.5 text-sm font-semibold shadow-2xs transition-shadow hover:shadow-xs disabled:cursor-not-allowed disabled:opacity-60',
                                isPopular(plan) ? 'bg-foreground text-background' : 'bg-card text-foreground',
                            ]"
                            @click="handleSelect(plan)"
                        >
                            <IconLoader2 v-if="processing === plan.id" class="size-4 animate-spin" />
                            {{ ctaLabel(plan) }}
                        </button>

                        <div class="flex flex-col gap-3">
                            <p class="text-sm font-bold leading-snug text-foreground">
                                <template v-if="inheritsFrom[plan.slug]">
                                    {{ trans('billing.subscribe.everything_in', { plan: planNameBySlug(plans, inheritsFrom[plan.slug]!) }) }}
                                </template>
                                <template v-else>
                                    {{ $t('billing.subscribe.features_included') }}
                                </template>
                            </p>
                            <div
                                v-for="highlight in highlights(plan)"
                                :key="highlight.text"
                                class="flex items-start gap-2.5"
                            >
                                <div class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border-2 border-foreground bg-card shadow-2xs">
                                    <IconCheck class="size-3 text-foreground" stroke-width="3" />
                                </div>
                                <span class="inline-flex items-center gap-1.5 text-sm leading-snug text-foreground/80">
                                    {{ highlight.text }}
                                    <Tooltip v-if="highlight.tooltip">
                                        <TooltipTrigger as-child>
                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center text-foreground/40 transition-colors hover:text-foreground"
                                                :aria-label="highlight.tooltip"
                                            >
                                                <IconInfoCircle class="size-3.5" stroke-width="2" />
                                            </button>
                                        </TooltipTrigger>
                                        <TooltipContent class="max-w-xs text-sm leading-snug">
                                            {{ highlight.tooltip }}
                                        </TooltipContent>
                                    </Tooltip>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </TooltipProvider>
        </DialogContent>
    </Dialog>
</template>
