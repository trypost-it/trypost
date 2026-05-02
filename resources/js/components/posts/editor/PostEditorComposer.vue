<script setup lang="ts">
import {
    IconHash,
    IconLibraryPhoto,
    IconLoader2,
    IconMessage2,
    IconMoodSmile,
    IconSparkles,
    IconTrash,
} from '@tabler/icons-vue';
import { ref } from 'vue';

import EmojiPicker from '@/components/posts/EmojiPicker.vue';
import HashtagsModal from '@/components/posts/HashtagsModal.vue';
import MediaPickerDialog from '@/components/posts/MediaPickerDialog.vue';
import { Button } from '@/components/ui/button';
import { Popover, PopoverAnchor, PopoverContent } from '@/components/ui/popover';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { readFileMetadata } from '@/composables/useMedia';
import { store as storeAsset } from '@/routes/app/assets';

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

interface Hashtag {
    id: string;
    name: string;
    hashtags: string;
}

const props = defineProps<{
    isReadOnly: boolean;
    hashtags: Hashtag[];
}>();

const content = defineModel<string>('content', { required: true });
const media = defineModel<MediaItem[]>('media', { required: true });

const emit = defineEmits<{
    (e: 'focus-assistant'): void;
}>();

const isDragging = ref(false);
const uploading = ref(false);
const emojiOpen = ref(false);
const mediaPickerDialog = ref<InstanceType<typeof MediaPickerDialog> | null>(null);
const hashtagsModal = ref<InstanceType<typeof HashtagsModal> | null>(null);

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

const appendHashtags = (hashtag: Hashtag) => {
    if (props.isReadOnly) return;
    const separator = content.value.trim() ? '\n\n' : '';
    content.value += separator + hashtag.hashtags;
};

const appendEmoji = (emoji: string) => {
    if (props.isReadOnly) return;
    content.value += emoji;
    emojiOpen.value = false;
};
</script>

<template>
    <div class="max-w-2xl mx-auto py-8 px-6">
        <div
            class="rounded-lg border bg-card shadow-sm transition-shadow focus-within:shadow-md"
            :class="isDragging ? 'border-primary ring-2 ring-primary/20' : ''"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="handleDrop"
        >
            <div class="flex items-start gap-3 border-b px-5 py-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <IconMessage2 class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold text-foreground">{{ $t('posts.edit.compose_title') }}</h2>
                    <p class="text-xs text-muted-foreground">{{ $t('posts.edit.compose_subtitle') }}</p>
                </div>
            </div>

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
                                @click="emit('focus-assistant')"
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

            <div v-if="isDragging && !isReadOnly" class="border-t bg-primary/5 px-5 py-6 text-center text-sm text-primary">
                {{ $t('posts.edit.drag_drop') }}
            </div>
        </div>

        <HashtagsModal ref="hashtagsModal" :hashtags="hashtags" @select="appendHashtags" />
        <MediaPickerDialog ref="mediaPickerDialog" @select="addMediaFromGallery" />
    </div>
</template>
