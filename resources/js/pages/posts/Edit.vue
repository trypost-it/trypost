<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Clock, AlertCircle, Trash2, Send, Link2, MoreHorizontal, Plus } from 'lucide-vue-next';
import dayjs from '@/dayjs';
import { PlatformPreview } from '@/components/posts/previews';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import DatePicker from '@/components/DatePicker.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import { calendar } from '@/routes';
import { destroy as destroyPost, update as updatePost } from '@/routes/posts';
import { type BreadcrumbItemType } from '@/types';

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    avatar_url: string | null;
}

interface MediaItem {
    id: string;
    url: string;
    type: string;
    original_filename: string;
}

interface PostPlatform {
    id: string;
    social_account_id: string;
    enabled: boolean;
    platform: string;
    content: string;
    content_type: string | null;
    status: string;
    social_account: SocialAccount;
    media: MediaItem[];
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
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Calendar', href: calendar.url() },
    { title: 'Edit Post', href: '#' },
];

// State
const selectedPlatformIds = ref<string[]>(
    props.post.post_platforms.filter(pp => pp.enabled).map(pp => pp.id)
);
const synced = ref(props.post.synced);
const enabledPlatforms = props.post.post_platforms.filter(pp => pp.enabled);
const globalContent = ref(
    enabledPlatforms[0]?.content || props.post.post_platforms[0]?.content || ''
);
const platformContents = ref<Record<string, string>>(
    Object.fromEntries(props.post.post_platforms.map(pp => [pp.id, pp.content]))
);
const platformMedia = ref<Record<string, MediaItem[]>>(
    Object.fromEntries(props.post.post_platforms.map(pp => [pp.id, pp.media || []]))
);
const platformContentTypes = ref<Record<string, string>>(
    Object.fromEntries(props.post.post_platforms.map(pp => [pp.id, pp.content_type || getDefaultContentType(pp.platform)]))
);
const isUploading = ref<Record<string, boolean>>({});
const isSubmitting = ref(false);
const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);
const showEnableSyncDialog = ref(false);
const showDisableSyncDialog = ref(false);
const showPlatformsDialog = ref(false);

// Active tab - first selected platform
const activeTabId = ref<string>(selectedPlatformIds.value[0] || props.post.post_platforms[0]?.id || '');

// Watch selected platforms and update active tab if needed
watch(selectedPlatformIds, (newIds) => {
    if (!newIds.includes(activeTabId.value) && newIds.length > 0) {
        activeTabId.value = newIds[0];
    }
}, { deep: true });

// Convert UTC to workspace timezone for display
const getLocalSchedule = () => {
    if (!props.post.scheduled_at) {
        return { date: '', time: '09:00' };
    }
    const local = dayjs.utc(props.post.scheduled_at).tz(props.workspace.timezone);
    return {
        date: local.format('YYYY-MM-DD'),
        time: local.format('HH:mm'),
    };
};

const { date: initialDate, time: initialTime } = getLocalSchedule();
const scheduledDate = ref(initialDate);
const scheduledTime = ref(initialTime);

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
    };
    return labels[platform] || platform;
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
    };
    return defaults[platform] || '';
}

function getContentTypeOptions(platform: string): ContentTypeOption[] {
    return contentTypeOptions[platform] || [];
}

function hasMultipleContentTypes(platform: string): boolean {
    return (contentTypeOptions[platform]?.length || 0) > 1;
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
};

// Active platform
const activePlatform = computed(() => {
    return props.post.post_platforms.find(pp => pp.id === activeTabId.value);
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
};

// Selected platforms (for tabs)
const selectedPlatforms = computed(() => {
    return props.post.post_platforms.filter(pp => selectedPlatformIds.value.includes(pp.id));
});

// Unselected platforms (for add button)
const unselectedPlatforms = computed(() => {
    return props.post.post_platforms.filter(pp => !selectedPlatformIds.value.includes(pp.id));
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

    for (const pp of props.post.post_platforms) {
        if (!selectedPlatformIds.value.includes(pp.id)) continue;

        const config = getConfig(pp);
        const content = getContent(pp.id);

        const charCount = content.length;
        const hasContent = charCount > 0;
        const withinLimit = charCount <= config.maxContentLength;
        const media = platformMedia.value[pp.id] || [];
        const hasMedia = media.length > 0;

        if (!config.supportsTextOnly && !hasMedia) {
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

    for (const pp of props.post.post_platforms) {
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
    if (!scheduledDate.value) return false;

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

// Platforms that only support single media (replace instead of add)
const singleMediaPlatforms = ['tiktok', 'youtube', 'instagram'];

const isSingleMediaPlatform = (platformId: string): boolean => {
    const platform = props.post.post_platforms.find(pp => pp.id === platformId);
    return platform ? singleMediaPlatforms.includes(platform.platform) : false;
};

const clearPlatformMedia = async (platformId: string) => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const media = platformMedia.value[platformId] || [];

    for (const m of media) {
        await fetch(`/media/${m.id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken },
        });
    }

    platformMedia.value[platformId] = [];
};

const handleFileUpload = async (event: Event, postPlatformId: string) => {
    const input = event.target as HTMLInputElement;
    const files = input.files;

    if (!files || files.length === 0) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // Get other platforms to duplicate to (if synced)
    const otherPlatformIds = synced.value
        ? selectedPlatformIds.value.filter(id => id !== postPlatformId)
        : [];

    // Mark all as uploading
    isUploading.value[postPlatformId] = true;
    for (const id of otherPlatformIds) {
        isUploading.value[id] = true;
    }

    // For single-media platforms, clear existing media first
    if (isSingleMediaPlatform(postPlatformId)) {
        await clearPlatformMedia(postPlatformId);
    }

    for (const file of Array.from(files)) {
        try {
            // 1. Upload once to the current platform
            const formData = new FormData();
            formData.append('file', file);
            formData.append('post_platform_id', postPlatformId);

            const response = await fetch('/media', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-TOKEN': csrfToken },
            });

            if (response.ok) {
                const data = await response.json();

                // Add to current platform (use spread for reactivity)
                const currentMedia = platformMedia.value[postPlatformId] || [];
                platformMedia.value[postPlatformId] = [...currentMedia, data];

                // 2. If synced, duplicate to other platforms
                if (otherPlatformIds.length > 0) {
                    const duplicateResponse = await fetch(`/media/${data.id}/duplicate`, {
                        method: 'POST',
                        body: JSON.stringify({ post_platform_ids: otherPlatformIds }),
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                    });

                    if (duplicateResponse.ok) {
                        const duplicates = await duplicateResponse.json();
                        for (const dup of duplicates) {
                            // For single-media platforms, clear first
                            if (isSingleMediaPlatform(dup.post_platform_id)) {
                                await clearPlatformMedia(dup.post_platform_id);
                            }
                            const existingMedia = platformMedia.value[dup.post_platform_id] || [];
                            platformMedia.value[dup.post_platform_id] = [...existingMedia, dup];
                        }
                    }
                }
            }
        } catch (error) {
            console.error('Upload failed:', error);
        }
    }

    // Mark all as done
    isUploading.value[postPlatformId] = false;
    for (const id of otherPlatformIds) {
        isUploading.value[id] = false;
    }
    input.value = '';
};

const removeMedia = async (postPlatformId: string, mediaId: string) => {
    // Find the media to get its filename for synced removal
    const mediaToRemove = platformMedia.value[postPlatformId]?.find(m => m.id === mediaId);

    if (!mediaToRemove) return;

    // Get target platforms - all selected if synced, otherwise just the current one
    const targetPlatformIds = synced.value ? selectedPlatformIds.value : [postPlatformId];

    for (const targetId of targetPlatformIds) {
        // Find media with same filename in this platform
        const mediaInPlatform = platformMedia.value[targetId]?.find(
            m => m.original_filename === mediaToRemove.original_filename
        );

        if (mediaInPlatform) {
            // Remove from local state
            platformMedia.value[targetId] = platformMedia.value[targetId].filter(
                m => m.id !== mediaInPlatform.id
            );

            // Delete from server
            await fetch(`/media/${mediaInPlatform.id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });
        }
    }
};

const getSubmitData = () => {
    const platforms = props.post.post_platforms
        .filter(pp => selectedPlatformIds.value.includes(pp.id))
        .map(pp => ({
            id: pp.id,
            content: synced.value ? globalContent.value : platformContents.value[pp.id],
            content_type: platformContentTypes.value[pp.id],
        }));

    // Combine date and time into ISO format
    const scheduled_at = scheduledDate.value
        ? `${scheduledDate.value}T${scheduledTime.value}:00`
        : null;

    return { platforms, scheduled_at };
};

const save = () => {
    if (isSubmitting.value) return;

    const { platforms, scheduled_at } = getSubmitData();

    isSubmitting.value = true;

    router.put(updatePost.url(props.post.id), {
        status: props.post.status,
        synced: synced.value,
        scheduled_at,
        platforms,
    }, {
        preserveScroll: true,
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const submit = (status: string = 'scheduled') => {
    if (isSubmitting.value) return;

    const { platforms, scheduled_at } = getSubmitData();

    isSubmitting.value = true;

    router.put(updatePost.url(props.post.id), {
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
    deleteModal.value?.open({
        url: destroyPost.url(props.post.id),
    });
};
</script>

<template>
    <Head title="Edit Post" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col h-[calc(100vh-4rem)]">
            <!-- Top Bar with Tabs and Actions -->
            <div class="flex items-center justify-between border-b px-4 py-2 bg-background">
                <!-- Platform Tabs -->
                <div class="flex items-center gap-2">
                    <Tabs v-model="activeTabId" v-if="selectedPlatforms.length > 0">
                        <TabsList class="h-auto p-1 gap-1">
                            <TooltipProvider v-for="pp in selectedPlatforms" :key="pp.id">
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <TabsTrigger :value="pp.id" class="relative px-2 py-1.5">
                                            <img
                                                :src="getPlatformLogo(pp.platform)"
                                                :alt="getPlatformLabel(pp.platform)"
                                                class="h-6 w-6 rounded"
                                            />
                                            <!-- Validation indicator -->
                                            <span
                                                v-if="contentValidation[pp.id]"
                                                class="absolute -top-0.5 -right-0.5 h-2 w-2 rounded-full border border-background"
                                                :class="contentValidation[pp.id].valid ? 'bg-green-500' : 'bg-red-500'"
                                            />
                                        </TabsTrigger>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>{{ pp.social_account.display_name }}</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </TabsList>
                    </Tabs>

                    <!-- Platforms Menu Button -->
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <button
                                    type="button"
                                    @click="showPlatformsDialog = true"
                                    class="p-2 rounded-lg hover:bg-muted/50 transition-colors"
                                >
                                    <MoreHorizontal class="h-5 w-5 text-muted-foreground" />
                                </button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Manage platforms</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>

                    <span class="mx-2 text-muted-foreground">|</span>

                    <button
                        type="button"
                        @click="deletePost"
                        class="p-2 rounded-lg hover:bg-muted/50 transition-colors text-muted-foreground hover:text-destructive"
                    >
                        <Trash2 class="h-5 w-5" />
                    </button>
                </div>

                <!-- Schedule & Actions -->
                <div class="flex items-center gap-3">
                    <!-- Date/Time Picker -->
                    <div class="flex items-center gap-2">
                        <DatePicker
                            name="scheduled_date"
                            v-model="scheduledDate"
                            class="w-[140px]"
                        />
                        <Input
                            type="time"
                            v-model="scheduledTime"
                            class="w-[100px] h-9"
                        />
                    </div>

                    <span class="text-muted-foreground">|</span>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="selectedPlatformIds.length === 0 || isSubmitting"
                        @click="save"
                    >
                        {{ isSubmitting ? 'Saving...' : 'Save' }}
                    </Button>

                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        :disabled="!canSubmit || isSubmitting"
                        @click="submit('scheduled')"
                    >
                        <Clock class="mr-2 h-4 w-4" />
                        Schedule
                    </Button>

                    <Button
                        type="button"
                        size="sm"
                        :disabled="!canSubmit || isSubmitting"
                        @click="submit('publishing')"
                    >
                        <Send class="mr-2 h-4 w-4" />
                        Publish
                    </Button>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="flex-1 overflow-auto">
                <div v-if="activePlatform && selectedPlatformIds.length > 0" class="max-w-2xl mx-auto py-8 px-4">
                    <!-- Sync Toggle -->
                    <div v-if="selectedPlatformIds.length > 1" class="flex items-center justify-center gap-2 mb-6">
                        <div @click.capture="handleSyncClick">
                            <Switch id="sync-content" v-model="synced" />
                        </div>
                        <Label for="sync-content" class="text-sm cursor-pointer flex items-center gap-1.5">
                            <Link2 class="h-4 w-4" />
                            <span>Sync with</span>
                            <span class="flex items-center gap-1">
                                <img
                                    v-for="op in otherSelectedPlatforms.slice(0, 3)"
                                    :key="op.id"
                                    :src="getPlatformLogo(op.platform)"
                                    class="h-4 w-4 rounded"
                                />
                                <span v-if="otherSelectedPlatforms.length > 3" class="text-muted-foreground">
                                    +{{ otherSelectedPlatforms.length - 3 }}
                                </span>
                            </span>
                        </Label>
                    </div>

                    <!-- Content Type Selector -->
                    <div v-if="hasMultipleContentTypes(activePlatform.platform)" class="flex items-center justify-center gap-2 mb-4">
                        <Label class="text-sm text-muted-foreground">Post as:</Label>
                        <Select
                            :model-value="currentContentType"
                            @update:model-value="setContentType(activePlatform.id, $event)"
                        >
                            <SelectTrigger class="w-[180px]">
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in getContentTypeOptions(activePlatform.platform)"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    <div class="flex flex-col">
                                        <span>{{ option.label }}</span>
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Platform Preview -->
                    <PlatformPreview
                        :key="activePlatform.id"
                        :platform="activePlatform.platform"
                        :social-account="activePlatform.social_account"
                        :content="getContent(activePlatform.id)"
                        :media="currentPlatformMedia"
                        :content-type="currentContentType"
                        :char-count="contentValidation[activePlatform.id]?.charCount || 0"
                        :max-length="contentValidation[activePlatform.id]?.maxLength || 5000"
                        :is-valid="contentValidation[activePlatform.id]?.valid ?? false"
                        :validation-message="contentValidation[activePlatform.id]?.message || ''"
                        :is-uploading="isUploading[activePlatform.id]"
                        @update:content="setContent(activePlatform.id, $event)"
                        @upload="handleFileUpload($event, activePlatform.id)"
                        @remove-media="removeMedia(activePlatform.id, $event)"
                    />

                    <!-- Media Validation Errors -->
                    <Alert v-if="mediaValidation.length > 0" variant="destructive" class="mt-4">
                        <AlertCircle class="h-4 w-4" />
                        <AlertDescription>
                            <ul class="list-disc list-inside">
                                <li v-for="error in mediaValidation" :key="error">{{ error }}</li>
                            </ul>
                        </AlertDescription>
                    </Alert>

                </div>

                <!-- Empty State -->
                <div v-else class="flex flex-col items-center justify-center h-full text-center p-8">
                    <div class="h-16 w-16 rounded-full bg-muted flex items-center justify-center mb-4">
                        <Plus class="h-8 w-8 text-muted-foreground" />
                    </div>
                    <h3 class="text-lg font-semibold mb-2">No platforms selected</h3>
                    <p class="text-muted-foreground mb-4">Select at least one platform to create your post</p>
                    <div class="flex flex-wrap justify-center gap-2">
                        <button
                            v-for="pp in post.post_platforms"
                            :key="pp.id"
                            type="button"
                            @click="togglePlatform(pp.id)"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border hover:bg-muted transition-colors"
                        >
                            <img :src="getPlatformLogo(pp.platform)" class="h-5 w-5" />
                            <span class="text-sm">{{ pp.social_account.display_name }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <ConfirmDeleteModal
        ref="deleteModal"
        title="Delete Post"
        description="Are you sure you want to delete this post? This action cannot be undone."
        action="Delete"
        cancel="Cancel"
    />

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
                    Are you sure you want to sync with the {{ getPlatformLabel(firstSelectedPlatform?.platform || '') }} version?
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction @click="confirmEnableSync">
                    <Link2 class="mr-2 h-4 w-4" />
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
                    Each platform will keep its current content, but future edits will only apply to the platform you're editing.
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
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Select Platforms</DialogTitle>
                <DialogDescription>
                    Choose which platforms to publish this post to.
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-3 py-4">
                <div
                    v-for="pp in post.post_platforms"
                    :key="pp.id"
                    class="flex items-center justify-between p-3 rounded-lg border"
                >
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <img
                                v-if="pp.social_account.avatar_url"
                                :src="pp.social_account.avatar_url"
                                :alt="pp.social_account.display_name"
                                class="h-10 w-10 rounded-full object-cover"
                            />
                            <div v-else class="h-10 w-10 rounded-full bg-muted flex items-center justify-center">
                                <span class="text-sm font-medium">{{ pp.social_account.display_name?.charAt(0) }}</span>
                            </div>
                            <img
                                :src="getPlatformLogo(pp.platform)"
                                :alt="pp.platform"
                                class="absolute -bottom-1 -right-1 h-5 w-5 rounded ring-2 ring-background"
                            />
                        </div>
                        <div>
                            <p class="font-medium text-sm">{{ pp.social_account.display_name }}</p>
                            <p class="text-xs text-muted-foreground">{{ getPlatformLabel(pp.platform) }}</p>
                        </div>
                    </div>
                    <div @click="togglePlatform(pp.id)" class="cursor-pointer">
                        <Switch :model-value="selectedPlatformIds.includes(pp.id)" />
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
