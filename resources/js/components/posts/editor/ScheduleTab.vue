<script setup lang="ts">
import { IconAlertCircle, IconCircleCheck, IconExternalLink, IconLoader2 } from '@tabler/icons-vue';
import { computed } from 'vue';

import FacebookSettings from '@/components/posts/editor/FacebookSettings.vue';
import InstagramSettings from '@/components/posts/editor/InstagramSettings.vue';
import LinkedInSettings from '@/components/posts/editor/LinkedInSettings.vue';
import PinterestSettings from '@/components/posts/editor/PinterestSettings.vue';
import TikTokSettings from '@/components/posts/editor/TikTokSettings.vue';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { getPlatformLabel, getPlatformLogo } from '@/composables/usePlatformLogo';

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

interface PlatformConfig {
    id: string;
    platform: string;
    maxContentLength: number;
    maxImages: number;
    allowedMediaTypes: string[];
    supportsTextOnly: boolean;
    requiresContent: boolean;
    publishConfig: Record<string, any>;
}

interface TikTokCreatorInfo {
    creator_nickname: string | null;
    creator_username: string | null;
    creator_avatar_url: string | null;
    privacy_level_options: string[];
    comment_disabled: boolean;
    duet_disabled: boolean;
    stitch_disabled: boolean;
    max_video_post_duration_sec: number | null;
}

interface MediaItem {
    id: string;
    url: string;
    type?: string;
    mime_type?: string;
    meta?: Record<string, any>;
}

const props = defineProps<{
    postPlatforms: PostPlatform[];
    selectedPlatformIds: string[];
    labels: Label[];
    selectedLabelIds: string[];
    isReadOnly: boolean;
    platformConfigs: Record<string, PlatformConfig>;
    platformMeta: Record<string, Record<string, any>>;
    platformContentTypes: Record<string, string>;
    platformIssues?: Record<string, string>;
    tiktokCreatorInfos?: Record<string, TikTokCreatorInfo> | null;
    media?: MediaItem[];
}>();

const emit = defineEmits<{
    togglePlatform: [platformId: string];
    toggleLabel: [labelId: string];
    'update:platformMeta': [platformId: string, meta: Record<string, any>];
    'update:platformContentType': [platformId: string, contentType: string];
}>();

const selectedTikTokPlatforms = computed(() =>
    props.postPlatforms.filter(
        (pp) => pp.platform === 'tiktok' && props.selectedPlatformIds.includes(pp.id),
    ),
);

const selectedInstagramPlatforms = computed(() =>
    props.postPlatforms.filter(
        (pp) => ['instagram', 'instagram-facebook'].includes(pp.platform)
            && props.selectedPlatformIds.includes(pp.id),
    ),
);

const selectedFacebookPlatforms = computed(() =>
    props.postPlatforms.filter(
        (pp) => pp.platform === 'facebook' && props.selectedPlatformIds.includes(pp.id),
    ),
);

const selectedLinkedInPlatforms = computed(() =>
    props.postPlatforms.filter(
        (pp) => ['linkedin', 'linkedin-page'].includes(pp.platform)
            && props.selectedPlatformIds.includes(pp.id),
    ),
);

const selectedPinterestPlatforms = computed(() =>
    props.postPlatforms.filter(
        (pp) => pp.platform === 'pinterest' && props.selectedPlatformIds.includes(pp.id),
    ),
);

const getPublishConfig = (pp: PostPlatform): Record<string, any> | null =>
    pp.social_account_id ? props.platformConfigs[pp.social_account_id]?.publishConfig ?? null : null;

const getCreatorInfo = (pp: PostPlatform): TikTokCreatorInfo | null =>
    pp.social_account_id ? props.tiktokCreatorInfos?.[pp.social_account_id] ?? null : null;

const videoDurationSec = computed(() => {
    const video = props.media?.find((m) => m.type === 'video' || m.mime_type?.startsWith('video/'));
    const duration = video?.meta?.duration;
    return typeof duration === 'number' ? Math.ceil(duration) : null;
});

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
            <div class="flex flex-wrap gap-3">
                <TooltipProvider v-for="pp in postPlatforms" :key="pp.id" :delay-duration="200">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <button
                                type="button"
                                class="flex w-20 flex-col items-center gap-1.5 transition-opacity"
                                :class="[
                                    platformIssues?.[pp.id] && !selectedPlatformIds.includes(pp.id) ? 'cursor-not-allowed opacity-40' : '',
                                    platformIssues?.[pp.id] && selectedPlatformIds.includes(pp.id) ? 'opacity-100' : '',
                                    !platformIssues?.[pp.id] ? 'opacity-100 hover:opacity-90' : '',
                                ]"
                                :disabled="Boolean(platformIssues?.[pp.id]) && !selectedPlatformIds.includes(pp.id)"
                                @click="emit('togglePlatform', pp.id)"
                            >
                                <div class="relative">
                                    <Avatar
                                        :src="getPlatformAvatar(pp)"
                                        :name="getPlatformDisplayName(pp)"
                                        class="h-10 w-10 shrink-0 rounded-full ring-2 ring-offset-2"
                                        :class="[
                                            platformIssues?.[pp.id] && selectedPlatformIds.includes(pp.id) ? 'ring-destructive' : '',
                                            !platformIssues?.[pp.id] && selectedPlatformIds.includes(pp.id) ? 'ring-primary' : '',
                                            !selectedPlatformIds.includes(pp.id) ? 'ring-transparent' : '',
                                        ]"
                                    />
                                    <img
                                        :src="getPlatformLogo(pp.platform)"
                                        :alt="pp.platform"
                                        class="absolute -bottom-1.5 -right-1.5 h-5 w-5 rounded-full bg-background object-contain ring-1 ring-border"
                                    />
                                    <Badge
                                        v-if="platformIssues?.[pp.id] && selectedPlatformIds.includes(pp.id)"
                                        variant="destructive"
                                        class="absolute -top-1 -right-1 h-4 w-4 p-0"
                                    >
                                        <IconAlertCircle class="h-2.5 w-2.5" />
                                    </Badge>
                                    <Badge v-else-if="pp.status === 'published'" variant="default" class="absolute -top-1 -right-1 h-4 w-4 p-0">
                                        <IconCircleCheck class="h-2.5 w-2.5" />
                                    </Badge>
                                    <Badge v-else-if="pp.status === 'failed'" variant="destructive" class="absolute -top-1 -right-1 h-4 w-4 p-0 text-[9px]">!</Badge>
                                </div>
                                <span class="line-clamp-2 text-center text-xs leading-tight">{{ getPlatformDisplayName(pp) }}</span>
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <div class="space-y-0.5 text-xs">
                                <p class="font-semibold">{{ getPlatformDisplayName(pp) }}<span v-if="pp.social_account?.username" class="font-normal opacity-80">&nbsp;·&nbsp;@{{ pp.social_account.username }}</span></p>
                                <p class="opacity-70">{{ getPlatformLabel(pp.platform) }}</p>
                                <p v-if="platformIssues?.[pp.id]" class="mt-1 max-w-xs text-destructive-foreground/90">
                                    {{ platformIssues[pp.id] }}
                                </p>
                            </div>
                        </TooltipContent>
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
                        <img :src="getPlatformLogo(pp.platform)" :alt="pp.platform" class="h-4 w-4 object-contain" />
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

        <!-- Platform-specific settings -->
        <div v-if="selectedTikTokPlatforms.length > 0" class="space-y-4">
            <TikTokSettings
                v-for="pp in selectedTikTokPlatforms"
                :key="pp.id"
                :social-account="pp.social_account"
                :publish-config="getPublishConfig(pp)"
                :creator-info="getCreatorInfo(pp)"
                :creator-info-loading="tiktokCreatorInfos === undefined || tiktokCreatorInfos === null"
                :video-duration-sec="videoDurationSec"
                :meta="platformMeta[pp.id] ?? {}"
                :disabled="isReadOnly"
                @update:meta="emit('update:platformMeta', pp.id, $event)"
            />
        </div>

        <div v-if="selectedInstagramPlatforms.length > 0" class="space-y-4">
            <InstagramSettings
                v-for="pp in selectedInstagramPlatforms"
                :key="pp.id"
                :social-account="pp.social_account"
                :content-type="platformContentTypes[pp.id] ?? ''"
                :media="media ?? []"
                :meta="platformMeta[pp.id] ?? {}"
                :disabled="isReadOnly"
                @update:content-type="emit('update:platformContentType', pp.id, $event)"
                @update:meta="emit('update:platformMeta', pp.id, $event)"
            />
        </div>

        <div v-if="selectedFacebookPlatforms.length > 0" class="space-y-4">
            <FacebookSettings
                v-for="pp in selectedFacebookPlatforms"
                :key="pp.id"
                :social-account="pp.social_account"
                :content-type="platformContentTypes[pp.id] ?? ''"
                :media="media ?? []"
                :disabled="isReadOnly"
                @update:content-type="emit('update:platformContentType', pp.id, $event)"
            />
        </div>

        <div v-if="selectedLinkedInPlatforms.length > 0" class="space-y-4">
            <LinkedInSettings
                v-for="pp in selectedLinkedInPlatforms"
                :key="pp.id"
                :social-account="pp.social_account"
                :platform="pp.platform"
                :content-type="platformContentTypes[pp.id] ?? ''"
                :media="media ?? []"
                :disabled="isReadOnly"
                @update:content-type="emit('update:platformContentType', pp.id, $event)"
            />
        </div>

        <div v-if="selectedPinterestPlatforms.length > 0" class="space-y-4">
            <PinterestSettings
                v-for="pp in selectedPinterestPlatforms"
                :key="pp.id"
                :social-account="pp.social_account"
                :content-type="platformContentTypes[pp.id] ?? ''"
                :media="media ?? []"
                :disabled="isReadOnly"
                @update:content-type="emit('update:platformContentType', pp.id, $event)"
            />
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
