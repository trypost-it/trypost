<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted } from 'vue';
import { AlertCircle, Check, ExternalLink, RefreshCw, Trash2 } from 'lucide-vue-next';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

export interface SocialAccount {
    id: string;
    platform: string;
    username: string;
    display_name: string;
    avatar_url: string;
    status: 'connected' | 'disconnected' | 'token_expired' | null;
    error_message: string | null;
}

export interface Platform {
    value: string;
    label: string;
    color: string;
    connected: boolean;
    account: SocialAccount | null;
}

interface Props {
    platforms: Platform[];
    showDisconnect?: boolean;
    showReconnect?: boolean;
    showViewProfile?: boolean;
    columns?: 2 | 3 | 4;
}

const props = withDefaults(defineProps<Props>(), {
    showDisconnect: true,
    showReconnect: true,
    showViewProfile: true,
    columns: 4,
});

const getConnectUrl = (platformValue: string): string => {
    return `/connect/${platformValue}`;
};

const openOAuthPopup = (platformValue: string) => {
    const url = getConnectUrl(platformValue);
    const width = 600;
    const height = 700;
    const left = window.screenX + (window.outerWidth - width) / 2;
    const top = window.screenY + (window.outerHeight - height) / 2;

    window.open(
        url,
        'oauth-popup',
        `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`
    );
};

const handleOAuthMessage = (event: MessageEvent) => {
    if (event.origin !== window.location.origin) return;
    if (event.data?.type !== 'social-oauth-callback') return;

    // Reload the page to get fresh data
    router.reload();
};

onMounted(() => {
    window.addEventListener('message', handleOAuthMessage);
});

onUnmounted(() => {
    window.removeEventListener('message', handleOAuthMessage);
});

const gridClass = computed(() => {
    switch (props.columns) {
        case 2:
            return 'sm:grid-cols-2';
        case 3:
            return 'sm:grid-cols-2 lg:grid-cols-3';
        case 4:
        default:
            return 'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4';
    }
});

const emit = defineEmits<{
    disconnect: [accountId: string];
}>();

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

const getProfileUrl = (platform: string, username: string | null): string | null => {
    if (!username) return null;

    const urls: Record<string, string> = {
        'linkedin': `https://linkedin.com/in/${username}`,
        'linkedin-page': `https://linkedin.com/company/${username}`,
        'x': `https://x.com/${username}`,
        'tiktok': `https://tiktok.com/@${username}`,
        'instagram': `https://instagram.com/${username}`,
        'facebook': `https://facebook.com/${username}`,
        'youtube': `https://youtube.com/@${username}`,
        'threads': `https://threads.net/@${username}`,
        'bluesky': `https://bsky.app/profile/${username}`,
        'pinterest': `https://pinterest.com/${username}`,
    };
    return urls[platform] || null;
};

const isDisconnected = (account: SocialAccount | null): boolean => {
    if (!account) return false;
    return account.status === 'disconnected' || account.status === 'token_expired';
};
</script>

<template>
    <div class="grid gap-4" :class="gridClass">
        <div
            v-for="platform in platforms"
            :key="platform.value"
            class="group relative overflow-hidden rounded-xl border bg-card transition-all hover:shadow-md"
            :class="{
                'border-green-500/30 bg-green-50/50 dark:bg-green-950/20': platform.connected && !isDisconnected(platform.account),
                'border-red-500/30 bg-red-50/50 dark:bg-red-950/20': platform.connected && isDisconnected(platform.account),
            }"
        >
            <!-- Platform Header -->
            <div class="flex items-center gap-3 p-4">
                <div class="relative">
                    <img
                        :src="getPlatformLogo(platform.value)"
                        :alt="platform.label"
                        class="h-12 w-12 rounded-lg object-contain"
                    />
                    <div
                        v-if="platform.connected && !isDisconnected(platform.account)"
                        class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-green-500 text-white ring-2 ring-white dark:ring-gray-900"
                    >
                        <Check class="h-3 w-3" />
                    </div>
                    <div
                        v-else-if="platform.connected && isDisconnected(platform.account)"
                        class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-white ring-2 ring-white dark:ring-gray-900"
                    >
                        <AlertCircle class="h-3 w-3" />
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <h3 class="font-semibold truncate">{{ platform.label }}</h3>
                    </div>
                    <p v-if="platform.connected && platform.account" class="text-sm text-muted-foreground truncate">
                        @{{ platform.account.username || platform.account.display_name }}
                    </p>
                    <p v-else class="text-sm text-muted-foreground">
                        Not connected
                    </p>
                </div>
            </div>

            <!-- Connected State -->
            <div v-if="platform.connected && platform.account" class="border-t px-4 py-3">
                <!-- Disconnected Warning -->
                <div v-if="isDisconnected(platform.account)" class="mb-3 flex items-start gap-2 rounded-lg bg-red-100 p-2 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400">
                    <AlertCircle class="h-4 w-4 mt-0.5 shrink-0" />
                    <div class="flex-1 min-w-0">
                        <p class="font-medium">Connection lost</p>
                        <p v-if="platform.account.error_message" class="text-xs truncate opacity-80">
                            {{ platform.account.error_message }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Avatar class="h-8 w-8">
                            <AvatarImage :src="platform.account.avatar_url" />
                            <AvatarFallback class="text-xs">
                                {{ platform.account.display_name?.charAt(0) }}
                            </AvatarFallback>
                        </Avatar>
                        <span class="text-sm font-medium truncate max-w-[120px]">
                            {{ platform.account.display_name }}
                        </span>
                    </div>
                    <div class="flex items-center gap-1">
                        <!-- Reconnect button for disconnected accounts -->
                        <TooltipProvider v-if="showReconnect && isDisconnected(platform.account)">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <button
                                        @click="openOAuthPopup(platform.value)"
                                        class="p-2 text-amber-600 hover:text-amber-700 transition-colors"
                                    >
                                        <RefreshCw class="h-4 w-4" />
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Reconnect account</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                        <TooltipProvider v-if="showViewProfile && getProfileUrl(platform.value, platform.account.username)">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <a
                                        :href="getProfileUrl(platform.value, platform.account.username)!"
                                        target="_blank"
                                        class="p-2 text-muted-foreground hover:text-foreground transition-colors"
                                    >
                                        <ExternalLink class="h-4 w-4" />
                                    </a>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>View profile</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                        <TooltipProvider v-if="showDisconnect">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <button
                                        @click="emit('disconnect', platform.account.id)"
                                        class="p-2 text-muted-foreground hover:text-red-600 transition-colors"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Disconnect</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                </div>
            </div>

            <!-- Not Connected State -->
            <div v-else class="border-t px-4 py-3">
                <Button variant="outline" class="w-full" size="sm" @click="openOAuthPopup(platform.value)">
                    Connect
                </Button>
            </div>
        </div>
    </div>
</template>
