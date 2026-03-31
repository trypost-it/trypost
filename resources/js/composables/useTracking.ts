import posthog from '@/posthog';

const push = (data: Record<string, unknown>) => {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(data);
};

export const useTracking = () => ({
    trackSignUp: (authProvider: string) => {
        posthog.capture('user.signed_up', {
            auth_provider: authProvider,
        });

        push({
            event: 'sign_up',
            method: authProvider,
        });
    },

    trackBeginCheckout: (plan: { name: string; price: number; interval: string }) => {
        posthog.capture('checkout.started', {
            plan_name: plan.name,
            plan_price: plan.price,
            interval: plan.interval,
        });

        push({
            event: 'begin_checkout',
            currency: 'USD',
            plan_name: plan.name,
            plan_price: plan.price,
            plan_interval: plan.interval,
        });
    },

    trackPurchase: (plan: { name: string; price: number; interval: string }) => {
        posthog.capture('checkout.completed', {
            plan_name: plan.name,
            plan_price: plan.price,
            interval: plan.interval,
        });

        push({
            event: 'purchase',
            currency: 'USD',
            plan_name: plan.name,
            plan_price: plan.price,
            plan_interval: plan.interval,
        });
    },
});
