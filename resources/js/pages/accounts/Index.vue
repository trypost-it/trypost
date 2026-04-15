<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { IconAffiliate, IconAlertCircle, IconCheck, IconExternalLink, IconPlus, IconRefresh, IconTrash } from '@tabler/icons-vue';
import { ref } from 'vue';

import AddSocialDialog, { type AvailablePlatform } from '@/components/accounts/AddSocialDialog.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import date from '@/date';
import AppLayout from '@/layouts/AppLayout.vue';
import { disconnect as disconnectAccount, toggle as toggleAccount } from '@/routes/app/accounts';
interface SocialAccount {
    id: string;
    platform: string;
    platform_user_id: string;
    username: string;
    display_name: string;
    avatar_url: string;
    status: 'connected' | 'disconnected' | 'token_expired' | null;
    is_active: boolean;
    error_message: string | null;
    created_at: string;
}

interface Props {
    accounts: SocialAccount[];
    platforms: AvailablePlatform[];
}

const props = defineProps<Props>();

const isAddDialogOpen = ref(false);
const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);

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

const getProfileUrl = (platform: string, username: string | null, platformUserId: string | null = null): string | null => {
    if (platform === 'facebook') {
        const identifier = username || platformUserId;
        return identifier ? `https://facebook.com/${identifier}` : null;
    }

    if (!username) return null;

    const urls: Record<string, string> = {
        'linkedin': `https://linkedin.com/in/${username}`,
        'linkedin-page': `https://linkedin.com/company/${username}`,
        'x': `https://x.com/${username}`,
        'tiktok': `https://tiktok.com/@${username}`,
        'instagram': `https://instagram.com/${username}`,
        'instagram-facebook': `https://instagram.com/${username}`,
        'youtube': `https://youtube.com/@${username}`,
        'threads': `https://threads.net/@${username}`,
        'bluesky': `https://bsky.app/profile/${username}`,
        'pinterest': `https://pinterest.com/${username}`,
    };
    return urls[platform] || null;
};

const isDisconnected = (account: SocialAccount): boolean => {
    return account.status === 'disconnected' || account.status === 'token_expired';
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

const handleToggle = (accountId: string) => {
    router.put(toggleAccount.url(accountId), {}, {
        preserveScroll: true,
    });
};

const handleDisconnect = (accountId: string) => {
    deleteModal.value?.open({
        url: disconnectAccount.url(accountId),
    });
};
</script>

<template>
    <Head :title="$t('accounts.page_title')" />

    <AppLayout :title="$t('accounts.page_title')">
        <template #header-actions>
            <Button @click="isAddDialogOpen = true">
                {{ $t('accounts.add_social') }}
            </Button>
        </template>

        <div class="flex flex-col gap-6 p-6">
            <EmptyState
                v-if="props.accounts.length === 0"
                :icon="IconAffiliate"
                :title="$t('accounts.no_accounts')"
                :description="$t('accounts.no_accounts_description')"
            />

            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <Card
                    v-for="account in props.accounts"
                    :key="account.id"
                    class="overflow-hidden"
                    :class="{
                        'border-red-500/30': isDisconnected(account),
                    }"
                >
                    <CardContent class="p-4">
                        <div class="flex flex-col items-center text-center">
                            <!-- Avatar with platform badge -->
                            <div class="relative mb-3">
                                <Avatar class="h-16 w-16">
                                    <AvatarImage v-if="account.avatar_url" :src="account.avatar_url" />
                                    <AvatarFallback class="text-lg">
                                        {{ account.display_name?.charAt(0) }}
                                    </AvatarFallback>
                                </Avatar>
                                <img
                                    :src="getPlatformLogo(account.platform)"
                                    :alt="account.platform"
                                    class="absolute -bottom-1 -right-1 h-6 w-6 rounded-full border-2 border-background object-contain"
                                />
                                <div
                                    v-if="!isDisconnected(account)"
                                    class="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-green-500 text-white ring-2 ring-background"
                                >
                                    <IconCheck class="h-2 w-2" />
                                </div>
                                <div
                                    v-else
                                    class="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-white ring-2 ring-background"
                                >
                                    <IconAlertCircle class="h-2 w-2" />
                                </div>
                            </div>

                            <!-- Name and username -->
                            <p class="font-semibold truncate max-w-full">{{ account.display_name }}</p>
                            <p class="text-sm text-muted-foreground truncate max-w-full">
                                @{{ account.username || account.display_name }}
                            </p>

                            <!-- Added date -->
                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ $t('accounts.added', { date: date.diffForHumans(account.created_at) }) }}
                            </p>

                            <!-- Disconnected warning -->
                            <div v-if="isDisconnected(account)" class="mt-3 w-full rounded-md bg-red-100 px-2 py-1.5 text-xs text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                {{ $t('accounts.connection_lost') }}
                            </div>

                            <!-- Actions -->
                            <div class="mt-3 flex items-center gap-1">
                                <TooltipProvider v-if="isDisconnected(account)">
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button variant="ghost" size="icon" class="h-8 w-8 text-amber-600 hover:text-amber-700" @click="openOAuthPopup(account.platform)">
                                                <IconRefresh class="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{{ $t('accounts.reconnect_account') }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>

                                <TooltipProvider v-if="getProfileUrl(account.platform, account.username, account.platform_user_id)">
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button variant="ghost" size="icon" class="h-8 w-8" as-child>
                                                <a :href="getProfileUrl(account.platform, account.username, account.platform_user_id)!" target="_blank">
                                                    <IconExternalLink class="h-4 w-4" />
                                                </a>
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{{ $t('accounts.view_profile') }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>

                                <Switch :model-value="account.is_active" @update:model-value="handleToggle(account.id)" />

                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive hover:text-destructive" @click="handleDisconnect(account.id)">
                                                <IconTrash class="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{{ $t('accounts.disconnect') }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Add Social Card -->
                <Card class="cursor-pointer overflow-hidden border-dashed transition-all hover:border-primary hover:shadow-md" @click="isAddDialogOpen = true">
                    <CardContent class="flex h-full flex-col items-center justify-center p-4">
                        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-muted mb-3">
                            <IconPlus class="h-6 w-6 text-muted-foreground" />
                        </div>
                        <p class="font-semibold">{{ $t('accounts.add_social') }}</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>

    <AddSocialDialog v-model:open="isAddDialogOpen" :platforms="platforms" />

    <ConfirmDeleteModal
        ref="deleteModal"
        :title="$t('accounts.disconnect_modal.title')"
        :description="$t('accounts.disconnect_modal.description')"
        :action="$t('accounts.disconnect_modal.confirm')"
        :cancel="$t('accounts.disconnect_modal.cancel')"
    />
</template>
