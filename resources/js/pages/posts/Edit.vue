<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import {
    IconCalendar,
    IconCircleCheck,
    IconLoader2,
    IconTrash,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, onUnmounted, ref, watch } from 'vue';

import AiGenerateDialog from '@/components/posts/ai/AiGenerateDialog.vue';
import AiReviewDialog from '@/components/posts/ai/AiReviewDialog.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import PostEditorComposer from '@/components/posts/editor/PostEditorComposer.vue';
import PostEditorSidebar from '@/components/posts/editor/PostEditorSidebar.vue';
import PickTimePopover from '@/components/posts/PickTimePopover.vue';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { getMediaItemIssue } from '@/composables/useMedia';
import { getMediaRulesForContentType } from '@/composables/useMediaRules';
import dayjs from '@/dayjs';
import debounce from '@/debounce';
import AppLayout from '@/layouts/AppLayout.vue';
import { destroy as destroyPost, index as postsIndex, update as updatePost } from '@/routes/app/posts';
import type { BreadcrumbItem } from '@/types';

interface MediaItem {
    id: string;
    path: string;
    url: string;
    type?: string;
    mime_type?: string;
    original_filename?: string;
    size?: number;
    meta?: { width?: number; height?: number; duration?: number };
}

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

interface Post {
    id: string;
    content: string;
    media: MediaItem[];
    status: string;
    scheduled_at: string | null;
    published_at: string | null;
    post_platforms: PostPlatform[];
    labels?: { id: string; name: string }[];
}

interface Workspace {
    id: string;
    name: string;
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

const props = defineProps<{
    workspace: Workspace;
    post: Post;
    socialAccounts: SocialAccount[];
    platformConfigs: Record<string, any>;
    pinterestBoards: any[];
    tiktokCreatorInfos?: Record<string, TikTokCreatorInfo> | null;
    labels: { id: string; name: string; color: string }[];
    signatures: { id: string; name: string; content: string }[];
    authUserId: string;
}>();

const post = computed(() => props.post);
const isReadOnly = computed(() => ['publishing', 'published', 'partially_published'].includes(post.value.status));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('posts.title'), href: postsIndex.url() },
    { title: trans('posts.edit.title') },
]);
const isPublishing = computed(() => post.value.status === 'publishing');
const isPublished = computed(() => ['published', 'partially_published'].includes(post.value.status));

// Content
const content = ref(post.value.content || '');
const media = ref<MediaItem[]>(post.value.media || []);

// Platforms
const selectedPlatformIds = ref<string[]>(
    post.value.post_platforms.filter((pp) => pp.enabled).map((pp) => pp.id),
);

// Per-platform meta (TikTok settings, Pinterest board, etc.)
const platformMeta = ref<Record<string, Record<string, any>>>(
    Object.fromEntries(post.value.post_platforms.map((pp) => [pp.id, { ...(pp.meta ?? {}) }])),
);

const updatePlatformMeta = (platformId: string, meta: Record<string, any>) => {
    platformMeta.value = { ...platformMeta.value, [platformId]: meta };
};

// Per-platform content_type (Instagram Feed/Reel/Story, Facebook Post/Reel/Story, etc.)
const platformContentTypes = ref<Record<string, string>>(
    Object.fromEntries(post.value.post_platforms.map((pp) => [pp.id, pp.content_type ?? ''])),
);

const updatePlatformContentType = (platformId: string, contentType: string) => {
    platformContentTypes.value = { ...platformContentTypes.value, [platformId]: contentType };
};

const platformLimits = computed(() => {
    const seen = new Set<string>();
    const result: { platform: string; maxLength: number }[] = [];
    for (const pp of post.value.post_platforms) {
        if (! selectedPlatformIds.value.includes(pp.id)) continue;
        if (seen.has(pp.platform)) continue;
        const accountId = pp.social_account_id;
        const max = accountId ? props.platformConfigs[accountId]?.maxContentLength : null;
        if (typeof max === 'number' && max > 0) {
            seen.add(pp.platform);
            result.push({ platform: pp.platform, maxLength: max });
        }
    }
    return result;
});

const mediaIssues = computed(() => {
    const result: Record<string, { platform: string; reason: string }[]> = {};
    for (const item of media.value) {
        const issues: { platform: string; reason: string }[] = [];
        const seen = new Set<string>();
        for (const pp of post.value.post_platforms) {
            if (! selectedPlatformIds.value.includes(pp.id)) continue;
            if (seen.has(pp.platform)) continue;
            const contentType = platformContentTypes.value[pp.id] ?? pp.content_type ?? '';
            const reason = getMediaItemIssue(item, contentType);
            if (reason) {
                seen.add(pp.platform);
                issues.push({ platform: pp.platform, reason });
            }
        }
        if (issues.length > 0) result[item.id] = issues;
    }
    return result;
});

const getMediaIncompatibilityReason = (contentType: string, mediaItems: MediaItem[]): string | null => {
    const rules = getMediaRulesForContentType(contentType);
    const videos = mediaItems.filter((m) => m.type === 'video' || m.mime_type?.startsWith('video/'));
    const images = mediaItems.filter((m) => m.type === 'image' || m.mime_type?.startsWith('image/'));
    const gifs = mediaItems.filter((m) => m.mime_type === 'image/gif');
    const total = mediaItems.length;

    // Order matters: type-mismatch errors (image vs video vs gif) are more
    // fundamental than count errors. Telling the user "YouTube doesn't accept
    // images" is more actionable than "too many files" when they're mixing types.
    if (rules.requiresMedia && total === 0) return trans('posts.edit.compliance.requires_media');
    if (!rules.acceptVideos && videos.length > 0) return trans('posts.edit.compliance.no_videos');
    if (!rules.acceptImages && images.length > 0) return trans('posts.edit.compliance.no_images');
    if (!rules.acceptsGif && gifs.length > 0) return trans('posts.edit.compliance.no_gifs');
    if (total > rules.maxFiles) return trans('posts.edit.compliance.too_many_files', { max: String(rules.maxFiles) });
    if (rules.minFiles && total < rules.minFiles) return trans('posts.edit.compliance.too_few_files', { min: String(rules.minFiles) });

    for (const m of mediaItems) {
        const isVideo = m.type === 'video' || m.mime_type?.startsWith('video/');
        const size = m.size ?? 0;
        const duration = m.meta?.duration ?? 0;
        const width = m.meta?.width ?? 0;
        const height = m.meta?.height ?? 0;

        if (isVideo) {
            if (rules.maxVideoBytes && size > 0 && size > rules.maxVideoBytes) return trans('posts.edit.compliance.video_too_large');
            if (rules.maxVideoDurationSec && duration > 0 && duration > rules.maxVideoDurationSec) {
                return trans('posts.edit.compliance.video_too_long', { seconds: String(rules.maxVideoDurationSec) });
            }
        } else if (rules.maxImageBytes && size > 0 && size > rules.maxImageBytes) {
            return trans('posts.edit.compliance.image_too_large');
        }

        if (width > 0 && height > 0 && (rules.aspectRatioMin || rules.aspectRatioMax)) {
            const ratio = width / height;
            if (rules.aspectRatioMin && ratio < rules.aspectRatioMin) return trans('posts.edit.compliance.aspect_ratio_invalid');
            if (rules.aspectRatioMax && ratio > rules.aspectRatioMax) return trans('posts.edit.compliance.aspect_ratio_invalid');
        }
    }

    return null;
};

const platformIssues = computed<Record<string, string>>(() => {
    const issues: Record<string, string> = {};

    for (const pp of post.value.post_platforms) {
        const contentType = platformContentTypes.value[pp.id];
        if (!contentType) {
            issues[pp.id] = trans('posts.edit.compliance.no_content_type');
            continue;
        }

        const reason = getMediaIncompatibilityReason(contentType, media.value);
        if (reason) issues[pp.id] = reason;
    }

    return issues;
});

const mediaCompliancePerPlatformValid = computed(
    () => selectedPlatformIds.value.every((id) => !platformIssues.value[id]),
);

// TikTok compliance per docs:
// - privacy_level must be explicitly selected
// - if disclosure toggle is ON, at least one sub-toggle must be selected
const tiktokComplianceValid = computed(() => {
    const tiktokPlatforms = post.value.post_platforms.filter(
        (pp) => pp.platform === 'tiktok' && selectedPlatformIds.value.includes(pp.id),
    );
    return tiktokPlatforms.every((pp) => {
        const meta = platformMeta.value[pp.id] ?? {};
        if (!meta.privacy_level) return false;
        if (meta.disclose && !meta.brand_organic_toggle && !meta.brand_content_toggle) return false;
        return true;
    });
});

const canSchedule = computed(
    () => mediaCompliancePerPlatformValid.value && tiktokComplianceValid.value,
);

const postActionTooltip = computed(() => {
    if (canSchedule.value) return '';

    const reasons = post.value.post_platforms
        .filter((pp) => selectedPlatformIds.value.includes(pp.id) && platformIssues.value[pp.id])
        .map((pp) => `${pp.platform_name ?? pp.platform}: ${platformIssues.value[pp.id]}`);

    if (reasons.length === 0) return trans('posts.edit.compliance_incomplete');

    return reasons.join('\n');
});

// Schedule
const getLocalSchedule = () => {
    if (!post.value.scheduled_at) return '';
    return dayjs.utc(post.value.scheduled_at).local().format('YYYY-MM-DDTHH:mm:00');
};
const scheduledDateTime = ref(getLocalSchedule());
const hasPickedTime = ref(post.value.status === 'scheduled' && !! post.value.scheduled_at);

const pickTimeLabel = computed(() => {
    if (! hasPickedTime.value || ! scheduledDateTime.value) {
        return trans('posts.edit.pick_time');
    }
    return dayjs(scheduledDateTime.value).format('MMM D, HH:mm');
});

// Labels
const selectedLabelIds = ref<string[]>(post.value.labels?.map((l) => l.id) || []);

// UI state
const isSubmitting = ref(false);
const isSaving = ref(false);
const showSaved = ref(false);
const isAiGenerateOpen = ref(false);
const isAiReviewOpen = ref(false);

const onAiGenerateApply = (newContent: string) => {
    content.value = newContent;
};

const onAiReviewApply = (original: string, suggestion: string) => {
    content.value = content.value.replace(original, suggestion);
};

const isPostActionDisabled = computed(
    () => isSubmitting.value || selectedPlatformIds.value.length === 0 || !canSchedule.value,
);
const queryParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
const initialTabFromQuery = (() => {
    const tab = queryParams?.get('tab');
    return ['preview', 'schedule', 'comments'].includes(tab ?? '') ? (tab as string) : 'schedule';
})();
const initialHighlightCommentId = queryParams?.get('comment') ?? null;
const activeTab = ref(initialTabFromQuery);
const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);
const editorSidebarRef = ref<InstanceType<typeof PostEditorSidebar> | null>(null);

const togglePlatform = (platformId: string) => {
    if (isReadOnly.value) return;
    const index = selectedPlatformIds.value.indexOf(platformId);
    if (index === -1) {
        selectedPlatformIds.value.push(platformId);
    } else {
        selectedPlatformIds.value.splice(index, 1);
    }
};

// Save logic
const getSubmitData = () => {
    const platforms = post.value.post_platforms
        .filter((pp) => selectedPlatformIds.value.includes(pp.id))
        .map((pp) => ({
            id: pp.id,
            content_type: platformContentTypes.value[pp.id] ?? pp.content_type,
            meta: platformMeta.value[pp.id] ?? pp.meta ?? {},
        }));

    return {
        content: content.value,
        media: media.value,
        platforms,
        scheduled_at: scheduledDateTime.value
            ? dayjs(scheduledDateTime.value).utc().format()
            : null,
        label_ids: selectedLabelIds.value,
    };
};

const save = () => {
    if (isSubmitting.value || isReadOnly.value || isSaving.value) return;

    const data = getSubmitData();

    isSaving.value = true;
    showSaved.value = false;

    router.put(updatePost.url(post.value.id), {
        status: post.value.status,
        ...data,
    }, {
        preserveScroll: true,
        onFinish: () => {
            isSaving.value = false;
            showSaved.value = true;
            setTimeout(() => { showSaved.value = false; }, 2000);
        },
    });
};

const debouncedSave = debounce(() => {
    if (!isReadOnly.value && !isSubmitting.value) {
        save();
    }
}, 1500);

const triggerAutosave = () => {
    if (!isReadOnly.value) {
        showSaved.value = false;
        debouncedSave();
    }
};

watch([content, media, selectedPlatformIds, scheduledDateTime, selectedLabelIds, platformMeta, platformContentTypes], triggerAutosave, { deep: true });

onUnmounted(() => {
    debouncedSave.cancel();
});

const submit = (status: string = 'scheduled') => {
    if (isSubmitting.value || isReadOnly.value) return;
    debouncedSave.cancel();

    const data = getSubmitData();
    isSubmitting.value = true;

    router.put(updatePost.url(post.value.id), {
        status,
        ...data,
    }, {
        onFinish: () => { isSubmitting.value = false; },
    });
};

const toggleLabel = (labelId: string) => {
    const index = selectedLabelIds.value.indexOf(labelId);
    if (index === -1) {
        selectedLabelIds.value.push(labelId);
    } else {
        selectedLabelIds.value.splice(index, 1);
    }
};

const deletePost = () => {
    if (isReadOnly.value) return;
    deleteModal.value?.open({ url: destroyPost.url(post.value.id) });
};

// Echo: listen for real-time platform status updates.
// Event fires when any post_platform completes publishing (success or fail).
// Full reload of the post prop so the new status + post_platforms propagate and
// the overlay dismisses.
useEcho(`post.${post.value.id}`, '.PostPlatformStatusUpdated', () => {
    router.reload({ only: ['post'] });
});


// Echo: listen for real-time comments
useEcho(`post.${post.value.id}`, '.PostCommentCreated', (e: any) => {
    if (e.mentioned_users) {
        editorSidebarRef.value?.registerMentionedUsers(e.mentioned_users);
    }
    editorSidebarRef.value?.addCommentFromBroadcast(e.comment);
});
</script>

<template>
    <Head :title="$t('posts.edit.title')" />

    <AppLayout :full-width="true" :breadcrumbs="breadcrumbs">
        <template #header-actions>
            <span v-if="isSaving" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <IconLoader2 class="h-3.5 w-3.5 animate-spin" />
                {{ $t('posts.edit.saving') }}
            </span>
            <span v-else-if="showSaved" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <IconCircleCheck class="h-3.5 w-3.5 text-green-500" />
                {{ $t('posts.edit.saved') }}
            </span>
            <span v-else-if="isPublished" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <IconCircleCheck class="h-3.5 w-3.5 text-green-500" />
                {{ $t('posts.edit.status.published') }}
            </span>
            <span v-else class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <span class="h-2 w-2 rounded-full bg-muted-foreground/50" />
                {{ $t('posts.edit.draft') }}
            </span>

            <template v-if="!isReadOnly">
                <span class="h-4 w-px bg-border" />

                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                class="text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                :disabled="isSaving || isSubmitting"
                                @click="deletePost"
                            >
                                <IconTrash class="h-4 w-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{ $t('posts.edit.delete') }}</TooltipContent>
                    </Tooltip>
                </TooltipProvider>

                <PickTimePopover
                    v-model="scheduledDateTime"
                    :disabled="isPostActionDisabled"
                    @confirm="hasPickedTime = true"
                >
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        :disabled="isPostActionDisabled"
                        :title="postActionTooltip"
                    >
                        <IconCalendar class="h-4 w-4" />
                        {{ pickTimeLabel }}
                    </Button>
                </PickTimePopover>

                <Button
                    type="button"
                    size="sm"
                    :disabled="isPostActionDisabled"
                    :title="postActionTooltip"
                    @click="submit(hasPickedTime ? 'scheduled' : 'publishing')"
                >
                    {{ hasPickedTime ? $t('posts.edit.schedule') : $t('posts.edit.post_now') }}
                </Button>
            </template>
        </template>

        <div class="flex flex-col flex-1 min-h-0">
            <div class="relative flex-1 overflow-hidden">
                <div
                    v-if="isPublishing"
                    class="absolute inset-0 z-40 flex flex-col items-center justify-center gap-3 bg-background/80 backdrop-blur-sm"
                >
                    <IconLoader2 class="h-8 w-8 animate-spin text-primary" />
                    <div class="text-center">
                        <p class="text-sm font-medium">{{ $t('posts.edit.publishing_overlay_title') }}</p>
                        <p class="text-xs text-muted-foreground">{{ $t('posts.edit.publishing_overlay_subtitle') }}</p>
                    </div>
                </div>
                <div class="h-full flex">
                    <div class="w-full lg:w-2/3 lg:border-r overflow-y-auto">
                        <PostEditorComposer
                            v-model:content="content"
                            v-model:media="media"
                            :signatures="signatures"
                            :platform-limits="platformLimits"
                            :media-issues="mediaIssues"
                            @open-ai-generate="isAiGenerateOpen = true"
                            @open-ai-review="isAiReviewOpen = true"
                        />
                    </div>

                    <div class="hidden lg:block lg:w-1/3 overflow-hidden">
                        <PostEditorSidebar
                            ref="editorSidebarRef"
                            v-model:active-tab="activeTab"
                            :post="post"
                            :workspace-id="workspace.id"
                            :content="content"
                            :media="media"
                            :selected-platform-ids="selectedPlatformIds"
                            :platform-meta="platformMeta"
                            :platform-content-types="platformContentTypes"
                            :platform-issues="platformIssues"
                            :platform-configs="platformConfigs"
                            :labels="labels"
                            :selected-label-ids="selectedLabelIds"
                            :tiktok-creator-infos="tiktokCreatorInfos"
                            :is-read-only="isReadOnly"
                            :auth-user-id="authUserId"
                            :initial-highlight-comment-id="initialHighlightCommentId"
                            @toggle-platform="togglePlatform"
                            @toggle-label="toggleLabel"
                            @update:platform-meta="updatePlatformMeta"
                            @update:platform-content-type="updatePlatformContentType"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <ConfirmDeleteModal
        ref="deleteModal"
        :title="$t('posts.delete.title')"
        :description="$t('posts.delete.description')"
        :action="$t('posts.delete.confirm')"
        :cancel="$t('posts.delete.cancel')"
    />

    <AiGenerateDialog
        v-model:open="isAiGenerateOpen"
        :post-id="post.id"
        :current-content="content"
        @apply="onAiGenerateApply"
    />

    <AiReviewDialog
        v-model:open="isAiReviewOpen"
        :post-id="post.id"
        :content="content"
        @apply="onAiReviewApply"
    />
</template>
