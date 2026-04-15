<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import {
    IconCircleCheck,
    IconCloudUpload,
    IconHash,
    IconLoader2,
    IconTag,
    IconTrash,
} from '@tabler/icons-vue';
import { computed, onUnmounted, ref, watch } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import DatePicker from '@/components/DatePicker.vue';
import HashtagsModal from '@/components/posts/HashtagsModal.vue';
import CommentsTab from '@/components/posts/editor/CommentsTab.vue';
import PreviewTab from '@/components/posts/editor/PreviewTab.vue';
import ScheduleTab from '@/components/posts/editor/ScheduleTab.vue';
import WritingAssistantTab from '@/components/posts/editor/WritingAssistantTab.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
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
    timezone: string;
}

const props = defineProps<{
    workspace: Workspace;
    post: Post;
    socialAccounts: SocialAccount[];
    platformConfigs: Record<string, any>;
    pinterestBoards: any[];
    labels: { id: string; name: string; color: string }[];
    hashtags: { id: string; name: string; hashtags: string }[];
}>();

const post = computed(() => props.post);
const isReadOnly = computed(() => ['published', 'partially_published'].includes(post.value.status));

// Content
const content = ref(post.value.content || '');
const media = ref<MediaItem[]>(post.value.media || []);

// Platforms
const selectedPlatformIds = ref<string[]>(
    post.value.post_platforms.filter((pp) => pp.enabled).map((pp) => pp.id),
);

// Schedule
const getLocalSchedule = () => {
    if (!post.value.scheduled_at) return '';
    return dayjs.utc(post.value.scheduled_at).tz(props.workspace.timezone).format('YYYY-MM-DDTHH:mm:00');
};
const scheduledDateTime = ref(getLocalSchedule());

// Labels
const selectedLabelIds = ref<string[]>(post.value.labels?.map((l) => l.id) || []);

// UI state
const isSubmitting = ref(false);
const isSaving = ref(false);
const showSaved = ref(false);
const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);
const hashtagsModal = ref<InstanceType<typeof HashtagsModal> | null>(null);

const timezoneAbbr = computed(() => dayjs().tz(props.workspace.timezone).format('z'));
const fileInput = ref<HTMLInputElement | null>(null);
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

// First enabled platform for preview
const previewPlatform = computed(() => {
    const enabledId = selectedPlatformIds.value[0];
    return post.value.post_platforms.find((pp) => pp.id === enabledId) || post.value.post_platforms[0];
});

// Media upload
const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const triggerFileInput = () => fileInput.value?.click();

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files) {
        uploadFiles(Array.from(target.files));
        target.value = '';
    }
};

const handleDrop = (event: DragEvent) => {
    isDragging.value = false;
    if (event.dataTransfer?.files) {
        uploadFiles(Array.from(event.dataTransfer.files));
    }
};

const uploadFiles = async (files: File[]) => {
    uploading.value = true;

    for (const file of files) {
        const formData = new FormData();
        formData.append('media', file);

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

// Save logic
const getSubmitData = () => {
    const platforms = post.value.post_platforms
        .filter((pp) => selectedPlatformIds.value.includes(pp.id))
        .map((pp) => ({
            id: pp.id,
            content_type: pp.content_type,
            meta: pp.meta || {},
        }));

    return {
        content: content.value,
        media: media.value,
        platforms,
        scheduled_at: scheduledDateTime.value || null,
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

watch([content, media, selectedPlatformIds, scheduledDateTime, selectedLabelIds], triggerAutosave, { deep: true });

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

const deletePost = () => {
    if (isReadOnly.value) return;
    deleteModal.value?.open({ url: destroyPost.url(post.value.id) });
};

// Echo: listen for real-time platform status updates
useEcho(`post.${post.value.id}`, '.PostPlatformStatusUpdated', () => {
    router.reload({ only: ['post'], preserveScroll: true });
});

const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
};
</script>

<template>
    <Head :title="$t('posts.edit.title')" />

    <AppLayout :full-width="true">
        <!-- <template v-if="!isReadOnly" #header-actions>
            <div class="flex items-center gap-3">
                <Button type="button" variant="ghost" size="icon" @click="deletePost"
                    :disabled="isSaving || isSubmitting" class="text-muted-foreground hover:text-destructive">
                    <IconTrash class="h-4 w-4" />
                </Button>

                <span class="h-4 w-px bg-border" />

                <Button type="button" variant="secondary" class="shrink-0"
                    :disabled="isSubmitting || selectedPlatformIds.length === 0" @click="submit('scheduled')">
                    {{ $t('posts.edit.schedule') }}
                </Button>

                <Button type="button" class="shrink-0"
                    :disabled="isSubmitting || selectedPlatformIds.length === 0" @click="submit('publishing')">
                    {{ $t('posts.edit.post_now') }}
                </Button>
            </div>
        </template> -->

        <div class="flex flex-col h-screen">
            <!-- <div class="relative flex items-center justify-between gap-4 border-b px-4 py-2 bg-background">
                <div class="flex min-w-0 items-center gap-2">
                    <span v-if="isSaving" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <IconLoader2 class="h-4 w-4 animate-spin" />
                        {{ $t('posts.edit.saving') }}
                    </span>
                    <span v-else-if="showSaved" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <IconCircleCheck class="h-4 w-4 text-green-500" />
                        {{ $t('posts.edit.saved') }}
                    </span>
                </div>

                <div v-if="!isReadOnly" class="hidden lg:flex shrink-0 items-center gap-2">
                    <Popover>
                        <PopoverTrigger as-child>
                            <Button type="button" variant="outline">
                                <IconTag class="h-4 w-4" />
                                {{ $t('posts.edit.labels') }}
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent class="w-56 p-2" align="end">
                            <div v-if="labels.length > 0">
                                <div v-for="label in labels" :key="label.id"
                                    class="flex items-center gap-2 px-2 py-1.5 rounded-md hover:bg-muted cursor-pointer"
                                    @click="toggleLabel(label.id)">
                                    <Checkbox :model-value="selectedLabelIds.includes(label.id)" />
                                    <span class="h-3 w-3 rounded-full shrink-0" :style="{ backgroundColor: label.color }" />
                                    <span class="text-sm truncate">{{ label.name }}</span>
                                </div>
                            </div>
                            <p v-else class="px-2 py-3 text-center text-sm text-muted-foreground">{{ $t('posts.edit.no_labels') }}</p>
                        </PopoverContent>
                    </Popover>

                    <Button type="button" variant="outline" size="icon" @click="hashtagsModal?.open()">
                        <IconHash class="h-4 w-4" />
                    </Button>

                    <DatePicker v-model="scheduledDateTime" :show-time="true" class="w-auto" />
                    <span class="whitespace-nowrap text-xs text-muted-foreground">{{ timezoneAbbr }}</span>
                </div>
            </div> -->

            <div class="flex-1 overflow-hidden">
                <div class="h-full flex">
                    <div class="w-full lg:w-2/3 lg:border-r overflow-y-auto relative">
                        <div class="max-w-lg mx-auto py-8 px-6 space-y-6">
                            <div>
                                <Label class="mb-2 block text-sm font-medium">{{ $t('posts.edit.media') }}</Label>
                                <div v-if="media.length > 0" class="mb-3 grid grid-cols-4 gap-2">
                                    <div v-for="item in media" :key="item.id" class="group relative aspect-square overflow-hidden rounded-lg border bg-muted">
                                        <video v-if="item.type === 'video' || item.mime_type?.startsWith('video/')" :src="item.url" class="w-full h-full object-cover" muted />
                                        <img v-else :src="item.url" :alt="item.original_filename" class="w-full h-full object-cover" loading="lazy" />
                                        <button v-if="!isReadOnly" type="button" class="absolute top-1 right-1 flex h-6 w-6 items-center justify-center rounded-full bg-black/60 text-white opacity-0 transition-opacity hover:bg-black/80 group-hover:opacity-100" @click="removeMedia(item.id)">
                                            <IconTrash class="h-3 w-3" />
                                        </button>
                                    </div>
                                    <button v-if="!isReadOnly" type="button" class="flex aspect-square items-center justify-center rounded-lg border-2 border-dashed text-muted-foreground transition-colors hover:border-primary/50 hover:text-primary" @click="triggerFileInput">
                                        <IconCloudUpload class="h-6 w-6" />
                                    </button>
                                </div>
                                <div v-else-if="!isReadOnly" class="relative flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 transition-colors" :class="isDragging ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50'" @click="triggerFileInput" @dragover.prevent="isDragging = true" @dragleave.prevent="isDragging = false" @drop.prevent="handleDrop">
                                    <IconCloudUpload class="mb-2 h-8 w-8 text-muted-foreground" />
                                    <p class="text-sm text-muted-foreground">{{ $t('posts.edit.drag_drop') }}</p>
                                    <div v-if="uploading" class="absolute inset-0 flex items-center justify-center rounded-lg bg-background/80">
                                        <IconLoader2 class="h-5 w-5 animate-spin text-muted-foreground" />
                                    </div>
                                </div>
                                <input ref="fileInput" type="file" class="hidden" multiple accept="image/jpeg,image/png,image/gif,image/webp,video/mp4" @change="handleFileSelect" />
                            </div>
                            <div>
                                <Label class="mb-2 block text-sm font-medium">{{ $t('posts.edit.caption') }}</Label>
                                <Textarea v-model="content" :placeholder="$t('posts.edit.caption_placeholder')" :disabled="isReadOnly" class="min-h-[160px] resize-none" />
                                <p class="mt-1 text-right text-xs text-muted-foreground">{{ content.length }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="hidden lg:block lg:w-1/3 overflow-hidden">
                        <Tabs default-value="schedule" class="h-full flex flex-col">
                            <TabsList class="mx-4 mt-4 w-auto shrink-0">
                                <TabsTrigger value="preview">{{ $t('posts.edit.tabs.preview') }}</TabsTrigger>
                                <TabsTrigger value="schedule">{{ $t('posts.edit.tabs.schedule') }}</TabsTrigger>
                                <TabsTrigger value="comments">{{ $t('posts.edit.tabs.comments') }}</TabsTrigger>
                                <TabsTrigger value="assistant">{{ $t('posts.edit.tabs.writing_assistant') }}</TabsTrigger>
                            </TabsList>

                            <TabsContent value="preview" class="flex-1 overflow-y-auto">
                                <PreviewTab v-if="previewPlatform" :platform="previewPlatform.platform" :content="content" :media="media" :social-account="previewPlatform.social_account" :content-type="previewPlatform.content_type" />
                            </TabsContent>

                            <TabsContent value="schedule" class="flex-1 overflow-y-auto p-4">
                                <ScheduleTab :post-platforms="post.post_platforms" :selected-platform-ids="selectedPlatformIds" @toggle-platform="togglePlatform" />
                            </TabsContent>

                            <TabsContent value="comments" class="flex-1 overflow-y-auto p-4">
                                <CommentsTab />
                            </TabsContent>

                            <TabsContent value="assistant" class="flex-1 overflow-y-auto p-4">
                                <WritingAssistantTab />
                            </TabsContent>
                        </Tabs>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <ConfirmDeleteModal ref="deleteModal" :title="$t('posts.delete.title')" :description="$t('posts.delete.description')" :action="$t('posts.delete.confirm')" :cancel="$t('posts.delete.cancel')" />
    <HashtagsModal ref="hashtagsModal" :hashtags="hashtags" @select="appendHashtags" />
</template>
