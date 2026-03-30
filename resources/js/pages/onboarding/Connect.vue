<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { IconCheck } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, onMounted, onUnmounted, ref } from 'vue';

import { storeConnect } from '@/actions/App/Http/Controllers/App/OnboardingController';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';

export interface SocialAccount {
    id: string;
    platform: string;
    username: string;
    display_name: string;
    status: 'connected' | 'disconnected' | 'token_expired' | null;
}

export interface Platform {
    value: string;
    label: string;
    connected: boolean;
    account: SocialAccount | null;
}

interface Props {
    platforms: Platform[];
    hasWorkspace: boolean;
}

const props = defineProps<Props>();

const isSubmitting = ref(false);

const connectedCount = computed(() =>
    props.platforms.filter((p) => p.connected).length,
);

const getPlatformLogo = (platform: string): string => {
    const logos: Record<string, string> = {
        'linkedin': '/images/accounts/linkedin.png',
        'linkedin-page': '/images/accounts/linkedin.png',
        'x': '/images/accounts/x.png',
        'tiktok': '/images/accounts/tiktok.png',
        'instagram': '/images/accounts/instagram.png',
        'facebook': '/images/accounts/facebook.png',
        'youtube': '/images/accounts/youtube.png',
        'threads': '/images/accounts/threads.png',
        'bluesky': '/images/accounts/bluesky.png',
        'pinterest': '/images/accounts/pinterest.png',
        'mastodon': '/images/accounts/mastodon.png',
    };
    return logos[platform] || '/images/accounts/linkedin.png';
};

const openOAuthPopup = (platformValue: string) => {
    const url = `/connect/${platformValue}`;
    const width = 600;
    const height = 700;
    const left = window.screenX + (window.outerWidth - width) / 2;
    const top = window.screenY + (window.outerHeight - height) / 2;

    window.open(
        url,
        'oauth-popup',
        `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`,
    );
};

const handleOAuthMessage = (event: MessageEvent) => {
    if (event.origin !== window.location.origin) return;
    if (event.data?.type !== 'social-oauth-callback') return;
    router.reload();
};

onMounted(() => window.addEventListener('message', handleOAuthMessage));
onUnmounted(() => window.removeEventListener('message', handleOAuthMessage));

const submit = () => {
    isSubmitting.value = true;
    router.post(storeConnect.url());
};
</script>

<template>
    <Head :title="$t('onboarding.connect.page_title')" />

    <AuthLayout
        :title="$t('onboarding.connect.title')"
        :description="$t('onboarding.connect.description')"
    >
        <div v-if="hasWorkspace" class="space-y-4">
            <div class="space-y-1.5">
                <div
                    v-for="platform in platforms"
                    :key="platform.value"
                    class="flex items-center gap-3 rounded-lg border px-3 py-2.5 transition-colors"
                    :class="platform.connected ? 'border-green-500/30 bg-green-50/50 dark:bg-green-950/20' : ''"
                >
                    <img
                        :src="getPlatformLogo(platform.value)"
                        :alt="platform.label"
                        class="size-7 rounded object-contain"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium leading-tight">{{ platform.label }}</p>
                        <p v-if="platform.connected && platform.account" class="truncate text-xs text-muted-foreground">
                            @{{ platform.account.username || platform.account.display_name }}
                        </p>
                    </div>
                    <div v-if="platform.connected" class="flex size-5 shrink-0 items-center justify-center rounded-full bg-green-500 text-white">
                        <IconCheck class="size-3" />
                    </div>
                    <Button
                        v-else
                        variant="outline"
                        size="sm"
                        class="h-7 shrink-0 text-xs"
                        @click="openOAuthPopup(platform.value)"
                    >
                        {{ trans('accounts.connect') }}
                    </Button>
                </div>
            </div>

            <Button
                v-if="connectedCount > 0"
                class="w-full"
                :disabled="isSubmitting"
                @click="submit"
            >
                {{ $t('onboarding.connect.submit') }}
            </Button>
        </div>

        <div v-else class="flex flex-col items-center gap-4 py-8">
            <p class="text-muted-foreground">
                {{ $t('onboarding.connect.error') }}
            </p>
            <Button variant="outline" @click="router.visit('/onboarding/role')">
                {{ $t('onboarding.connect.go_back') }}
            </Button>
        </div>
    </AuthLayout>
</template>
