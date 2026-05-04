<script setup lang="ts">
import {
    IconAlertTriangle,
    IconCloudUpload,
    IconGripVertical,
    IconHash,
    IconLibraryPhoto,
    IconLoader2,
    IconMoodSmile,
    IconSparkles,
    IconTrash,
    IconVideo,
    IconWriting,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, nextTick, ref } from 'vue';

import ImagePreviewDialog from '@/components/ImagePreviewDialog.vue';
import EmojiPicker from '@/components/posts/EmojiPicker.vue';
import MediaPickerDialog from '@/components/posts/MediaPickerDialog.vue';
import SignaturesModal from '@/components/posts/SignaturesModal.vue';
import { Button } from '@/components/ui/button';
import { Popover, PopoverAnchor, PopoverContent } from '@/components/ui/popover';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { formatBytes, readFileMetadata } from '@/composables/useMedia';
import { getPlatformLabel, getPlatformLogo } from '@/composables/usePlatformLogo';
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

interface Signature {
    id: string;
    name: string;
    content: string;
}

interface PlatformLimit {
    platform: string;
    maxLength: number;
}

interface MediaIssue {
    platform: string;
    reason: string;
}

const props = defineProps<{
    signatures: Signature[];
    platformLimits: PlatformLimit[];
    mediaIssues: Record<string, MediaIssue[]>;
}>();

const content = defineModel<string>('content', { required: true });
const media = defineModel<MediaItem[]>('media', { required: true });

const emit = defineEmits<{
    (e: 'open-ai-generate'): void;
    (e: 'open-ai-review'): void;
}>();

const isDragging = ref(false);
const uploading = ref(false);
const emojiOpen = ref(false);
const mediaPickerDialog = ref<InstanceType<typeof MediaPickerDialog> | null>(null);
const signaturesModal = ref<InstanceType<typeof SignaturesModal> | null>(null);

const dragMediaIndex = ref<number | null>(null);
const dragOverIndex = ref<number | null>(null);
const mediaThumbRefs = ref<HTMLElement[]>([]);
const previewIndex = ref<number | null>(null);

// Image-only URLs (videos are skipped) in the same order as `media`. The
// preview index is computed against THIS list to keep arrow navigation tight.
const previewImages = computed(() =>
    media.value.filter((m) => !isVideo(m)).map((m) => m.url),
);

const openPreview = (item: MediaItem) => {
    if (isVideo(item)) return;
    const idx = previewImages.value.indexOf(item.url);
    previewIndex.value = idx >= 0 ? idx : 0;
};

const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const isVideo = (item: MediaItem): boolean =>
    item.type === 'video' || Boolean(item.mime_type?.startsWith('video/'));

const formatDuration = (seconds: number): string => {
    const total = Math.round(seconds);
    const m = Math.floor(total / 60);
    const s = total % 60;
    return `${m}:${s.toString().padStart(2, '0')}`;
};

const limitsWithUsage = computed(() =>
    props.platformLimits.map((p) => {
        const used = content.value.length;
        const ratio = p.maxLength > 0 ? used / p.maxLength : 0;
        const state = ratio > 1 ? 'over' : ratio >= 0.9 ? 'warn' : 'ok';
        return { ...p, used, state };
    }),
);

const limitClass = (state: string): string => {
    if (state === 'over') return 'text-destructive';
    if (state === 'warn') return 'text-amber-600 dark:text-amber-400';
    return 'text-muted-foreground';
};

const smallestLimit = computed(() => {
    if (props.platformLimits.length === 0) return null;
    return Math.min(...props.platformLimits.map((p) => p.maxLength));
});

const overflowParts = computed(() => {
    const limit = smallestLimit.value;
    if (limit === null || content.value.length <= limit) {
        return { fits: content.value, overflow: '' };
    }
    return {
        fits: content.value.slice(0, limit),
        overflow: content.value.slice(limit),
    };
});

const handleDrop = (event: DragEvent) => {
    isDragging.value = false;
    if (event.dataTransfer?.files && event.dataTransfer.files.length > 0) {
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

const appendSignature = (signature: Signature) => {
    const separator = content.value.trim() ? '\n\n' : '';
    content.value += separator + signature.content;
};

const appendEmoji = (emoji: string) => {
    content.value += emoji;
    emojiOpen.value = false;
};

const moveMediaItem = (from: number, to: number) => {
    if (from === to || to < 0 || to >= media.value.length) return;
    const next = [...media.value];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);
    media.value = next;
};

const onMediaDragStart = (event: DragEvent, index: number) => {
    dragMediaIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(index));
    }
};

const onMediaDragOver = (event: DragEvent, index: number) => {
    if (dragMediaIndex.value === null) return;
    event.preventDefault();
    event.stopPropagation();
    dragOverIndex.value = index;
    if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
};

const onMediaDrop = (event: DragEvent, index: number) => {
    if (dragMediaIndex.value === null) return;
    event.preventDefault();
    event.stopPropagation();
    const from = dragMediaIndex.value;
    dragMediaIndex.value = null;
    dragOverIndex.value = null;
    moveMediaItem(from, index);
};

const onMediaDragEnd = () => {
    dragMediaIndex.value = null;
    dragOverIndex.value = null;
};

const GRID_COLS = 4;

const onMediaKeydown = async (event: KeyboardEvent, index: number) => {
    if (media.value.length < 2) return;
    if (! event.altKey) return;

    const deltas: Record<string, number> = {
        ArrowLeft: -1,
        ArrowRight: 1,
        ArrowUp: -GRID_COLS,
        ArrowDown: GRID_COLS,
    };
    const delta = deltas[event.key];
    if (delta === undefined) return;

    event.preventDefault();
    const target = index + delta;
    if (target < 0 || target >= media.value.length) return;
    moveMediaItem(index, target);

    await nextTick();
    mediaThumbRefs.value[target]?.focus();
};

const issueLabel = (reason: string): string => trans(`posts.form.warnings.${reason}`);
</script>

<template>
    <div class="mx-auto max-w-2xl px-6 py-10">
        <div
            class="relative"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="handleDrop"
        >
            <!-- Media grid (top) — always shown so "Add" tile is discoverable -->
            <div class="mb-6">
                <div class="grid grid-cols-4 gap-2">
                    <div
                        v-for="(item, index) in media"
                        :key="item.id"
                        :ref="(el) => { if (el) mediaThumbRefs[index] = el as HTMLElement; }"
                        class="group relative aspect-square cursor-zoom-in overflow-hidden rounded-xl bg-muted transition-all focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                        :class="[
                            dragMediaIndex === index ? 'opacity-40' : '',
                            dragOverIndex === index && dragMediaIndex !== index ? 'ring-2 ring-primary ring-offset-2' : '',
                            mediaIssues[item.id] ? 'ring-1 ring-destructive ring-offset-1' : '',
                        ]"
                        tabindex="0"
                        :draggable="media.length > 1"
                        @click="openPreview(item)"
                        @dragstart="onMediaDragStart($event, index)"
                        @dragover="onMediaDragOver($event, index)"
                        @drop="onMediaDrop($event, index)"
                        @dragend="onMediaDragEnd"
                        @keydown="onMediaKeydown($event, index)"
                    >
                        <video
                            v-if="isVideo(item)"
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

                        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-black/60 to-transparent opacity-0 transition-opacity group-hover:opacity-100" />

                        <div class="absolute bottom-1.5 left-1.5 flex flex-col items-start gap-1 text-[10px] font-medium text-white">
                            <span
                                v-if="isVideo(item) && item.meta?.duration"
                                class="inline-flex items-center gap-0.5 rounded-md bg-black/65 px-1.5 py-0.5 backdrop-blur-sm"
                            >
                                <IconVideo class="size-2.5" />
                                {{ formatDuration(item.meta.duration) }}
                            </span>
                            <span
                                v-if="item.size"
                                class="inline-flex rounded-md bg-black/65 px-1.5 py-0.5 backdrop-blur-sm"
                            >
                                {{ formatBytes(item.size) }}
                            </span>
                        </div>

                        <TooltipProvider v-if="mediaIssues[item.id]" :delay-duration="100">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <span class="absolute bottom-1.5 right-1.5 flex h-5 items-center gap-0.5 rounded-full bg-destructive px-1.5 text-[10px] font-semibold text-destructive-foreground shadow-sm">
                                        <IconAlertTriangle class="size-2.5" />
                                        {{ mediaIssues[item.id].length }}
                                    </span>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <ul class="space-y-1 text-xs">
                                        <li v-for="iss in mediaIssues[item.id]" :key="iss.platform" class="flex items-center gap-1.5">
                                            <img :src="getPlatformLogo(iss.platform)" :alt="iss.platform" class="size-3 object-contain" />
                                            <span class="font-medium">{{ getPlatformLabel(iss.platform) }}:</span>
                                            <span class="opacity-80">{{ issueLabel(iss.reason) }}</span>
                                        </li>
                                    </ul>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>

                        <span
                            v-if="media.length > 1"
                            class="absolute left-1.5 top-1.5 flex h-6 w-6 cursor-grab items-center justify-center rounded-md bg-black/55 text-white opacity-0 backdrop-blur-sm transition-opacity group-hover:opacity-100 group-focus:opacity-100"
                        >
                            <IconGripVertical class="h-3.5 w-3.5" />
                        </span>

                        <button
                            type="button"
                            class="absolute right-1.5 top-1.5 flex h-6 w-6 items-center justify-center rounded-md bg-black/55 text-white opacity-0 backdrop-blur-sm transition-all hover:bg-destructive group-hover:opacity-100 group-focus:opacity-100"
                            @click.stop="removeMedia(item.id)"
                        >
                            <IconTrash class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <button
                        type="button"
                        class="flex aspect-square flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-border/60 text-muted-foreground transition-colors hover:border-primary/50 hover:bg-primary/5 hover:text-primary"
                        @click="mediaPickerDialog?.open()"
                    >
                        <IconLibraryPhoto class="h-5 w-5" />
                        <span class="text-[10px] font-medium">{{ $t('posts.edit.add') }}</span>
                    </button>
                </div>
            </div>

            <!-- Toolbar: between photos and textarea -->
            <div class="mb-4 flex items-center gap-0.5">
                <Popover v-model:open="emojiOpen">
                    <PopoverAnchor as-child>
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        class="size-8 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                                        @click="emojiOpen = !emojiOpen"
                                    >
                                        <IconMoodSmile class="size-4" />
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
                                class="size-8 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                                @click="signaturesModal?.open()"
                            >
                                <IconHash class="size-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{ $t('posts.edit.signatures') }}</TooltipContent>
                    </Tooltip>
                </TooltipProvider>

                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                class="size-8 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                                @click="emit('open-ai-generate')"
                            >
                                <IconSparkles class="size-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{ $t('posts.ai.generate.button_tooltip') }}</TooltipContent>
                    </Tooltip>
                </TooltipProvider>

                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                class="size-8 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                                @click="emit('open-ai-review')"
                            >
                                <IconWriting class="size-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{ $t('posts.ai.review.button_tooltip') }}</TooltipContent>
                    </Tooltip>
                </TooltipProvider>

                <span v-if="uploading" class="ml-2 flex items-center gap-1.5 text-xs text-muted-foreground">
                    <IconLoader2 class="size-3.5 animate-spin" />
                </span>
            </div>

            <!-- Per-platform counters (below menu, above textarea) -->
            <div
                v-if="limitsWithUsage.length > 0"
                class="mb-4 flex flex-wrap items-center gap-3"
            >
                <TooltipProvider v-for="limit in limitsWithUsage" :key="limit.platform" :delay-duration="200">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <span
                                class="inline-flex items-center gap-1 text-[11px] font-medium tabular-nums"
                                :class="limitClass(limit.state)"
                            >
                                <img :src="getPlatformLogo(limit.platform)" :alt="limit.platform" class="size-3 object-contain" />
                                {{ limit.used }}<span class="opacity-50">/{{ limit.maxLength }}</span>
                            </span>
                        </TooltipTrigger>
                        <TooltipContent>{{ getPlatformLabel(limit.platform) }}</TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>

            <!-- Textarea: borderless, sheet-of-paper feel.
                 Mirror div sits behind to highlight chars beyond the smallest platform limit.
                 Both share identical font/padding/leading/wrap so highlights align with the textarea text. -->
            <div class="relative font-sans text-base leading-[1.7]">
                <div
                    v-if="overflowParts.overflow"
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-0 whitespace-pre-wrap break-words p-0 font-sans text-base leading-[1.7] text-transparent"
                >
                    <span>{{ overflowParts.fits }}</span><span class="rounded-sm bg-destructive/15 text-destructive">{{ overflowParts.overflow }}</span>
                </div>
                <textarea
                    v-model="content"
                    :placeholder="$t('posts.edit.caption_placeholder')"
                    class="relative block w-full resize-none border-0 bg-transparent p-0 font-sans text-base leading-[1.7] shadow-none outline-none placeholder:text-muted-foreground/50"
                    style="min-height: 280px; field-sizing: content;"
                />
            </div>

            <!-- Drag-drop overlay (full-bleed over the editor area) -->
            <div
                v-if="isDragging"
                class="pointer-events-none absolute -inset-6 z-10 flex flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-primary bg-primary/10 backdrop-blur-sm"
            >
                <IconCloudUpload class="size-10 text-primary" />
                <p class="text-base font-semibold text-primary">{{ $t('posts.edit.drag_drop') }}</p>
            </div>
        </div>

        <SignaturesModal ref="signaturesModal" :signatures="signatures" @select="appendSignature" />
        <MediaPickerDialog ref="mediaPickerDialog" @select="addMediaFromGallery" />
        <ImagePreviewDialog
            :images="previewImages"
            :index="previewIndex"
            @update:index="previewIndex = $event"
            @close="previewIndex = null"
        />
    </div>
</template>
