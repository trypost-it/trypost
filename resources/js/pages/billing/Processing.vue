<script setup lang="ts">
import { Head, router, usePage, usePoll } from '@inertiajs/vue3';
import { IconLoader2 } from '@tabler/icons-vue';
import { onMounted, watch } from 'vue';

import { useTracking } from '@/composables/useTracking';
import { home } from '@/routes/app';
import type { Auth } from '@/types';

const props = defineProps<{
    subscriptionActive: boolean;
}>();

const page = usePage();

// Polls `auth` alongside so `auth.plan.interval` is fresh once the Stripe
// webhook creates the local Subscription row — at /billing/processing's
// initial render that row doesn't exist yet, so the interval would default
// to 'monthly' even for a yearly purchase.
const { stop } = usePoll(2000, {
    only: ['subscriptionActive', 'auth'],
});

const { trackPurchase } = useTracking();

const goHome = () => router.visit(home.url());

// `watch` (without `immediate`) only fires on transition false → true, which
// is exactly the purchase moment. The `onMounted` fallback covers the case
// where the user lands here with an already-active subscription (back button,
// refresh after the redirect) — we just bounce them home, no extra event.
watch(
    () => props.subscriptionActive,
    (active) => {
        if (! active) {
            return;
        }

        stop();

        const plan = (page.props.auth as Auth | undefined)?.plan;
        if (plan) {
            trackPurchase({
                name: plan.name,
                interval: plan.interval,
            });
        }

        goHome();
    },
);

onMounted(() => {
    if (props.subscriptionActive) {
        goHome();
    }
});
</script>

<template>
    <Head :title="$t('billing.processing.page_title')" />

    <div class="flex min-h-screen items-center justify-center bg-background">
        <div class="flex flex-col items-center gap-4 text-center">
            <IconLoader2 class="size-8 animate-spin text-muted-foreground" />
            <div>
                <h2 class="text-lg font-semibold tracking-tight">
                    {{ $t('billing.processing.title') }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ $t('billing.processing.description') }}
                </p>
            </div>
        </div>
    </div>
</template>
