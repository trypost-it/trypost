import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import type { Usage } from '@/types';

interface Plan {
    id: string;
    slug: string;
    name: string;
}

interface Features {
    workspaceLimit: number;
    socialAccountLimit: number;
    memberLimit: number;
    monthlyCreditsLimit: number;
}

export const useFeatureAccess = () => {
    const page = usePage();

    const isSelfHosted = computed(() => page.props.selfHosted as boolean);
    const plan = computed<Plan | null>(
        () => (page.props.auth as { plan: Plan | null }).plan ?? null,
    );
    const usage = computed<Usage | null>(
        () => (page.props.usage as Usage | null) ?? null,
    );
    const features = computed<Features | null>(
        () => (page.props.features as Features | null) ?? null,
    );

    const workspaceLimit = computed(() => features.value?.workspaceLimit ?? 1);
    const socialAccountLimit = computed(
        () => features.value?.socialAccountLimit ?? 1,
    );
    const memberLimit = computed(() => features.value?.memberLimit ?? 1);
    const monthlyCreditsLimit = computed(
        () => features.value?.monthlyCreditsLimit ?? 0,
    );

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
        return (
            usage.value.memberCount + usage.value.pendingInviteCount <
            memberLimit.value
        );
    });

    const hasCreditsLeft = computed(() => {
        if (isSelfHosted.value) return true;
        if (!usage.value) return true;
        return usage.value.creditsUsed < monthlyCreditsLimit.value;
    });

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
    };
};
