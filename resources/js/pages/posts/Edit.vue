<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
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
} from '@tabler/icons-vue';
import {
    IconClock,
    IconAlertCircle,
    IconTrash,
    IconSend,
    IconLink,
    IconDots,
    IconPlus,
    IconCircleCheck,
    IconExternalLink,
    IconLoader2,
} from '@tabler/icons-vue';
import { computed, onUnmounted, ref, watch, type Component } from 'vue';

import debounce from '@/debounce';

import { Badge } from '@/components/ui/badge';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import DatePicker from '@/components/DatePicker.vue';
import PhoneMockup from '@/components/PhoneMockup.vue';
import PostForm from '@/components/posts/PostForm.vue';
import { PlatformPreview } from '@/components/posts/previews';
import { useMediaManager, type MediaItem } from '@/composables/useMediaManager';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import dayjs from '@/dayjs';
import AppLayout from '@/layouts/AppLayout.vue';
import { destroy as destroyPost, index as postsIndex, update as updatePost } from '@/routes/posts';
import { type BreadcrumbItemType } from '@/types';

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    avatar_url: string | null;
}


interface PostPlatform {
    id: string;
    social_account_id: string;
    enabled: boolean;
    platform: string;
    content: string;
    content_type: string | null;
    status: string;
    platform_url: string | null;
    error_message: string | null;
    published_at: string | null;
    social_account: SocialAccount;
    media: MediaItem[];
    meta?: Record<string, any>;
}

interface PinterestBoard {
    id: string;
    name: string;
}

interface ContentTypeOption {
    value: string;
    label: string;
    description: string;
}

interface Post {
    id: string;
    status: string;
    synced: boolean;
    scheduled_at: string | null;
    published_at: string | null;
    post_platforms: PostPlatform[];
}

interface PlatformConfig {
    maxContentLength: number;
    maxImages: number;
    allowedMediaTypes: string[];
    supportsTextOnly: boolean;
}

interface Workspace {
    id: string;
    name: string;
    timezone: string;
}

interface Props {
    workspace: Workspace;
    post: Post;
    socialAccounts: SocialAccount[];
    platformConfigs: Record<string, PlatformConfig>;
    pinterestBoards: PinterestBoard[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Posts', href: postsIndex.url() },
    { title: 'Edit Post', href: '#' },
];

// Reactive state for real-time updates
const post = ref(props.post);

// Listen for status updates via WebSocket
useEcho(
    `posts.${props.post.id}`,
    'PostPlatformStatusUpdated',
    (e: { post_platform: { id: string; status: string; platform_url: string | null; error_message: string | null; published_at: string | null }; post: { id: string; status: string; published_at: string | null } }) => {
        // Update post platform status
        const platformIndex = post.value.post_platforms.findIndex(pp => pp.id === e.post_platform.id);
        if (platformIndex !== -1) {
            post.value.post_platforms[platformIndex].status = e.post_platform.status;
            post.value.post_platforms[platformIndex].platform_url = e.post_platform.platform_url;
            post.value.post_platforms[platformIndex].error_message = e.post_platform.error_message;
            post.value.post_platforms[platformIndex].published_at = e.post_platform.published_at;
        }

        // Update post status
        post.value.status = e.post.status;
        post.value.published_at = e.post.published_at;
    },
);

// Check if post is in read-only mode (published, failed, publishing, partially_published)
const isReadOnly = computed(() => ['published', 'failed', 'publishing', 'partially_published'].includes(post.value.status));

// State
const selectedPlatformIds = ref<string[]>(
    post.value.post_platforms.filter(pp => pp.enabled).map(pp => pp.id)
);
const synced = ref(post.value.synced);
const enabledPlatforms = post.value.post_platforms.filter(pp => pp.enabled);
const globalContent = ref(
    enabledPlatforms[0]?.content || post.value.post_platforms[0]?.content || ''
);
const platformContents = ref<Record<string, string>>(
    Object.fromEntries(post.value.post_platforms.map(pp => [pp.id, pp.content]))
);
const platformContentTypes = ref<Record<string, string>>(
    Object.fromEntries(post.value.post_platforms.map(pp => [pp.id, pp.content_type || getDefaultContentType(pp.platform)]))
);
const platformMeta = ref<Record<string, Record<string, any>>>(
    Object.fromEntries(post.value.post_platforms.map(pp => [pp.id, pp.meta || {}]))
);

// Media manager composable
const postPlatformsRef = computed(() => post.value.post_platforms);
const {
    platformMedia,
    isUploading,
    upload: uploadMedia,
    remove: removeMedia,
    reorder: reorderMedia,
} = useMediaManager({
    synced,
    selectedPlatformIds,
    platformContentTypes,
    postPlatforms: postPlatformsRef,
});

// Check if any platform is currently uploading
const isAnyUploading = computed(() => Object.values(isUploading.value).some(v => v));

const isSubmitting = ref(false);
const isSaving = ref(false);
const showSaved = ref(false);
const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);
const showEnableSyncDialog = ref(false);
const showDisableSyncDialog = ref(false);
const showPlatformsDialog = ref(false);

// Active tab - first selected platform
const activeTabId = ref<string>(selectedPlatformIds.value[0] || post.value.post_platforms[0]?.id || '');

// Watch selected platforms and update active tab if needed
watch(selectedPlatformIds, (newIds) => {
    if (!newIds.includes(activeTabId.value) && newIds.length > 0) {
        activeTabId.value = newIds[0];
    }
}, { deep: true });

// Convert UTC to workspace timezone for display
const getLocalSchedule = () => {
    if (!post.value.scheduled_at) {
        return '';
    }
    const local = dayjs.utc(post.value.scheduled_at).tz(props.workspace.timezone);
    return local.format('YYYY-MM-DDTHH:mm:00');
};

const scheduledDateTime = ref(getLocalSchedule());

const timezoneAbbr = computed(() => {
    return dayjs().tz(props.workspace.timezone).format('z');
});

// Helpers
const getPlatformLogo = (platform: string): string => {
    const logos: Record<string, string> = {
        'linkedin': '/images/accounts/linkedin.png',
        'linkedin-page': '/images/accounts/linkedin.png',
        'x': '/images/accounts/x.png',
        'tiktok': '/images/accounts/tiktok.png',
        'youtube': '/images/accounts/youtube.png',
        'facebook': '/images/accounts/facebook.png',
        'instagram': '/images/accounts/instagram.png',
        'threads': '/images/accounts/threads.png',
        'pinterest': '/images/accounts/pinterest.png',
        'bluesky': '/images/accounts/bluesky.png',
        'mastodon': '/images/accounts/mastodon.png',
    };
    return logos[platform] || '/images/accounts/default.png';
};

const getPlatformLabel = (platform: string): string => {
    const labels: Record<string, string> = {
        'linkedin': 'LinkedIn',
        'linkedin-page': 'LinkedIn Page',
        'x': 'X',
        'tiktok': 'TikTok',
        'youtube': 'YouTube Shorts',
        'facebook': 'Facebook Page',
        'instagram': 'Instagram',
        'threads': 'Threads',
        'pinterest': 'Pinterest',
        'bluesky': 'Bluesky',
        'mastodon': 'Mastodon',
    };
    return labels[platform] || platform;
};

const getPlatformIcon = (platform: string): Component => {
    const icons: Record<string, Component> = {
        'linkedin': IconBrandLinkedin,
        'linkedin-page': IconBrandLinkedin,
        'x': IconBrandX,
        'tiktok': IconBrandTiktok,
        'youtube': IconBrandYoutube,
        'facebook': IconBrandFacebook,
        'instagram': IconBrandInstagram,
        'threads': IconBrandThreads,
        'pinterest': IconBrandPinterest,
        'bluesky': IconBrandBluesky,
        'mastodon': IconBrandMastodon,
    };
    return icons[platform];
};

const getStatusConfig = (status: string) => {
    const configs: Record<string, { label: string; color: string; icon: any }> = {
        'draft': { label: 'Draft', color: 'bg-gray-100 text-gray-800', icon: IconClock },
        'scheduled': { label: 'Scheduled', color: 'bg-blue-100 text-blue-800', icon: IconClock },
        'publishing': { label: 'Publishing', color: 'bg-yellow-100 text-yellow-800', icon: IconLoader2 },
        'published': { label: 'Published', color: 'bg-green-100 text-green-800', icon: IconCircleCheck },
        'partially_published': { label: 'Partially Published', color: 'bg-orange-100 text-orange-800', icon: IconAlertCircle },
        'failed': { label: 'Failed', color: 'bg-red-100 text-red-800', icon: IconAlertCircle },
    };
    return configs[status] || configs['draft'];
};

const formatDateTime = (date: string | null): string => {
    if (!date) return '-';
    return dayjs.utc(date).tz(props.workspace.timezone).format('MMM D, YYYY [at] h:mm A');
};

// Content type options per platform
const contentTypeOptions: Record<string, ContentTypeOption[]> = {
    'instagram': [
        { value: 'instagram_feed', label: 'Feed Post', description: 'Appears in your feed and profile' },
        { value: 'instagram_reel', label: 'Reel', description: 'Short video up to 90 seconds' },
        { value: 'instagram_story', label: 'Story', description: 'Disappears after 24 hours' },
    ],
    'linkedin': [
        { value: 'linkedin_post', label: 'Post', description: 'Standard post with text and media' },
        { value: 'linkedin_carousel', label: 'Carousel', description: 'Swipeable images' },
    ],
    'linkedin-page': [
        { value: 'linkedin_page_post', label: 'Post', description: 'Standard post with text and media' },
        { value: 'linkedin_page_carousel', label: 'Carousel', description: 'Swipeable images' },
    ],
    'facebook': [
        { value: 'facebook_post', label: 'Post', description: 'Standard post on your page' },
        { value: 'facebook_reel', label: 'Reel', description: 'Short video up to 90 seconds' },
        { value: 'facebook_story', label: 'Story', description: 'Disappears after 24 hours' },
    ],
    'tiktok': [
        { value: 'tiktok_video', label: 'Video', description: 'Short-form video content' },
    ],
    'youtube': [
        { value: 'youtube_short', label: 'Short', description: 'Vertical video up to 60 seconds' },
    ],
    'x': [
        { value: 'x_post', label: 'Post', description: 'Tweet with text and media' },
    ],
    'threads': [
        { value: 'threads_post', label: 'Post', description: 'Text post with optional media' },
    ],
    'pinterest': [
        { value: 'pinterest_pin', label: 'Pin', description: 'Image pin with link' },
        { value: 'pinterest_video_pin', label: 'Video Pin', description: 'Video content' },
        { value: 'pinterest_carousel', label: 'Carousel', description: '2-5 images' },
    ],
    'bluesky': [
        { value: 'bluesky_post', label: 'Post', description: 'Text post with optional images' },
    ],
};

function getDefaultContentType(platform: string): string {
    const defaults: Record<string, string> = {
        'instagram': 'instagram_feed',
        'linkedin': 'linkedin_post',
        'linkedin-page': 'linkedin_page_post',
        'facebook': 'facebook_post',
        'tiktok': 'tiktok_video',
        'youtube': 'youtube_short',
        'x': 'x_post',
        'threads': 'threads_post',
        'pinterest': 'pinterest_pin',
        'bluesky': 'bluesky_post',
    };
    return defaults[platform] || '';
}

function getContentTypeOptions(platform: string): ContentTypeOption[] {
    return contentTypeOptions[platform] || [];
}

function getPlatformData(platform: string): Record<string, any> {
    if (platform === 'pinterest') {
        return { boards: props.pinterestBoards };
    }
    return {};
}

const getConfig = (postPlatform: PostPlatform): PlatformConfig => {
    return props.platformConfigs[postPlatform.social_account_id] || {
        maxContentLength: 5000,
        maxImages: 10,
        allowedMediaTypes: ['image', 'video'],
        supportsTextOnly: true,
    };
};

// Get content for a platform
const getContent = (platformId: string): string => {
    if (synced.value) {
        return globalContent.value;
    }
    return platformContents.value[platformId] || '';
};

// === Autosave Logic ===
const getSubmitData = () => {
    const platforms = post.value.post_platforms
        .filter(pp => selectedPlatformIds.value.includes(pp.id))
        .map(pp => ({
            id: pp.id,
            content: synced.value ? globalContent.value : platformContents.value[pp.id],
            content_type: platformContentTypes.value[pp.id],
            meta: platformMeta.value[pp.id] || {},
        }));

    return { platforms, scheduled_at: scheduledDateTime.value || null };
};

const save = () => {
    if (isSubmitting.value || isReadOnly.value || isSaving.value) return;

    const { platforms, scheduled_at } = getSubmitData();

    isSaving.value = true;
    showSaved.value = false;

    router.put(updatePost.url(post.value.id), {
        status: post.value.status,
        synced: synced.value,
        scheduled_at,
        platforms,
    }, {
        preserveScroll: true,
        onFinish: () => {
            isSaving.value = false;
            showSaved.value = true;
            setTimeout(() => {
                showSaved.value = false;
            }, 2000);
        },
    });
};

const debouncedSave = debounce(() => {
    if (!isReadOnly.value && !isAnyUploading.value && !isSubmitting.value) {
        save();
    }
}, 1500);

const triggerAutosave = () => {
    if (!isReadOnly.value) {
        showSaved.value = false;
        debouncedSave();
    }
};

// Watch other form data for autosave
watch([selectedPlatformIds, synced, scheduledDateTime], triggerAutosave, { deep: true });

// Cleanup on unmount
onUnmounted(() => {
    debouncedSave.cancel();
});

// === Content Setters ===
// Set content for a platform
const setContent = (platformId: string, value: string) => {
    if (synced.value) {
        globalContent.value = value;
        // Also update all platform contents when synced
        for (const id of selectedPlatformIds.value) {
            platformContents.value[id] = value;
        }
    } else {
        platformContents.value[platformId] = value;
    }
    triggerAutosave();
};

// Active platform
const activePlatform = computed(() => {
    return post.value.post_platforms.find(pp => pp.id === activeTabId.value);
});

// Current platform media (computed for proper reactivity)
const currentPlatformMedia = computed(() => {
    if (!activePlatform.value) return [];
    return platformMedia.value[activePlatform.value.id] || [];
});

// Current platform content type
const currentContentType = computed(() => {
    if (!activePlatform.value) return '';
    return platformContentTypes.value[activePlatform.value.id] || '';
});


// Set content type for a platform
const setContentType = (platformId: string, value: string) => {
    platformContentTypes.value[platformId] = value;
    triggerAutosave();
};

// Set meta for a platform
const setMeta = (platformId: string, value: Record<string, any>) => {
    platformMeta.value[platformId] = value;
    triggerAutosave();
};

// Selected platforms (for tabs)
const selectedPlatforms = computed(() => {
    return post.value.post_platforms.filter(pp => selectedPlatformIds.value.includes(pp.id));
});

// Other selected platforms (for sync label)
const otherSelectedPlatforms = computed(() => {
    return selectedPlatforms.value.filter(pp => pp.id !== activeTabId.value);
});

// First selected platform (for sync confirmation)
const firstSelectedPlatform = computed(() => {
    return selectedPlatforms.value[0];
});

// Handle sync toggle with confirmation
const handleSyncClick = (event: Event) => {
    event.preventDefault();
    event.stopPropagation();

    if (!synced.value) {
        // User wants to enable sync - show confirmation dialog
        showEnableSyncDialog.value = true;
    } else {
        // User wants to disable sync - show confirmation dialog
        showDisableSyncDialog.value = true;
    }
};

// Confirm sync enable
const confirmEnableSync = () => {
    if (firstSelectedPlatform.value) {
        // Copy content from first platform to global and all platforms
        const firstContent = platformContents.value[firstSelectedPlatform.value.id] || '';
        globalContent.value = firstContent;
        for (const id of selectedPlatformIds.value) {
            platformContents.value[id] = firstContent;
        }
    }
    synced.value = true;
    showEnableSyncDialog.value = false;
};

// Confirm sync disable
const confirmDisableSync = () => {
    synced.value = false;
    showDisableSyncDialog.value = false;
};

// Computed
const contentValidation = computed(() => {
    const results: Record<string, { valid: boolean; message: string; charCount: number; maxLength: number }> = {};

    for (const pp of post.value.post_platforms) {
        if (!selectedPlatformIds.value.includes(pp.id)) continue;

        const config = getConfig(pp);
        const content = getContent(pp.id);

        const charCount = content.length;
        const hasContent = charCount > 0;
        const withinLimit = charCount <= config.maxContentLength;
        const media = platformMedia.value[pp.id] || [];
        const hasMedia = media.length > 0;
        const meta = platformMeta.value[pp.id] || {};

        // Check media type compatibility
        const imageCount = media.filter(m => m.type === 'image').length;
        const videoCount = media.filter(m => m.type === 'video').length;
        const hasUnsupportedImages = config.maxImages === 0 && imageCount > 0;
        const hasTooManyImages = imageCount > config.maxImages && config.maxImages > 0;
        const hasUnsupportedVideos = !config.allowedMediaTypes.includes('video') && videoCount > 0;

        // Pinterest requires a board
        if (pp.platform === 'pinterest' && !meta.board_id) {
            results[pp.id] = { valid: false, message: 'Select a board', charCount, maxLength: config.maxContentLength };
        } else if (hasUnsupportedImages) {
            results[pp.id] = { valid: false, message: 'Images not supported', charCount, maxLength: config.maxContentLength };
        } else if (hasUnsupportedVideos) {
            results[pp.id] = { valid: false, message: 'Videos not supported', charCount, maxLength: config.maxContentLength };
        } else if (hasTooManyImages) {
            results[pp.id] = { valid: false, message: `Max ${config.maxImages} images`, charCount, maxLength: config.maxContentLength };
        } else if (!config.supportsTextOnly && !hasMedia) {
            results[pp.id] = { valid: false, message: 'Requires media', charCount, maxLength: config.maxContentLength };
        } else if (!hasContent && !hasMedia) {
            results[pp.id] = { valid: false, message: 'No content', charCount, maxLength: config.maxContentLength };
        } else if (!withinLimit) {
            results[pp.id] = { valid: false, message: `${charCount - config.maxContentLength} exceeded`, charCount, maxLength: config.maxContentLength };
        } else {
            results[pp.id] = { valid: true, message: `${charCount}/${config.maxContentLength}`, charCount, maxLength: config.maxContentLength };
        }
    }

    return results;
});

const mediaValidation = computed(() => {
    const errors: string[] = [];

    for (const pp of post.value.post_platforms) {
        if (!selectedPlatformIds.value.includes(pp.id)) continue;

        const config = getConfig(pp);
        const media = platformMedia.value[pp.id] || [];
        const imageCount = media.filter(m => m.type === 'image').length;
        const videoCount = media.filter(m => m.type === 'video').length;

        if (config.maxImages === 0 && imageCount > 0) {
            errors.push(`${getPlatformLabel(pp.platform)} does not support images`);
        }

        if (imageCount > config.maxImages && config.maxImages > 0) {
            errors.push(`${getPlatformLabel(pp.platform)} supports up to ${config.maxImages} images`);
        }

        if (!config.allowedMediaTypes.includes('video') && videoCount > 0) {
            errors.push(`${getPlatformLabel(pp.platform)} does not support videos`);
        }
    }

    return [...new Set(errors)];
});

const canSubmit = computed(() => {
    if (selectedPlatformIds.value.length === 0) return false;
    if (!scheduledDateTime.value) return false;

    const selectedValidations = Object.entries(contentValidation.value)
        .filter(([id]) => selectedPlatformIds.value.includes(id));

    return selectedValidations.every(([, v]) => v.valid) && mediaValidation.value.length === 0;
});

// Methods
const togglePlatform = (platformId: string) => {
    const index = selectedPlatformIds.value.indexOf(platformId);
    if (index === -1) {
        selectedPlatformIds.value.push(platformId);
        // Switch to newly added platform
        activeTabId.value = platformId;
    } else {
        selectedPlatformIds.value.splice(index, 1);
    }
};

const submit = (status: string = 'scheduled') => {
    if (isSubmitting.value || isReadOnly.value) return;

    // Cancel any pending autosave
    debouncedSave.cancel();

    const { platforms, scheduled_at } = getSubmitData();

    isSubmitting.value = true;

    router.put(updatePost.url(post.value.id), {
        status,
        synced: synced.value,
        scheduled_at,
        platforms,
    }, {
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const deletePost = () => {
    if (isReadOnly.value) return;
    deleteModal.value?.open({
        url: destroyPost.url(post.value.id),
    });
};
</script>

<template>

    <Head :title="isReadOnly ? 'View Post' : 'Edit Post'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col h-screen">
            <!-- Top Bar with Tabs and Actions -->
            <div class="relative flex items-center justify-between border-b px-4 py-2 bg-background">
                <!-- Platform Tabs -->
                <div class="flex items-center gap-2">
                    <!-- Status Badge (when read-only) -->
                    <Badge v-if="isReadOnly" :class="getStatusConfig(post.status).color" class="mr-2">
                        <component :is="getStatusConfig(post.status).icon" class="mr-1 h-3 w-3"
                            :class="{ 'animate-spin': post.status === 'publishing' }" />
                        {{ getStatusConfig(post.status).label }}
                    </Badge>

                    <div v-if="selectedPlatforms.length > 0"
                        class="bg-muted text-muted-foreground inline-flex h-9 w-fit items-center justify-center rounded-lg p-[3px]">
                        <TooltipProvider>
                            <Tooltip v-for="pp in selectedPlatforms" :key="pp.id">
                                <TooltipTrigger asChild>
                                    <button type="button" @click="activeTabId = pp.id" :class="[
                                        'relative inline-flex h-[calc(100%-1px)] items-center justify-center gap-1.5 rounded-md border border-transparent px-2 py-1 text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1 focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring',
                                        activeTabId === pp.id
                                            ? 'bg-background text-foreground shadow-sm dark:border-input dark:bg-input/30'
                                            : 'text-foreground dark:text-muted-foreground'
                                    ]">
                                        <component :is="getPlatformIcon(pp.platform)"
                                            class="h-5 w-5 text-neutral-500 rounded-full" />
                                        <!-- Status indicator (read-only) or Validation indicator (edit mode) -->
                                        <span v-if="isReadOnly"
                                            class="absolute -top-0.5 -right-0.5 h-2 w-2 rounded-full border border-background"
                                            :class="{
                                                'bg-green-500': pp.status === 'published',
                                                'bg-red-500': pp.status === 'failed',
                                                'bg-yellow-500 animate-pulse': pp.status === 'publishing',
                                                'bg-gray-400': !['published', 'failed', 'publishing'].includes(pp.status)
                                            }" />
                                        <span v-else-if="contentValidation[pp.id]"
                                            class="absolute -top-0.5 -right-0.5 h-2 w-2 rounded-full border border-background"
                                            :class="contentValidation[pp.id].valid ? 'bg-green-500' : 'bg-red-500'" />
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{{ pp.social_account.display_name }}</p>
                                    <p v-if="isReadOnly" class="text-xs text-muted-foreground">
                                        {{ getStatusConfig(pp.status).label }}
                                    </p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>

                    <!-- Platforms Menu Button (only in edit mode) -->
                    <TooltipProvider v-if="!isReadOnly">
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <button type="button" @click="showPlatformsDialog = true"
                                    class="p-2 rounded-lg hover:bg-muted/50 transition-colors">
                                    <IconDots class="h-5 w-5 text-muted-foreground" />
                                </button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Manage platforms</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>

                <!-- Sync Toggle (centered, only in edit mode with multiple platforms) -->
                <div v-if="!isReadOnly && selectedPlatformIds.length > 1"
                    class="absolute left-1/2 -translate-x-1/2 flex items-center gap-2">
                    <div @click.capture="handleSyncClick">
                        <Switch id="sync-content" v-model="synced" />
                    </div>
                    <Label for="sync-content" class="text-sm cursor-pointer flex items-center gap-1.5">
                        <IconLink class="h-4 w-4" />
                        <span>Sync</span>
                        <span class="flex items-center gap-1">
                            <component v-for="op in otherSelectedPlatforms.slice(0, 3)" :key="op.id"
                                :is="getPlatformIcon(op.platform)" class="h-4 w-4 text-neutral-600" />
                            <span v-if="otherSelectedPlatforms.length > 3" class="text-muted-foreground">
                                +{{ otherSelectedPlatforms.length - 3 }}
                            </span>
                        </span>
                    </Label>
                </div>

                <!-- Schedule & Actions (only in edit mode) -->
                <div v-if="!isReadOnly" class="flex items-center gap-3">
                    <!-- DateTime Picker -->
                    <div class="flex items-center gap-2">
                        <DatePicker name="scheduled_datetime" v-model="scheduledDateTime" :show-time="true" />
                        <span class="text-xs text-muted-foreground">
                            {{ timezoneAbbr }}
                        </span>
                    </div>

                    <span class="text-muted-foreground">|</span>

                    <Button type="button" variant="outline" size="icon-sm" @click="deletePost"
                        :disabled="isSaving || isSubmitting"
                        class="text-muted-foreground hover:text-destructive">
                        <IconTrash class="h-4 w-4" />
                    </Button>

                    <Button type="button" variant="secondary" size="sm" :disabled="!canSubmit || isSubmitting || isSaving"
                        @click="submit('scheduled')">
                        <IconClock class="mr-2 h-4 w-4" />
                        Schedule
                    </Button>

                    <Button type="button" size="sm" :disabled="!canSubmit || isSubmitting || isSaving"
                        @click="submit('publishing')">
                        <IconSend class="mr-2 h-4 w-4" />
                        Publish
                    </Button>
                </div>

                <!-- Read-only info -->
                <div v-else class="flex items-center gap-3 text-sm text-muted-foreground">
                    <span v-if="post.scheduled_at">Scheduled: {{ formatDateTime(post.scheduled_at) }}</span>
                    <span v-if="post.published_at">Published: {{ formatDateTime(post.published_at) }}</span>
                </div>
            </div>

            <!-- Main Content Area - Split Layout -->
            <div class="flex-1 overflow-hidden">
                <div v-if="activePlatform && selectedPlatformIds.length > 0" class="h-full flex">
                    <!-- Left Side: Form -->
                    <div class="w-1/2 border-r overflow-y-auto relative">
                        <!-- Autosave indicator -->
                        <div v-if="!isReadOnly && (isSaving || showSaved)" class="absolute top-4 left-6 z-10">
                            <span v-if="isSaving" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <IconLoader2 class="h-3 w-3 animate-spin" />
                                Saving...
                            </span>
                            <span v-else-if="showSaved" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <IconCircleCheck class="h-3 w-3 text-green-500" />
                                Saved
                            </span>
                        </div>
                        <div class="max-w-lg mx-auto py-8 px-6">
                            <!-- Publication Result (read-only mode) -->
                            <div v-if="isReadOnly" class="mb-6 space-y-3">
                                <!-- Platform Status -->
                                <div class="flex items-center justify-between p-4 rounded-lg border">
                                    <div class="flex items-center gap-3">
                                        <component :is="getPlatformIcon(activePlatform.platform)"
                                            class="h-8 w-8 text-neutral-600" />
                                        <div>
                                            <p class="font-medium">{{ activePlatform.social_account.display_name }}</p>
                                            <p class="text-sm text-muted-foreground">{{
                                                getPlatformLabel(activePlatform.platform) }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Badge :class="getStatusConfig(activePlatform.status).color">
                                            <component :is="getStatusConfig(activePlatform.status).icon"
                                                class="mr-1 h-3 w-3"
                                                :class="{ 'animate-spin': activePlatform.status === 'publishing' }" />
                                            {{ getStatusConfig(activePlatform.status).label }}
                                        </Badge>
                                        <a v-if="activePlatform.platform_url" :href="activePlatform.platform_url"
                                            target="_blank"
                                            class="p-2 rounded-lg hover:bg-muted/50 transition-colors text-muted-foreground hover:text-foreground">
                                            <IconExternalLink class="h-4 w-4" />
                                        </a>
                                    </div>
                                </div>

                                <!-- Error Message -->
                                <Alert v-if="activePlatform.error_message" variant="destructive">
                                    <IconAlertCircle class="h-4 w-4" />
                                    <AlertDescription>
                                        {{ activePlatform.error_message }}
                                    </AlertDescription>
                                </Alert>

                                <!-- Published Date -->
                                <p v-if="activePlatform.published_at" class="text-sm text-muted-foreground text-center">
                                    Published {{ formatDateTime(activePlatform.published_at) }}
                                </p>

                                <!-- Read-only content display -->
                                <div class="mt-6 space-y-4">
                                    <!-- Media -->
                                    <div v-if="currentPlatformMedia.length > 0">
                                        <p class="text-sm font-medium mb-2">Media</p>
                                        <div class="grid grid-cols-3 gap-2">
                                            <div v-for="item in currentPlatformMedia" :key="item.id"
                                                class="aspect-square rounded-lg overflow-hidden bg-muted">
                                                <img v-if="item.type === 'image'" :src="item.url"
                                                    :alt="item.original_filename" class="w-full h-full object-cover" />
                                                <video v-else :src="item.url"
                                                    class="w-full h-full object-cover bg-black" muted />
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Content -->
                                    <div>
                                        <p class="text-sm font-medium mb-2">Caption</p>
                                        <p class="text-sm text-muted-foreground whitespace-pre-wrap">
                                            {{ getContent(activePlatform.id) || 'No caption' }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Edit Form -->
                            <PostForm v-else :platform="activePlatform.platform"
                                :content="getContent(activePlatform.id)" :media="currentPlatformMedia"
                                :content-type="currentContentType"
                                :content-type-options="getContentTypeOptions(activePlatform.platform)"
                                :meta="platformMeta[activePlatform.id]"
                                :platform-data="getPlatformData(activePlatform.platform)"
                                :char-count="contentValidation[activePlatform.id]?.charCount || 0"
                                :max-length="contentValidation[activePlatform.id]?.maxLength || 5000"
                                :is-valid="contentValidation[activePlatform.id]?.valid ?? false"
                                :validation-message="contentValidation[activePlatform.id]?.message || ''"
                                :is-uploading="isUploading[activePlatform.id]" :disabled="isReadOnly"
                                @update:content="setContent(activePlatform.id, $event)"
                                @update:content-type="setContentType(activePlatform.id, $event)"
                                @update:meta="setMeta(activePlatform.id, $event)"
                                @upload="uploadMedia($event, activePlatform.id)"
                                @remove-media="removeMedia(activePlatform.id, $event)"
                                @reorder-media="reorderMedia(activePlatform.id, $event)" />

                            <!-- Media Validation Errors -->
                            <Alert v-if="!isReadOnly && mediaValidation.length > 0" variant="destructive" class="mt-4">
                                <IconAlertCircle class="h-4 w-4" />
                                <AlertDescription>
                                    <ul class="list-disc list-inside">
                                        <li v-for="error in mediaValidation" :key="error">{{ error }}</li>
                                    </ul>
                                </AlertDescription>
                            </Alert>
                        </div>
                    </div>

                    <!-- Right Side: Phone Preview -->
                    <div class="w-1/2 bg-muted/30 overflow-y-auto">
                        <div class="flex items-center justify-center min-h-full py-8 px-4">
                            <PhoneMockup>
                                <PlatformPreview :key="activePlatform.id" :platform="activePlatform.platform"
                                    :social-account="activePlatform.social_account"
                                    :content="getContent(activePlatform.id)" :media="currentPlatformMedia"
                                    :content-type="currentContentType" />
                            </PhoneMockup>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="flex flex-col items-center justify-center h-full text-center p-8">
                    <div class="h-16 w-16 rounded-full bg-muted flex items-center justify-center mb-4">
                        <IconPlus class="h-8 w-8 text-muted-foreground" />
                    </div>
                    <h3 class="text-lg font-semibold mb-2">No platforms selected</h3>
                    <p class="text-muted-foreground mb-4">Select at least one platform to create your post</p>
                    <div class="flex flex-wrap justify-center gap-2">
                        <button v-for="pp in post.post_platforms" :key="pp.id" type="button"
                            @click="togglePlatform(pp.id)"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border hover:bg-muted transition-colors">
                            <component :is="getPlatformIcon(pp.platform)" class="h-5 w-5 text-neutral-600" />
                            <span class="text-sm">{{ pp.social_account.display_name }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <ConfirmDeleteModal ref="deleteModal" title="Delete Post"
        description="Are you sure you want to delete this post? This action cannot be undone." action="Delete"
        cancel="Cancel" />

    <!-- Enable Sync Confirmation Dialog -->
    <AlertDialog :open="showEnableSyncDialog" @update:open="showEnableSyncDialog = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>
                    Discard text and sync with {{ getPlatformLabel(firstSelectedPlatform?.platform || '') }}?
                </AlertDialogTitle>
                <AlertDialogDescription>
                    If you enable syncing, you'll lose all edits made specifically to other platforms.
                    <br /><br />
                    Are you sure you want to sync with the {{ getPlatformLabel(firstSelectedPlatform?.platform || '') }}
                    version?
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction @click="confirmEnableSync">
                    <IconLink class="mr-2 h-4 w-4" />
                    Sync with {{ getPlatformLabel(firstSelectedPlatform?.platform || '') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <!-- Disable Sync Confirmation Dialog -->
    <AlertDialog :open="showDisableSyncDialog" @update:open="showDisableSyncDialog = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>
                    Disable sync?
                </AlertDialogTitle>
                <AlertDialogDescription>
                    Each platform will keep its current content, but future edits will only apply to the platform you're
                    editing.
                    <br /><br />
                    You'll be able to customize the content for each platform individually.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction @click="confirmDisableSync">
                    Disable sync
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <!-- Platforms Selection Dialog -->
    <Dialog :open="showPlatformsDialog" @update:open="showPlatformsDialog = $event">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Select Platforms</DialogTitle>
                <DialogDescription>
                    Choose which platforms to publish this post to.
                </DialogDescription>
            </DialogHeader>
            <div class="grid grid-cols-2 gap-3 py-4">
                <div v-for="pp in post.post_platforms" :key="pp.id"
                    class="flex items-center justify-between p-3 rounded-lg border">
                    <div class="flex items-center gap-3">
                        <div class="relative shrink-0">
                            <img v-if="pp.social_account.avatar_url" :src="pp.social_account.avatar_url"
                                :alt="pp.social_account.display_name" class="h-10 w-10 rounded-full object-cover" />
                            <div v-else class="h-10 w-10 rounded-full bg-muted flex items-center justify-center">
                                <span class="text-sm font-medium">{{ pp.social_account.display_name?.charAt(0) }}</span>
                            </div>
                            <div
                                class="absolute -bottom-1 -right-1 h-5 w-5 rounded-full bg-background ring-2 ring-background flex items-center justify-center">
                                <component :is="getPlatformIcon(pp.platform)" class="h-4 w-4 text-neutral-600" />
                            </div>
                        </div>
                        <div class="min-w-0">
                            <p class="font-medium text-sm truncate">{{ pp.social_account.display_name }}</p>
                            <p class="text-xs text-muted-foreground">{{ getPlatformLabel(pp.platform) }}</p>
                        </div>
                    </div>
                    <div @click="togglePlatform(pp.id)" class="cursor-pointer shrink-0">
                        <Switch :model-value="selectedPlatformIds.includes(pp.id)" />
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>