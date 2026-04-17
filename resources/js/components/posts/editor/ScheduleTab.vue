<script setup lang="ts">
import {
    IconBrandBluesky,
    IconBrandFacebook,
    IconBrandInstagram,
    IconBrandLinkedin,
    IconBrandMastodon,
    IconBrandPinterest,
    IconBrandThreads,
    IconBrandTiktok,
    IconBrandX,
    IconBrandYoutube,
    IconCircleCheck,
    IconExternalLink,
    IconLoader2,
} from '@tabler/icons-vue';
import { type Component } from 'vue';

import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    avatar_url: string | null;
}

interface PostPlatform {
    id: string;
    social_account_id: string | null;
    enabled: boolean;
    platform: string;
    platform_name: string | null;
    platform_username: string | null;
    platform_avatar: string | null;
    content_type: string | null;
    status: string;
    platform_url: string | null;
    error_message: string | null;
    published_at: string | null;
    social_account: SocialAccount | null;
    meta?: Record<string, any>;
}

interface Label {
    id: string;
    name: string;
    color: string;
}

const props = defineProps<{
    postPlatforms: PostPlatform[];
    selectedPlatformIds: string[];
    labels: Label[];
    selectedLabelIds: string[];
    isReadOnly: boolean;
}>();

const emit = defineEmits<{
    togglePlatform: [platformId: string];
    toggleLabel: [labelId: string];
}>();

const platformIcons: Record<string, Component> = {
    linkedin: IconBrandLinkedin,
    'linkedin-page': IconBrandLinkedin,
    x: IconBrandX,
    tiktok: IconBrandTiktok,
    youtube: IconBrandYoutube,
    facebook: IconBrandFacebook,
    instagram: IconBrandInstagram,
    'instagram-facebook': IconBrandInstagram,
    threads: IconBrandThreads,
    pinterest: IconBrandPinterest,
    bluesky: IconBrandBluesky,
    mastodon: IconBrandMastodon,
};

const getPlatformIcon = (platform: string): Component => platformIcons[platform] || IconBrandX;

const getPlatformDisplayName = (pp: PostPlatform): string =>
    pp.social_account?.display_name ?? pp.platform_name ?? pp.platform;

const getPlatformAvatar = (pp: PostPlatform): string | null =>
    pp.social_account?.avatar_url ?? pp.platform_avatar ?? null;

</script>

<template>
    <div class="space-y-6">
        <div>
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                {{ $t('posts.edit.publish_to') }}
            </p>
            <div class="flex flex-wrap gap-2">
                <TooltipProvider v-for="pp in postPlatforms" :key="pp.id">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <button
                                type="button"
                                class="relative flex items-center gap-2 rounded-lg border px-3 py-2 text-sm transition-all"
                                :class="selectedPlatformIds.includes(pp.id) ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-border opacity-50 hover:opacity-80'"
                                @click="emit('togglePlatform', pp.id)"
                            >
                                <Avatar :src="getPlatformAvatar(pp)" :name="getPlatformDisplayName(pp)" class="h-6 w-6 shrink-0 rounded-full" />
                                <component :is="getPlatformIcon(pp.platform)" class="h-3.5 w-3.5 text-muted-foreground" />
                                <Badge v-if="pp.status === 'published'" variant="default" class="absolute -top-1.5 -right-1.5 h-4 w-4 p-0">
                                    <IconCircleCheck class="h-2.5 w-2.5" />
                                </Badge>
                                <Badge v-else-if="pp.status === 'failed'" variant="destructive" class="absolute -top-1.5 -right-1.5 h-4 w-4 p-0 text-[9px]">!</Badge>
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>{{ getPlatformDisplayName(pp) }}</TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>
        </div>

        <div v-if="postPlatforms.some(pp => pp.status !== 'pending')">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                {{ $t('posts.edit.platform_status') }}
            </p>
            <div class="space-y-2">
                <div v-for="pp in postPlatforms.filter(p => p.enabled)" :key="pp.id" class="flex items-center justify-between rounded-lg border p-3">
                    <div class="flex items-center gap-2">
                        <component :is="getPlatformIcon(pp.platform)" class="h-4 w-4 text-muted-foreground" />
                        <span class="text-sm">{{ getPlatformDisplayName(pp) }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge v-if="pp.status === 'published'" variant="default">{{ $t('posts.edit.status.published') }}</Badge>
                        <Badge v-else-if="pp.status === 'publishing'" variant="secondary">
                            <IconLoader2 class="mr-1 h-3 w-3 animate-spin" />
                            {{ $t('posts.edit.status.publishing') }}
                        </Badge>
                        <Badge v-else-if="pp.status === 'failed'" variant="destructive">{{ $t('posts.edit.status.failed') }}</Badge>
                        <a v-if="pp.platform_url" :href="pp.platform_url" target="_blank" rel="noopener noreferrer">
                            <IconExternalLink class="h-4 w-4 text-muted-foreground hover:text-foreground" />
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                {{ $t('posts.edit.labels') }}
            </p>
            <div v-if="labels.length > 0" class="flex flex-wrap gap-2">
                <button
                    v-for="label in labels"
                    :key="label.id"
                    type="button"
                    class="flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs transition-colors"
                    :class="selectedLabelIds.includes(label.id) ? 'border-primary bg-primary/10' : 'border-border opacity-70 hover:opacity-100'"
                    :disabled="isReadOnly"
                    @click="emit('toggleLabel', label.id)"
                >
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: label.color }" />
                    <span class="truncate">{{ label.name }}</span>
                </button>
            </div>
            <p v-else class="text-sm text-muted-foreground">{{ $t('posts.edit.no_labels') }}</p>
        </div>
    </div>
</template>
