import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Plan {
    id: string;
    slug: string;
    name: string;
}

interface Usage {
    workspaceCount: number;
    socialAccountCount: number;
    memberCount: number;
    pendingInviteCount: number;
}

interface Features {
    workspaceLimit: number;
    socialAccountLimit: number;
    memberLimit: number;
    aiImagesLimit: number;
}

export const useFeatureAccess = () => {
    const page = usePage();

    const isSelfHosted = computed(() => page.props.selfHosted as boolean);
    const plan = computed<Plan | null>(() => (page.props.auth as { plan: Plan | null }).plan ?? null);
    const usage = computed<Usage | null>(() => (page.props.usage as Usage | null) ?? null);
    const features = computed<Features | null>(() => (page.props.features as Features | null) ?? null);

    const workspaceLimit = computed(() => features.value?.workspaceLimit ?? 1);
    const socialAccountLimit = computed(() => features.value?.socialAccountLimit ?? 1);
    const memberLimit = computed(() => features.value?.memberLimit ?? 1);
    const aiImagesLimit = computed(() => features.value?.aiImagesLimit ?? 0);

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

    return {
        plan,
        usage,
        features,
        isSelfHosted,
        workspaceLimit,
        socialAccountLimit,
        memberLimit,
        aiImagesLimit,
        canCreateWorkspace,
        canConnectSocialAccount,
        canInviteMember,
    };
};
