import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import type { AuthFeatures, AuthPlan, Usage } from '@/types';
import { useUpgradeDialog } from './useUpgradeDialog';

export const useFeatureAccess = () => {
    const page = usePage();
    const { openUpgrade } = useUpgradeDialog();

    const isSelfHosted = computed(() => page.props.selfHosted as boolean);
    const plan = computed<AuthPlan | null>(() => (page.props.auth as { plan: AuthPlan | null }).plan ?? null);
    const usage = computed<Usage | null>(() => (page.props.usage as Usage | null) ?? null);
    const features = computed<AuthFeatures | null>(() => (page.props.features as AuthFeatures | null) ?? null);

    const workspaceLimit = computed(() => features.value?.workspaceLimit ?? 1);
    const socialAccountLimit = computed(() => features.value?.socialAccountLimit ?? 1);
    const memberLimit = computed(() => features.value?.memberLimit ?? 1);
    const monthlyCreditsLimit = computed(() => features.value?.monthlyCreditsLimit ?? 0);

    const canCreateWorkspace = computed(() => {
        if (isSelfHosted.value) return true;
        if (!usage.value) return true;
        return usage.value.workspaceCount < workspaceLimit.value;
    });

    const canConnectSocialAccount = computed(() => {
        if (isSelfHosted.value) return true;
        if (!usage.value) return true;
        return usage.value.socialAccountCount < socialAccountLimit.value;
    });

    const canInviteMember = computed(() => {
        if (isSelfHosted.value) return true;
        if (!usage.value) return true;
        return usage.value.memberCount + usage.value.pendingInviteCount < memberLimit.value;
    });

    const hasCreditsLeft = computed(() => {
        if (isSelfHosted.value) return true;
        if (!usage.value) return true;
        return usage.value.creditsUsed < monthlyCreditsLimit.value;
    });

    const canUseAi = computed(() => {
        if (isSelfHosted.value) return true;
        return features.value?.canUseAi ?? false;
    });

    const canUseAnalytics = computed(() => {
        if (isSelfHosted.value) return true;
        return features.value?.canUseAnalytics ?? false;
    });

    const canConnectNetwork = (slug: string): boolean => {
        if (isSelfHosted.value) return true;
        const blocked = features.value?.blockedNetworks;
        if (!Array.isArray(blocked)) return true;
        return !blocked.includes(slug);
    };

    const requireAi = (): boolean => {
        if (canUseAi.value) return true;
        openUpgrade('ai_disabled');
        return false;
    };

    const requireAnalytics = (): boolean => {
        if (canUseAnalytics.value) return true;
        openUpgrade('analytics_disabled');
        return false;
    };

    const requireNetwork = (slug: string): boolean => {
        if (canConnectNetwork(slug)) return true;
        openUpgrade('network_disabled');
        return false;
    };

    /**
     * Navigate to a URL if the gate passes, otherwise open the upgrade dialog.
     * Single click handler for any gated sidebar/menu item.
     */
    const navigateOrUpgrade = (
        url: string,
        gate: boolean | (() => boolean),
        reason: string,
    ): void => {
        const allowed = typeof gate === 'function' ? gate() : gate;
        if (!allowed) {
            openUpgrade(reason);
            return;
        }
        router.visit(url);
    };

    return {
        plan,
        usage,
        features,
        isSelfHosted,
        workspaceLimit,
        socialAccountLimit,
        memberLimit,
        monthlyCreditsLimit,
        canCreateWorkspace,
        canConnectSocialAccount,
        canInviteMember,
        hasCreditsLeft,
        canUseAi,
        canUseAnalytics,
        canConnectNetwork,
        requireAi,
        requireAnalytics,
        requireNetwork,
        navigateOrUpgrade,
    };
};
