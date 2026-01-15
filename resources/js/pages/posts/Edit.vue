<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Clock, Image, Video, X, FileText, AlertCircle, Check, Trash2, Send } from 'lucide-vue-next';
import dayjs from '@/dayjs';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Alert, AlertDescription } from '@/components/ui/alert';
import DatePicker from '@/components/DatePicker.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
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
    status: string;
    social_account: SocialAccount;
    media: MediaItem[];
}

interface Post {
    id: string;
    status: string;
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
    { title: 'Workspaces', href: '/workspaces' },
    { title: props.workspace.name, href: `/workspaces/${props.workspace.id}` },
    { title: 'Calendar', href: `/workspaces/${props.workspace.id}/calendar` },
    { title: 'Edit Post', href: '#' },
];

// State
const selectedPlatformIds = ref<string[]>(
    props.post.post_platforms.filter(pp => pp.enabled).map(pp => pp.id)
);
const useGlobalContent = ref(true);
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
const isUploading = ref<Record<string, boolean>>({});
const isSubmitting = ref(false);
const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);

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

const getConfig = (postPlatform: PostPlatform): PlatformConfig => {
    return props.platformConfigs[postPlatform.social_account_id] || {
        maxContentLength: 5000,
        maxImages: 10,
        allowedMediaTypes: ['image', 'video'],
        supportsTextOnly: true,
    };
};

// Computed
const contentValidation = computed(() => {
    const results: Record<string, { valid: boolean; message: string }> = {};

    for (const pp of props.post.post_platforms) {
        if (!selectedPlatformIds.value.includes(pp.id)) continue;

        const config = getConfig(pp);
        const content = useGlobalContent.value
            ? globalContent.value
            : (platformContents.value[pp.id] || '');

        const charCount = content.length;
        const hasContent = charCount > 0;
        const withinLimit = charCount <= config.maxContentLength;
        const media = platformMedia.value[pp.id] || [];
        const hasMedia = media.length > 0;

        if (!config.supportsTextOnly && !hasMedia) {
            results[pp.id] = { valid: false, message: 'Requires media' };
        } else if (!hasContent && !hasMedia) {
            results[pp.id] = { valid: false, message: 'No content' };
        } else if (!withinLimit) {
            results[pp.id] = { valid: false, message: `${charCount - config.maxContentLength} exceeded` };
        } else {
            results[pp.id] = { valid: true, message: `${charCount}/${config.maxContentLength}` };
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
    } else {
        selectedPlatformIds.value.splice(index, 1);
    }
};

const handleFileUpload = async (event: Event, postPlatformId: string) => {
    const input = event.target as HTMLInputElement;
    const files = input.files;

    if (!files || files.length === 0) return;

    isUploading.value[postPlatformId] = true;

    for (const file of Array.from(files)) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('post_platform_id', postPlatformId);

        try {
            const response = await fetch('/media', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            if (response.ok) {
                const data = await response.json();
                if (!platformMedia.value[postPlatformId]) {
                    platformMedia.value[postPlatformId] = [];
                }
                platformMedia.value[postPlatformId].push(data);
            }
        } catch (error) {
            console.error('Upload failed:', error);
        }
    }

    isUploading.value[postPlatformId] = false;
    input.value = '';
};

const removeMedia = async (postPlatformId: string, mediaId: string) => {
    platformMedia.value[postPlatformId] = platformMedia.value[postPlatformId].filter(m => m.id !== mediaId);

    await fetch(`/media/${mediaId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
    });
};

const submit = (status: string = 'scheduled') => {
    if (isSubmitting.value) return;

    const platforms = props.post.post_platforms
        .filter(pp => selectedPlatformIds.value.includes(pp.id))
        .map(pp => ({
            id: pp.id,
            content: useGlobalContent.value ? globalContent.value : platformContents.value[pp.id],
        }));

    // Combine date and time into ISO format
    const scheduled_at = scheduledDate.value
        ? `${scheduledDate.value}T${scheduledTime.value}:00`
        : null;

    isSubmitting.value = true;

    router.put(`/workspaces/${props.workspace.id}/posts/${props.post.id}`, {
        status,
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
        url: `/workspaces/${props.workspace.id}/posts/${props.post.id}`,
    });
};
</script>

<template>
    <Head title="Edit Post" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Edit Post</h1>
                    <p class="text-muted-foreground">
                        Configure and schedule your post
                    </p>
                </div>
                <Button variant="ghost" size="sm" @click="deletePost" class="text-destructive hover:text-destructive">
                    <Trash2 class="h-4 w-4 mr-2" />
                    Delete
                </Button>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Platform Selection -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Social Networks</CardTitle>
                            <CardDescription>
                                Select where you want to publish
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <button
                                    v-for="pp in post.post_platforms"
                                    :key="pp.id"
                                    type="button"
                                    @click="togglePlatform(pp.id)"
                                    class="flex items-center gap-3 p-3 rounded-lg border text-left transition-all hover:border-primary"
                                    :class="selectedPlatformIds.includes(pp.id) ? 'border-primary bg-primary/5 ring-1 ring-primary' : ''"
                                >
                                    <div class="relative shrink-0">
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
                                            class="absolute -bottom-1 -right-1 h-5 w-5 rounded-full ring-2 ring-background"
                                        />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-sm truncate">{{ pp.social_account.display_name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ getPlatformLabel(pp.platform) }}</p>
                                    </div>
                                    <div
                                        class="shrink-0 h-5 w-5 rounded-full border-2 flex items-center justify-center transition-colors"
                                        :class="selectedPlatformIds.includes(pp.id) ? 'border-primary bg-primary text-primary-foreground' : 'border-muted-foreground/30'"
                                    >
                                        <Check v-if="selectedPlatformIds.includes(pp.id)" class="h-3 w-3" />
                                    </div>
                                </button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Content Editor -->
                    <Card v-if="selectedPlatformIds.length > 0">
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <div>
                                    <CardTitle>Content</CardTitle>
                                    <CardDescription>
                                        Write your post content
                                    </CardDescription>
                                </div>
                                <div v-if="selectedPlatformIds.length > 1" class="flex items-center gap-2">
                                    <Switch
                                        id="global-content"
                                        :checked="useGlobalContent"
                                        @update:checked="useGlobalContent = $event"
                                    />
                                    <Label for="global-content" class="text-sm cursor-pointer">
                                        Same content
                                    </Label>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- Global Content Mode -->
                            <div v-if="useGlobalContent" class="space-y-3">
                                <textarea
                                    v-model="globalContent"
                                    class="w-full min-h-[180px] rounded-lg border border-input bg-background px-4 py-3 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring resize-none"
                                    placeholder="What do you want to share?"
                                />
                                <div class="flex flex-wrap gap-2">
                                    <div
                                        v-for="pp in post.post_platforms.filter(p => selectedPlatformIds.includes(p.id))"
                                        :key="pp.id"
                                        class="flex items-center gap-1.5 px-2 py-1 rounded-md text-xs"
                                        :class="contentValidation[pp.id]?.valid ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'"
                                    >
                                        <img :src="getPlatformLogo(pp.platform)" class="h-4 w-4" />
                                        <span>{{ contentValidation[pp.id]?.message }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Per-Platform Content Mode -->
                            <div v-else class="space-y-4">
                                <div
                                    v-for="pp in post.post_platforms.filter(p => selectedPlatformIds.includes(p.id))"
                                    :key="pp.id"
                                    class="space-y-2 p-4 rounded-lg border"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <img :src="getPlatformLogo(pp.platform)" class="h-5 w-5" />
                                            <span class="font-medium text-sm">{{ pp.social_account.display_name }}</span>
                                        </div>
                                        <span
                                            class="text-xs px-2 py-0.5 rounded"
                                            :class="contentValidation[pp.id]?.valid ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'"
                                        >
                                            {{ contentValidation[pp.id]?.message }}
                                        </span>
                                    </div>
                                    <textarea
                                        v-model="platformContents[pp.id]"
                                        class="w-full min-h-[120px] rounded-lg border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring resize-none"
                                        :placeholder="`Content for ${getPlatformLabel(pp.platform)}`"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Media Upload per Platform -->
                    <Card v-for="pp in post.post_platforms.filter(p => selectedPlatformIds.includes(p.id))" :key="'media-' + pp.id">
                        <CardHeader class="pb-3">
                            <div class="flex items-center gap-2">
                                <img :src="getPlatformLogo(pp.platform)" class="h-5 w-5" />
                                <CardTitle class="text-base">Media - {{ pp.social_account.display_name }}</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-wrap gap-3">
                                <div
                                    v-for="media in platformMedia[pp.id]"
                                    :key="media.id"
                                    class="relative group"
                                >
                                    <div class="w-20 h-20 rounded-lg overflow-hidden border bg-muted">
                                        <img
                                            v-if="media.type === 'image'"
                                            :src="media.url"
                                            :alt="media.original_filename"
                                            class="w-full h-full object-cover"
                                        />
                                        <div v-else class="w-full h-full flex items-center justify-center">
                                            <Video class="h-6 w-6 text-muted-foreground" />
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        @click="removeMedia(pp.id, media.id)"
                                        class="absolute -top-2 -right-2 bg-destructive text-destructive-foreground rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity shadow-sm"
                                    >
                                        <X class="h-3 w-3" />
                                    </button>
                                </div>

                                <label class="w-20 h-20 rounded-lg border-2 border-dashed flex flex-col items-center justify-center cursor-pointer hover:bg-accent transition-colors">
                                    <input
                                        type="file"
                                        accept="image/*,video/*"
                                        multiple
                                        class="hidden"
                                        @change="(e) => handleFileUpload(e, pp.id)"
                                        :disabled="isUploading[pp.id]"
                                    />
                                    <Image class="h-5 w-5 text-muted-foreground" />
                                    <span class="text-xs text-muted-foreground mt-1">
                                        {{ isUploading[pp.id] ? '...' : 'Add' }}
                                    </span>
                                </label>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Media Validation -->
                    <Alert v-if="mediaValidation.length > 0" variant="destructive">
                        <AlertCircle class="h-4 w-4" />
                        <AlertDescription>
                            <ul class="list-disc list-inside">
                                <li v-for="error in mediaValidation" :key="error">{{ error }}</li>
                            </ul>
                        </AlertDescription>
                    </Alert>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Scheduling</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label for="scheduled_date">Date</Label>
                                <DatePicker
                                    name="scheduled_date"
                                    v-model="scheduledDate"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="scheduled_time">Time</Label>
                                <Input
                                    id="scheduled_time"
                                    type="time"
                                    v-model="scheduledTime"
                                />
                            </div>

                            <div class="pt-4 space-y-2">
                                <Button
                                    type="button"
                                    class="w-full"
                                    :disabled="!canSubmit || isSubmitting"
                                    @click="submit('scheduled')"
                                >
                                    <Clock class="mr-2 h-4 w-4" />
                                    {{ isSubmitting ? 'Saving...' : 'Schedule Post' }}
                                </Button>

                                <Button
                                    type="button"
                                    variant="secondary"
                                    class="w-full"
                                    :disabled="!canSubmit || isSubmitting"
                                    @click="submit('publishing')"
                                >
                                    <Send class="mr-2 h-4 w-4" />
                                    {{ isSubmitting ? 'Publishing...' : 'Publish Now' }}
                                </Button>

                                <Button
                                    type="button"
                                    variant="outline"
                                    class="w-full"
                                    :disabled="selectedPlatformIds.length === 0 || isSubmitting"
                                    @click="submit('draft')"
                                >
                                    <FileText class="mr-2 h-4 w-4" />
                                    {{ isSubmitting ? 'Saving...' : 'Save Draft' }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card v-if="selectedPlatformIds.length > 0">
                        <CardHeader>
                            <CardTitle>Summary</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-muted-foreground">Selected networks</span>
                                    <span class="font-medium">{{ selectedPlatformIds.length }}</span>
                                </div>
                                <div class="pt-2 border-t">
                                    <p class="text-xs text-muted-foreground mb-2">Publish to:</p>
                                    <div class="flex flex-wrap gap-1">
                                        <div
                                            v-for="pp in post.post_platforms.filter(p => selectedPlatformIds.includes(p.id))"
                                            :key="pp.id"
                                            class="flex items-center gap-1 px-2 py-1 bg-muted rounded text-xs"
                                        >
                                            <img :src="getPlatformLogo(pp.platform)" class="h-3 w-3" />
                                            {{ pp.social_account.display_name }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
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
</template>
