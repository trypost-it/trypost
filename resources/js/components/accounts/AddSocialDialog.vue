<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { IconInfoCircle } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { onMounted, onUnmounted } from 'vue';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

export interface AvailablePlatform {
    value: string;
    label: string;
    color: string;
}

defineProps<{
    platforms: AvailablePlatform[];
}>();

const open = defineModel<boolean>('open', { default: false });

const getPlatformLogo = (platform: string): string => {
    const logos: Record<string, string> = {
        'linkedin': '/images/accounts/linkedin.png',
        'linkedin-page': '/images/accounts/linkedin.png',
        'x': '/images/accounts/x.png',
        'tiktok': '/images/accounts/tiktok.png',
        'instagram': '/images/accounts/instagram.png',
        'instagram-facebook': '/images/accounts/instagram.png',
        'facebook': '/images/accounts/facebook.png',
        'youtube': '/images/accounts/youtube.png',
        'threads': '/images/accounts/threads.png',
        'bluesky': '/images/accounts/bluesky.png',
        'pinterest': '/images/accounts/pinterest.png',
        'mastodon': '/images/accounts/mastodon.png',
    };
    return logos[platform] || '/images/accounts/linkedin.png';
};

const getPlatformTooltip = (platform: string): string | null => {
    const tooltips: Record<string, string> = {
        'instagram-facebook': trans('accounts.tooltips.instagram_facebook'),
        'instagram': trans('accounts.tooltips.instagram_direct'),
        'bluesky': trans('accounts.tooltips.bluesky'),
    };
    return tooltips[platform] || null;
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

    open.value = false;
    router.reload();
};

onMounted(() => {
    window.addEventListener('message', handleOAuthMessage);
});

onUnmounted(() => {
    window.removeEventListener('message', handleOAuthMessage);
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ $t('accounts.add_social_title') }}</DialogTitle>
                <DialogDescription>
                    {{ $t('accounts.add_social_description') }}
                </DialogDescription>
            </DialogHeader>
            <div class="grid grid-cols-2 gap-3">
                <Button
                    v-for="platform in platforms"
                    :key="platform.value"
                    variant="outline"
                    class="h-auto flex-col gap-2 py-4"
                    @click="openOAuthPopup(platform.value)"
                >
                    <div class="relative">
                        <img :src="getPlatformLogo(platform.value)" :alt="platform.label" class="h-8 w-8 rounded-full object-contain" />
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="text-xs">
                            <template v-if="platform.label.includes('(')">
                                {{ platform.label.split('(')[0].trim() }}
                            </template>
                            <template v-else>{{ platform.label }}</template>
                        </span>
                        <TooltipProvider v-if="getPlatformTooltip(platform.value)">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <IconInfoCircle class="h-3 w-3 shrink-0 text-muted-foreground cursor-help" />
                                </TooltipTrigger>
                                <TooltipContent side="top" class="max-w-[250px]">
                                    <p>{{ getPlatformTooltip(platform.value) }}</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
