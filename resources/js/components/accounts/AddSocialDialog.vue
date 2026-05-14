<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { IconLock, IconPlus } from '@tabler/icons-vue';
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
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useFeatureAccess } from '@/composables/useFeatureAccess';
import * as blueskyRoutes from '@/routes/App/Social/bluesky';
import * as facebookRoutes from '@/routes/App/Social/facebook';
import * as instagramRoutes from '@/routes/App/Social/instagram';
import * as instagramFacebookRoutes from '@/routes/App/Social/instagram-facebook';
import * as linkedinRoutes from '@/routes/App/Social/linkedin';
import * as linkedinPageRoutes from '@/routes/App/Social/linkedin-page';
import * as mastodonRoutes from '@/routes/App/Social/mastodon';
import * as pinterestRoutes from '@/routes/App/Social/pinterest';
import * as threadsRoutes from '@/routes/App/Social/threads';
import * as tiktokRoutes from '@/routes/App/Social/tiktok';
import * as xRoutes from '@/routes/App/Social/x';
import * as youtubeRoutes from '@/routes/App/Social/youtube';

export interface AvailablePlatform {
    value: string;
    label: string;
    color: string;
}

defineProps<{
    platforms: AvailablePlatform[];
}>();

const open = defineModel<boolean>('open', { default: false });

const { canConnectNetwork, requireNetwork } = useFeatureAccess();

const getPlatformDescription = (platform: string): string =>
    trans(`accounts.descriptions.${platform}`);

// Mirrors `NetworksGrid.vue` from the marketing site — pastel tile bg
// + ink 2px border + slight rotation per platform, real PNG logo inside.
// `linkedin-page` / `instagram-facebook` fall back to the base brand
// image and same color since they're variants of the same network.
const platformTheme: Record<string, { bg: string; rotate: string; image: string }> = {
    instagram: { bg: 'bg-pink-200', rotate: '-rotate-2', image: '/images/accounts/instagram.png' },
    'instagram-facebook': { bg: 'bg-pink-200', rotate: '-rotate-2', image: '/images/accounts/instagram.png' },
    facebook: { bg: 'bg-sky-200', rotate: 'rotate-1', image: '/images/accounts/facebook.png' },
    linkedin: { bg: 'bg-blue-200', rotate: '-rotate-1', image: '/images/accounts/linkedin.png' },
    'linkedin-page': { bg: 'bg-blue-200', rotate: '-rotate-1', image: '/images/accounts/linkedin.png' },
    x: { bg: 'bg-amber-200', rotate: 'rotate-2', image: '/images/accounts/x.png' },
    tiktok: { bg: 'bg-fuchsia-200', rotate: '-rotate-1', image: '/images/accounts/tiktok.png' },
    youtube: { bg: 'bg-red-200', rotate: 'rotate-1', image: '/images/accounts/youtube.png' },
    pinterest: { bg: 'bg-rose-200', rotate: '-rotate-2', image: '/images/accounts/pinterest.png' },
    threads: { bg: 'bg-emerald-200', rotate: 'rotate-2', image: '/images/accounts/threads.png' },
    bluesky: { bg: 'bg-cyan-200', rotate: '-rotate-1', image: '/images/accounts/bluesky.png' },
    mastodon: { bg: 'bg-violet-200', rotate: 'rotate-1', image: '/images/accounts/mastodon.png' },
};

const themeFor = (value: string) =>
    platformTheme[value] ?? { bg: 'bg-muted', rotate: '', image: '' };

const platformConnectUrls: Record<string, string> = {
    bluesky: blueskyRoutes.connect.url(),
    facebook: facebookRoutes.connect.url(),
    instagram: instagramRoutes.connect.url(),
    'instagram-facebook': instagramFacebookRoutes.connect.url(),
    linkedin: linkedinRoutes.connect.url(),
    'linkedin-page': linkedinPageRoutes.connect.url(),
    mastodon: mastodonRoutes.connect.url(),
    pinterest: pinterestRoutes.connect.url(),
    threads: threadsRoutes.connect.url(),
    tiktok: tiktokRoutes.connect.url(),
    x: xRoutes.connect.url(),
    youtube: youtubeRoutes.connect.url(),
};

const requestUpgradeAndClose = (platformValue: string) => {
    requireNetwork(platformValue);
    open.value = false;
};

const openOAuthPopup = (platformValue: string) => {
    const url = platformConnectUrls[platformValue] ?? `/connect/${platformValue}`;
    const width = 600;
    const height = 700;
    const left = window.screenX + (window.outerWidth - width) / 2;
    const top = window.screenY + (window.outerHeight - height) / 2;

    open.value = false;

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
        <DialogContent class="sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>{{ $t('accounts.add_social_title') }}</DialogTitle>
                <DialogDescription>
                    {{ $t('accounts.add_social_description') }}
                </DialogDescription>
            </DialogHeader>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <div
                    v-for="platform in platforms"
                    :key="platform.value"
                    :class="[
                        'group relative flex flex-col items-center gap-3 rounded-xl border-2 border-foreground bg-card p-4 text-center shadow-xs transition-shadow hover:shadow-md',
                        !canConnectNetwork(platform.value) ? 'opacity-70' : '',
                    ]"
                >
                    <!-- Lock badge for gated networks (hover tooltip explains why) -->
                    <TooltipProvider v-if="!canConnectNetwork(platform.value)" :delay-duration="100">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <span
                                    class="absolute -top-2 -right-2 inline-flex size-6 cursor-help items-center justify-center rounded-full border-2 border-foreground bg-amber-200 text-foreground shadow-2xs"
                                    :aria-label="$t('accounts.upgrade_to_connect', { platform: platform.label })"
                                >
                                    <IconLock class="size-3.5" stroke-width="3" />
                                </span>
                            </TooltipTrigger>
                            <TooltipContent>
                                {{ $t('accounts.upgrade_to_connect', { platform: platform.label }) }}
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>

                    <!-- "+" sticker badge appears only on hover so the grid doesn't feel cluttered. -->
                    <span
                        v-else
                        class="pointer-events-none absolute -top-2 -right-2 inline-flex size-6 items-center justify-center rounded-full border-2 border-foreground bg-violet-200 text-foreground opacity-0 shadow-2xs transition-all group-hover:rotate-90 group-hover:scale-110 group-hover:opacity-100"
                        aria-hidden="true"
                    >
                        <IconPlus class="size-3.5" stroke-width="3" />
                    </span>

                    <div
                        :class="[
                            themeFor(platform.value).bg,
                            themeFor(platform.value).rotate,
                            'inline-flex size-16 items-center justify-center rounded-2xl border-2 border-foreground shadow-sm transition-transform group-hover:!rotate-0',
                        ]"
                    >
                        <img
                            :src="themeFor(platform.value).image"
                            :alt="platform.label"
                            class="size-9 rounded-lg"
                            loading="lazy"
                        />
                    </div>

                    <div class="flex-1">
                        <span class="block text-sm font-semibold text-foreground">
                            <template v-if="platform.label.includes('(')">
                                {{ platform.label.split('(')[0].trim() }}
                            </template>
                            <template v-else>{{ platform.label }}</template>
                        </span>
                        <p class="mt-0.5 line-clamp-2 text-xs leading-tight text-foreground/60">
                            {{ getPlatformDescription(platform.value) }}
                        </p>
                    </div>

                    <Button
                        v-if="!canConnectNetwork(platform.value)"
                        size="sm"
                        class="mt-auto w-full bg-amber-300 font-semibold text-foreground hover:bg-amber-400"
                        @click="requestUpgradeAndClose(platform.value)"
                    >
                        {{ $t('accounts.upgrade_cta') }}
                    </Button>
                    <Button
                        v-else
                        size="sm"
                        class="mt-auto w-full"
                        @click="openOAuthPopup(platform.value)"
                    >
                        {{ $t('accounts.connect_cta') }}
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
