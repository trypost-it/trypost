<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { IconArrowRight, IconCheck, IconInfoCircle } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTracking } from '@/composables/useTracking';
import { checkout } from '@/routes/app/billing';

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

defineProps<{
    plans: Plan[];
    trialDays: number;
}>();

const isYearly = ref(true);
const processing = ref<string | null>(null);

const { trackBeginCheckout } = useTracking();

// Headline price = the per-month figure, regardless of toggle. When
// yearly is selected we read the pre-computed per-month rate from
// `lang/*/billing.php` (already with the right currency) so the price
// stays comparable visually across toggles.
const getDisplayPrice = (plan: Plan): string =>
    trans(
        `billing.subscribe.prices.${plan.slug}.${isYearly.value ? 'yearly_per_month' : 'monthly'}`,
    );

const selectPlan = (plan: Plan) => {
    processing.value = plan.id;

    const interval = isYearly.value ? 'yearly' : 'monthly';
    const priceId = isYearly.value
        ? plan.stripe_yearly_price_id
        : plan.stripe_monthly_price_id;

    trackBeginCheckout({
        name: plan.name,
        interval,
    });

    router.post(checkout.url(plan.id), {
        price_id: priceId,
    });
};

// Mirrors the marketing site: each tier shows only the deltas vs the
// previous one, plus a header telling the reader the lower tier is
// already included. Starter shows the full "What's included" list.
const inheritsFrom: Partial<Record<PlanSlug, PlanSlug>> = {
    plus: 'starter',
    pro: 'plus',
    max: 'pro',
};

const planNameBySlug = (plans: Plan[], slug: PlanSlug): string =>
    plans.find((plan) => plan.slug === slug)?.name ?? slug;

const highlights = (plan: Plan): Highlight[] => [
    {
        text: trans('billing.subscribe.features.social_accounts', {
            count: String(plan.social_account_limit),
        }),
    },
    {
        text: trans('billing.subscribe.features.credits', {
            count: plan.monthly_credits_limit.toLocaleString(),
        }),
        tooltip: trans(`billing.subscribe.credit_tooltips.${plan.slug}`),
    },
    {
        text: trans('billing.subscribe.features.workspaces', {
            count: String(plan.workspace_limit),
        }),
    },
    {
        text: trans('billing.subscribe.features.members', {
            count: String(plan.member_limit),
        }),
    },
];

const isPopular = (plan: Plan): boolean => plan.slug === 'pro';

const planTones: Record<PlanSlug, string> = {
    starter: 'bg-amber-50',
    plus: 'bg-rose-50',
    pro: 'bg-violet-100',
    max: 'bg-emerald-50',
};
</script>

<template>
    <Head :title="$t('billing.subscribe.page_title')" />

    <TooltipProvider :delay-duration="150">
        <section class="relative min-h-screen overflow-hidden bg-background">
            <!-- Dot pattern overlay (subtle ink dots) -->
            <div
                class="pointer-events-none absolute inset-0 opacity-[0.06]"
                style="
                    background-image: radial-gradient(
                        circle,
                        #0a0a0a 1px,
                        transparent 1px
                    );
                    background-size: 28px 28px;
                "
            />

            <!-- Soft violet glow blobs -->
            <div
                class="pointer-events-none absolute -top-20 right-0 size-[560px] rounded-full bg-violet-200/50 blur-3xl"
            />
            <div
                class="pointer-events-none absolute top-1/3 -left-32 size-[440px] rounded-full bg-fuchsia-200/30 blur-3xl"
            />

            <!-- Hero -->
            <div class="relative mx-auto max-w-7xl px-6 pt-14 pb-4 lg:pt-20">
                <div class="mx-auto max-w-3xl space-y-4 text-center">
                    <span
                        class="inline-block -rotate-1 rounded-md border-2 border-foreground bg-violet-200 px-3 py-1 text-[11px] font-black tracking-widest text-foreground uppercase shadow-2xs"
                    >
                        {{ $t('billing.subscribe.eyebrow') }}
                    </span>
                    <h1
                        class="text-3xl leading-[1.1] font-normal tracking-tight text-balance text-foreground sm:text-5xl lg:text-6xl"
                        style="font-family: var(--font-display)"
                    >
                        {{ $t('billing.subscribe.title') }}
                    </h1>
                    <p
                        class="mx-auto max-w-2xl text-base text-balance text-muted-foreground sm:text-lg"
                    >
                        {{
                            trans('billing.subscribe.description', {
                                days: String(trialDays),
                            })
                        }}
                    </p>
                </div>
            </div>

            <!-- Plans -->
            <div class="relative mx-auto max-w-7xl px-4 pb-20">
                <!-- Billing toggle pill -->
                <div class="flex justify-center pt-8 pb-10">
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
                                    'cursor-pointer rounded-full px-5 py-1.5 text-sm font-semibold whitespace-nowrap transition-colors',
                                    !isYearly
                                        ? 'bg-foreground text-background'
                                        : 'text-foreground hover:bg-foreground/5',
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
                                    'cursor-pointer rounded-full px-5 py-1.5 text-sm font-semibold whitespace-nowrap transition-colors',
                                    isYearly
                                        ? 'bg-foreground text-background'
                                        : 'text-foreground hover:bg-foreground/5',
                                ]"
                                @click="isYearly = true"
                            >
                                {{ $t('billing.subscribe.yearly') }}
                            </button>
                        </div>

                        <span
                            :class="[
                                'pointer-events-none absolute -top-6 -right-12 inline-flex rotate-6 items-center gap-1 rounded-md border-2 border-foreground bg-amber-200 px-2.5 py-1 text-[10px] font-black tracking-widest whitespace-nowrap text-foreground uppercase shadow-xs transition-all sm:top-1/2 sm:right-auto sm:left-full sm:ml-3 sm:-translate-y-1/2 sm:-rotate-3',
                                isYearly
                                    ? 'opacity-100'
                                    : 'opacity-30 grayscale',
                            ]"
                        >
                            <span class="hidden sm:inline">←</span>
                            {{ $t('billing.subscribe.save_months') }}
                        </span>
                    </div>
                </div>

                <!-- Plan cards -->
                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                    <div
                        v-for="plan in plans"
                        :key="plan.id"
                        :class="[
                            planTones[plan.slug],
                            'relative flex flex-col rounded-2xl border-2 border-foreground p-7 transition-shadow sm:p-8',
                            isPopular(plan)
                                ? 'shadow-xl'
                                : 'shadow-sm hover:shadow-md',
                        ]"
                    >
                        <span
                            v-if="isPopular(plan)"
                            class="absolute -top-3.5 left-1/2 inline-flex -translate-x-1/2 items-center gap-1 rounded-md border-2 border-foreground bg-foreground px-3 py-1 text-[11px] font-black tracking-widest whitespace-nowrap text-background uppercase shadow-2xs"
                        >
                            ⭐ {{ $t('billing.subscribe.popular') }}
                        </span>

                        <div class="mb-5">
                            <h3
                                class="text-2xl font-bold tracking-tight text-foreground"
                            >
                                {{ plan.name }}
                            </h3>
                        </div>

                        <div class="mb-2">
                            <div class="flex items-baseline gap-1">
                                <span
                                    class="relative inline-block overflow-hidden leading-none"
                                >
                                    <Transition
                                        enter-active-class="motion-safe:transition-transform motion-safe:duration-[350ms] motion-safe:ease-out"
                                        leave-active-class="motion-safe:transition-transform motion-safe:duration-[350ms] motion-safe:ease-out absolute top-0 left-0"
                                        enter-from-class="motion-safe:translate-y-full"
                                        leave-to-class="motion-safe:-translate-y-full"
                                    >
                                        <span
                                            :key="getDisplayPrice(plan)"
                                            class="block text-5xl font-black text-foreground tabular-nums"
                                        >
                                            {{ getDisplayPrice(plan) }}
                                        </span>
                                    </Transition>
                                </span>
                                <span
                                    class="text-sm font-semibold text-foreground/60"
                                >
                                    {{ $t('billing.subscribe.per_month') }}
                                </span>
                            </div>
                            <p
                                class="mt-1 text-xs leading-tight text-foreground/60"
                            >
                                {{
                                    isYearly
                                        ? $t('billing.subscribe.billed_yearly')
                                        : $t('billing.subscribe.billed_monthly')
                                }}
                            </p>
                        </div>

                        <button
                            type="button"
                            :disabled="processing !== null"
                            :class="[
                                'my-5 inline-flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-full border-2 border-foreground px-4 py-2.5 text-sm font-semibold whitespace-nowrap shadow-2xs transition-shadow hover:shadow-xs disabled:cursor-not-allowed disabled:opacity-60',
                                isPopular(plan)
                                    ? 'bg-foreground text-background'
                                    : 'bg-card text-foreground',
                            ]"
                            @click="selectPlan(plan)"
                        >
                            {{
                                trans('billing.subscribe.start_trial', {
                                    days: String(trialDays),
                                })
                            }}
                            <IconArrowRight class="size-4" />
                        </button>

                        <div class="flex flex-col gap-3">
                            <p
                                class="text-sm leading-snug font-bold text-foreground"
                            >
                                <template v-if="inheritsFrom[plan.slug]">
                                    {{
                                        trans(
                                            'billing.subscribe.everything_in',
                                            {
                                                plan: planNameBySlug(
                                                    plans,
                                                    inheritsFrom[plan.slug]!,
                                                ),
                                            },
                                        )
                                    }}
                                </template>
                                <template v-else>
                                    {{
                                        $t(
                                            'billing.subscribe.features_included',
                                        )
                                    }}
                                </template>
                            </p>
                            <div
                                v-for="highlight in highlights(plan)"
                                :key="highlight.text"
                                class="flex items-start gap-2.5"
                            >
                                <div
                                    class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border-2 border-foreground bg-card shadow-2xs"
                                >
                                    <IconCheck
                                        class="size-3 text-foreground"
                                        stroke-width="3"
                                    />
                                </div>
                                <span
                                    class="inline-flex items-center gap-1.5 text-sm leading-snug text-foreground/80"
                                >
                                    {{ highlight.text }}
                                    <Tooltip v-if="highlight.tooltip">
                                        <TooltipTrigger as-child>
                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center text-foreground/40 transition-colors hover:text-foreground"
                                                :aria-label="highlight.tooltip"
                                            >
                                                <IconInfoCircle
                                                    class="size-3.5"
                                                    stroke-width="2"
                                                />
                                            </button>
                                        </TooltipTrigger>
                                        <TooltipContent
                                            class="max-w-xs text-sm leading-snug"
                                        >
                                            {{ highlight.tooltip }}
                                        </TooltipContent>
                                    </Tooltip>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </TooltipProvider>
</template>
