<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import {
    IconCalendar,
    IconCircleCheck,
    IconHash,
    IconLibraryPhoto,
    IconLoader2,
    IconMessage2,
    IconMoodSmile,
    IconSparkles,
    IconTrash,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, onUnmounted, ref, watch } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import CommentsTab from '@/components/posts/editor/CommentsTab.vue';
import PreviewTab from '@/components/posts/editor/PreviewTab.vue';
import ScheduleTab from '@/components/posts/editor/ScheduleTab.vue';
import WritingAssistantTab from '@/components/posts/editor/WritingAssistantTab.vue';
import EmojiPicker from '@/components/posts/EmojiPicker.vue';
import HashtagsModal from '@/components/posts/HashtagsModal.vue';
import MediaPickerDialog from '@/components/posts/MediaPickerDialog.vue';
import PickTimePopover from '@/components/posts/PickTimePopover.vue';
import { Button } from '@/components/ui/button';
import { Popover, PopoverAnchor, PopoverContent } from '@/components/ui/popover';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { readFileMetadata } from '@/composables/useMedia';
import { getMediaRulesForContentType } from '@/composables/useMediaRules';
import dayjs from '@/dayjs';
import debounce from '@/debounce';
import AppLayout from '@/layouts/AppLayout.vue';
import { store as storeAsset } from '@/routes/app/assets';
import { destroy as destroyPost, update as updatePost } from '@/routes/app/posts';

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
    hashtags: { id: string; name: string; hashtags: string }[];
    authUserId: string;
}>();

const post = computed(() => props.post);
const isReadOnly = computed(() => ['publishing', 'published', 'partially_published'].includes(post.value.status));
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
    if (total > rules.maxFiles) return trans('posts.edit.compliance.too_many_files', { max: rules.maxFiles });
    if (rules.minFiles && total < rules.minFiles) return trans('posts.edit.compliance.too_few_files', { min: rules.minFiles });

    for (const m of mediaItems) {
        const isVideo = m.type === 'video' || m.mime_type?.startsWith('video/');
        const size = m.size ?? 0;
        const duration = m.meta?.duration ?? 0;
        const width = m.meta?.width ?? 0;
        const height = m.meta?.height ?? 0;

        if (isVideo) {
            if (rules.maxVideoBytes && size > 0 && size > rules.maxVideoBytes) return trans('posts.edit.compliance.video_too_large');
            if (rules.maxVideoDurationSec && duration > 0 && duration > rules.maxVideoDurationSec) {
                return trans('posts.edit.compliance.video_too_long', { seconds: rules.maxVideoDurationSec });
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

const isPostActionDisabled = computed(
    () => isSubmitting.value || selectedPlatformIds.value.length === 0 || !canSchedule.value,
);
const queryParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
const initialTabFromQuery = (() => {
    const tab = queryParams?.get('tab');
    return ['preview', 'schedule', 'comments', 'assistant'].includes(tab ?? '') ? (tab as string) : 'schedule';
})();
const initialHighlightCommentId = queryParams?.get('comment') ?? null;
const activeTab = ref(initialTabFromQuery);
const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);
const hashtagsModal = ref<InstanceType<typeof HashtagsModal> | null>(null);
const mediaPickerDialog = ref<InstanceType<typeof MediaPickerDialog> | null>(null);
const commentsTabRef = ref<InstanceType<typeof CommentsTab> | null>(null);
const emojiOpen = ref(false);

const isDragging = ref(false);
const uploading = ref(false);

// Toggle platform
const togglePlatform = (platformId: string) => {
    if (isReadOnly.value) return;
    const index = selectedPlatformIds.value.indexOf(platformId);
    if (index === -1) {
        selectedPlatformIds.value.push(platformId);
    } else {
        selectedPlatformIds.value.splice(index, 1);
    }
};

const previewablePlatforms = computed(() => {
    const selected = post.value.post_platforms.filter((pp) => selectedPlatformIds.value.includes(pp.id));
    return selected.length > 0 ? selected : post.value.post_platforms.slice(0, 1);
});

// Media upload
const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const handleDrop = (event: DragEvent) => {
    isDragging.value = false;
    if (event.dataTransfer?.files) {
        uploadFiles(Array.from(event.dataTransfer.files));
    }
};

const uploadFiles = async (files: File[]) => {
    uploading.value = true;

    for (const file of files) {
        const clientMeta = await readFileMetadata(file);

        const formData = new FormData();
        formData.append('media', file);
        if (clientMeta.width) formData.append('meta[width]', String(clientMeta.width));
        if (clientMeta.height) formData.append('meta[height]', String(clientMeta.height));
        if (clientMeta.duration) formData.append('meta[duration]', String(clientMeta.duration));

        try {
            const response = await fetch(storeAsset.url(), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            if (!response.ok) continue;

            const data = await response.json();
            media.value = [
                ...media.value,
                {
                    id: data.id,
                    path: data.path,
                    url: data.url,
                    type: data.type,
                    mime_type: data.mime_type,
                    original_filename: data.original_filename,
                    size: data.size,
                    meta: data.meta,
                },
            ];
        } catch {
            // ignore
        }
    }

    uploading.value = false;
};

const removeMedia = (mediaId: string) => {
    media.value = media.value.filter((m) => m.id !== mediaId);
};

const addMediaFromGallery = (picked: MediaItem[]) => {
    const existingIds = new Set(media.value.map((m) => m.id));
    const additions = picked.filter((m) => !existingIds.has(m.id));
    if (additions.length === 0) return;
    media.value = [...media.value, ...additions];
};

const addedTextFromMessageIds = ref<Set<string>>(new Set());

const addMediaFromAssistant = (payload: {
    messageId: string;
    messageContent: string;
    media: { id: string; path: string; url: string; type: string; mime_type: string };
}) => {
    media.value = [...media.value, payload.media];

    const text = payload.messageContent.trim();
    if (text === '' || addedTextFromMessageIds.value.has(payload.messageId)) {
        return;
    }

    content.value = content.value.trim() === '' ? text : `${content.value}\n\n${text}`;
    addedTextFromMessageIds.value.add(payload.messageId);
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
    if (!isReadOnly.value && !uploading.value && !isSubmitting.value) {
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

const appendHashtags = (hashtag: { id: string; name: string; hashtags: string }) => {
    if (isReadOnly.value) return;
    const separator = content.value.trim() ? '\n\n' : '';
    content.value += separator + hashtag.hashtags;
};

const appendEmoji = (emoji: string) => {
    if (isReadOnly.value) return;
    content.value += emoji;
    emojiOpen.value = false;
};

const focusAssistant = () => {
    activeTab.value = 'assistant';
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
        commentsTabRef.value?.registerMentionedUsers(e.mentioned_users);
    }
    commentsTabRef.value?.addCommentFromBroadcast(e.comment);
});
</script>

<template>
    <Head :title="$t('posts.edit.title')" />

    <AppLayout :full-width="true">
        <div class="flex flex-col h-screen">
            <!-- Slim status bar -->
            <div class="flex items-center justify-between gap-4 border-b bg-background px-4 py-2">
                <div class="flex min-w-0 items-center gap-2">
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
                </div>

                <div v-if="!isReadOnly" class="flex shrink-0 items-center gap-2">
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

                    <span class="h-4 w-px bg-border" />

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
                </div>
            </div>

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
                    <!-- Composition column -->
                    <div class="w-full lg:w-2/3 lg:border-r overflow-y-auto">
                        <div class="max-w-2xl mx-auto py-8 px-6">
                            <div
                                class="rounded-lg border bg-card shadow-sm transition-shadow focus-within:shadow-md"
                                :class="isDragging ? 'border-primary ring-2 ring-primary/20' : ''"
                                @dragover.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="handleDrop"
                            >
                                <!-- Card header -->
                                <div class="flex items-start gap-3 border-b px-5 py-4">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <IconMessage2 class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <h2 class="text-sm font-semibold text-foreground">{{ $t('posts.edit.compose_title') }}</h2>
                                        <p class="text-xs text-muted-foreground">{{ $t('posts.edit.compose_subtitle') }}</p>
                                    </div>
                                </div>

                                <!-- Textarea + counter -->
                                <div class="relative">
                                    <Textarea
                                        v-model="content"
                                        :placeholder="$t('posts.edit.caption_placeholder')"
                                        :disabled="isReadOnly"
                                        class="min-h-[240px] resize-none rounded-none border-0 bg-transparent px-5 py-4 text-sm shadow-none focus-visible:ring-0 focus-visible:ring-offset-0"
                                    />
                                    <span class="pointer-events-none absolute bottom-2 right-3 text-xs text-muted-foreground/70">
                                        {{ content.length }}
                                    </span>
                                </div>

                                <!-- Inline toolbar -->
                                <div v-if="!isReadOnly" class="flex items-center gap-1 border-t px-3 py-2">
                                    <Popover v-model:open="emojiOpen">
                                        <PopoverAnchor as-child>
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon-sm"
                                                            class="h-8 w-8 text-muted-foreground hover:text-foreground"
                                                            @click="emojiOpen = !emojiOpen"
                                                        >
                                                            <IconMoodSmile class="h-4 w-4" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>{{ $t('posts.edit.emoji_picker.search') }}</TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </PopoverAnchor>
                                        <PopoverContent class="w-auto p-0" align="start">
                                            <EmojiPicker @select="appendEmoji" />
                                        </PopoverContent>
                                    </Popover>

                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    class="h-8 w-8 text-muted-foreground hover:text-foreground"
                                                    @click="hashtagsModal?.open()"
                                                >
                                                    <IconHash class="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>{{ $t('posts.edit.hashtags') }}</TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>

                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    class="h-8 w-8 text-muted-foreground hover:text-foreground"
                                                    @click="focusAssistant"
                                                >
                                                    <IconSparkles class="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>{{ $t('posts.edit.tabs.writing_assistant') }}</TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>

                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    class="h-8 w-8 text-muted-foreground hover:text-foreground"
                                                    @click="mediaPickerDialog?.open()"
                                                >
                                                    <IconLibraryPhoto class="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>{{ $t('posts.edit.add_media') }}</TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>

                                    <div class="flex-1" />

                                    <span v-if="uploading" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <IconLoader2 class="h-3.5 w-3.5 animate-spin" />
                                    </span>
                                </div>

                                <!-- Media grid -->
                                <div v-if="media.length > 0" class="border-t px-5 py-4">
                                    <div class="grid grid-cols-4 gap-2">
                                        <div
                                            v-for="item in media"
                                            :key="item.id"
                                            class="group relative aspect-square overflow-hidden rounded-lg border bg-muted"
                                        >
                                            <video
                                                v-if="item.type === 'video' || item.mime_type?.startsWith('video/')"
                                                :src="item.url"
                                                class="h-full w-full object-cover"
                                                muted
                                            />
                                            <img
                                                v-else
                                                :src="item.url"
                                                :alt="item.original_filename"
                                                class="h-full w-full object-cover"
                                                loading="lazy"
                                            />
                                            <button
                                                v-if="!isReadOnly"
                                                type="button"
                                                class="absolute right-1 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-black/60 text-white opacity-0 transition-opacity hover:bg-black/80 group-hover:opacity-100"
                                                @click="removeMedia(item.id)"
                                            >
                                                <IconTrash class="h-3 w-3" />
                                            </button>
                                        </div>
                                        <button
                                            v-if="!isReadOnly"
                                            type="button"
                                            class="flex aspect-square items-center justify-center rounded-lg border-2 border-dashed border-border text-muted-foreground transition-colors hover:border-primary/50 hover:text-primary"
                                            @click="mediaPickerDialog?.open()"
                                        >
                                            <IconLibraryPhoto class="h-6 w-6" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Drag overlay hint -->
                                <div v-if="isDragging && !isReadOnly" class="border-t bg-primary/5 px-5 py-6 text-center text-sm text-primary">
                                    {{ $t('posts.edit.drag_drop') }}
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Right sidebar -->
                    <div class="hidden lg:block lg:w-1/3 overflow-hidden">
                        <Tabs v-model="activeTab" class="h-full flex flex-col">
                            <TabsList class="mx-4 mt-4 w-auto shrink-0">
                                <TabsTrigger value="preview">{{ $t('posts.edit.tabs.preview') }}</TabsTrigger>
                                <TabsTrigger value="schedule">{{ $t('posts.edit.tabs.schedule') }}</TabsTrigger>
                                <TabsTrigger value="comments">{{ $t('posts.edit.tabs.comments') }}</TabsTrigger>
                                <TabsTrigger value="assistant">{{ $t('posts.edit.tabs.writing_assistant') }}</TabsTrigger>
                            </TabsList>

                            <TabsContent value="preview" class="flex-1 overflow-y-auto">
                                <PreviewTab
                                    :platforms="previewablePlatforms"
                                    :content="content"
                                    :media="media"
                                    :platform-content-types="platformContentTypes"
                                    :platform-meta="platformMeta"
                                    @update:platform-content-type="updatePlatformContentType"
                                />
                            </TabsContent>

                            <TabsContent value="schedule" class="flex-1 overflow-y-auto p-4">
                                <ScheduleTab
                                    :post-platforms="post.post_platforms"
                                    :selected-platform-ids="selectedPlatformIds"
                                    :labels="labels"
                                    :selected-label-ids="selectedLabelIds"
                                    :is-read-only="isReadOnly"
                                    :platform-configs="platformConfigs"
                                    :platform-meta="platformMeta"
                                    :platform-content-types="platformContentTypes"
                                    :platform-issues="platformIssues"
                                    :tiktok-creator-infos="tiktokCreatorInfos"
                                    :media="media"
                                    @toggle-platform="togglePlatform"
                                    @toggle-label="toggleLabel"
                                    @update:platformMeta="updatePlatformMeta"
                                    @update:platformContentType="updatePlatformContentType"
                                />
                            </TabsContent>

                            <TabsContent value="comments" class="flex-1 overflow-hidden">
                                <CommentsTab
                                    ref="commentsTabRef"
                                    :post-id="post.id"
                                    :current-user-id="authUserId"
                                    :highlight-comment-id="initialHighlightCommentId"
                                />
                            </TabsContent>

                            <TabsContent value="assistant" class="flex-1 overflow-hidden">
                                <WritingAssistantTab :post-id="post.id" :workspace-id="workspace.id" @add-media="addMediaFromAssistant" />
                            </TabsContent>
                        </Tabs>
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
    <HashtagsModal ref="hashtagsModal" :hashtags="hashtags" @select="appendHashtags" />
    <MediaPickerDialog ref="mediaPickerDialog" @select="addMediaFromGallery" />
</template>
