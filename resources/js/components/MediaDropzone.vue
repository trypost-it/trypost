<script setup lang="ts">
import { IconCloudUpload, IconPhoto, IconVideo, IconX, IconLoader2, IconPlus } from '@tabler/icons-vue';
import { computed, ref } from 'vue';

interface MediaItem {
    id: string;
    url: string;
    type: string;
    original_filename: string;
}

interface Props {
    media: MediaItem[];
    maxFiles: number;
    minFiles?: number;
    acceptImages: boolean;
    acceptVideos: boolean;
    isUploading?: boolean;
    disabled?: boolean;
    aspectRatio?: string;
    variant?: 'default' | 'compact' | 'vertical';
    showGrid?: boolean;
    emptyStateText?: string;
    emptyStateSubtext?: string;
}

const props = withDefaults(defineProps<Props>(), {
    minFiles: 0,
    isUploading: false,
    disabled: false,
    aspectRatio: 'aspect-square',
    variant: 'default',
    showGrid: true,
    emptyStateText: 'Drag & drop or click to upload',
    emptyStateSubtext: '',
});

const emit = defineEmits<{
    upload: [files: File[]];
    remove: [mediaId: string];
}>();

const isDragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const acceptMimeTypes = computed(() => {
    const types: string[] = [];
    if (props.acceptImages) {
        types.push('image/*');
    }
    if (props.acceptVideos) {
        types.push('video/*');
    }
    return types.join(',');
});

const acceptDescription = computed(() => {
    if (props.acceptImages && props.acceptVideos) {
        return props.emptyStateSubtext || 'Images or videos';
    }
    if (props.acceptImages) {
        return props.emptyStateSubtext || 'Images only';
    }
    if (props.acceptVideos) {
        return props.emptyStateSubtext || 'Videos only';
    }
    return 'No media allowed';
});

const canAddMore = computed(() => props.media.length < props.maxFiles);
const isMultiple = computed(() => props.maxFiles > 1);

const isValidFileType = (file: File): boolean => {
    const isImage = file.type.startsWith('image/');
    const isVideo = file.type.startsWith('video/');

    if (isImage && !props.acceptImages) {
        return false;
    }
    if (isVideo && !props.acceptVideos) {
        return false;
    }
    return isImage || isVideo;
};

const handleDragOver = (e: DragEvent) => {
    e.preventDefault();
    if (!props.disabled && canAddMore.value) {
        isDragging.value = true;
    }
};

const handleDragLeave = (e: DragEvent) => {
    e.preventDefault();
    isDragging.value = false;
};

const handleDrop = (e: DragEvent) => {
    e.preventDefault();
    isDragging.value = false;

    if (props.disabled || !canAddMore.value || !e.dataTransfer?.files) {
        return;
    }

    const files = Array.from(e.dataTransfer.files);
    processFiles(files);
};

const handleFileSelect = (e: Event) => {
    const input = e.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) {
        return;
    }

    const files = Array.from(input.files);
    processFiles(files);

    // Reset input
    input.value = '';
};

const processFiles = (files: File[]) => {
    // Filter valid files
    const validFiles = files.filter(isValidFileType);

    if (validFiles.length === 0) {
        return;
    }

    // Limit to remaining slots
    const remainingSlots = props.maxFiles - props.media.length;
    const filesToUpload = validFiles.slice(0, remainingSlots);

    emit('upload', filesToUpload);
};

const openFilePicker = () => {
    if (!props.disabled && canAddMore.value) {
        fileInput.value?.click();
    }
};

const handleRemove = (mediaId: string) => {
    emit('remove', mediaId);
};
</script>

<template>
    <div
        class="relative"
        @dragover="handleDragOver"
        @dragleave="handleDragLeave"
        @drop="handleDrop"
    >
        <!-- Empty state: dropzone -->
        <div
            v-if="media.length === 0"
            class="flex flex-col items-center justify-center cursor-pointer transition-all duration-200"
            :class="[
                aspectRatio,
                isDragging
                    ? 'bg-primary/5 border-primary border-2 border-dashed'
                    : 'bg-muted/50 hover:bg-muted/70 border-2 border-dashed border-muted-foreground/20 hover:border-muted-foreground/40',
                variant === 'vertical' ? 'rounded-2xl' : 'rounded-lg',
                disabled ? 'opacity-50 cursor-not-allowed' : '',
            ]"
            @click="openFilePicker"
        >
            <div v-if="isUploading" class="flex flex-col items-center gap-3">
                <div class="p-4 rounded-full bg-muted">
                    <IconLoader2 class="h-8 w-8 text-muted-foreground animate-spin" />
                </div>
                <p class="text-sm text-muted-foreground">Uploading...</p>
            </div>
            <div v-else class="flex flex-col items-center gap-3">
                <div class="p-4 rounded-full bg-muted">
                    <IconCloudUpload v-if="isDragging" class="h-8 w-8 text-primary" />
                    <IconVideo v-else-if="acceptVideos && !acceptImages" class="h-8 w-8 text-muted-foreground" />
                    <IconPhoto v-else class="h-8 w-8 text-muted-foreground" />
                </div>
                <div class="text-center">
                    <p class="text-sm font-medium text-muted-foreground">{{ emptyStateText }}</p>
                    <p class="text-xs text-muted-foreground/70 mt-1">{{ acceptDescription }}</p>
                    <p v-if="maxFiles > 1" class="text-xs text-muted-foreground/50 mt-1">
                        Up to {{ maxFiles }} {{ maxFiles === 1 ? 'file' : 'files' }}
                        <span v-if="minFiles && minFiles > 1">(min {{ minFiles }})</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Media grid with thumbnails -->
        <div v-else>
            <div
                v-if="showGrid && media.length > 1"
                class="grid gap-2"
                :class="media.length === 2 ? 'grid-cols-2' : 'grid-cols-3'"
            >
                <div
                    v-for="item in media"
                    :key="item.id"
                    class="relative group aspect-square rounded-lg overflow-hidden bg-muted"
                >
                    <img
                        v-if="item.type === 'image'"
                        :src="item.url"
                        :alt="item.original_filename"
                        class="w-full h-full object-cover"
                    />
                    <video
                        v-else
                        :src="item.url"
                        class="w-full h-full object-cover bg-black"
                        muted
                        playsinline
                    />
                    <!-- Video indicator -->
                    <div
                        v-if="item.type === 'video'"
                        class="absolute bottom-1 left-1 bg-black/60 text-white text-[10px] px-1.5 py-0.5 rounded flex items-center gap-0.5"
                    >
                        <IconVideo class="h-3 w-3" />
                    </div>
                    <!-- Remove button -->
                    <button
                        v-if="!disabled"
                        type="button"
                        @click.stop="handleRemove(item.id)"
                        class="absolute top-1 right-1 bg-black/60 hover:bg-black/80 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity"
                    >
                        <IconX class="h-3.5 w-3.5" />
                    </button>
                </div>
                <!-- Add more button -->
                <button
                    v-if="canAddMore && !disabled"
                    type="button"
                    @click="openFilePicker"
                    class="aspect-square rounded-lg border-2 border-dashed border-muted-foreground/20 hover:border-muted-foreground/40 flex items-center justify-center transition-colors"
                    :class="isUploading ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:bg-muted/50'"
                >
                    <IconLoader2 v-if="isUploading" class="h-6 w-6 text-muted-foreground animate-spin" />
                    <IconPlus v-else class="h-6 w-6 text-muted-foreground" />
                </button>
            </div>

            <!-- Single media display -->
            <div
                v-else
                class="relative group overflow-hidden bg-muted"
                :class="[aspectRatio, variant === 'vertical' ? 'rounded-2xl' : 'rounded-lg']"
            >
                <img
                    v-if="media[0].type === 'image'"
                    :src="media[0].url"
                    :alt="media[0].original_filename"
                    class="w-full h-full object-cover"
                />
                <video
                    v-else
                    :src="media[0].url"
                    class="w-full h-full object-cover bg-black"
                    muted
                    loop
                    playsinline
                />
                <!-- Video indicator -->
                <div
                    v-if="media[0].type === 'video'"
                    class="absolute bottom-2 left-2 bg-black/60 text-white text-xs px-2 py-1 rounded-full flex items-center gap-1"
                >
                    <IconVideo class="h-3.5 w-3.5" />
                    <span>Video</span>
                </div>
                <!-- Remove button -->
                <button
                    v-if="!disabled"
                    type="button"
                    @click.stop="handleRemove(media[0].id)"
                    class="absolute top-2 right-2 bg-black/60 hover:bg-black/80 text-white rounded-full p-2 opacity-0 group-hover:opacity-100 transition-opacity"
                >
                    <IconX class="h-4 w-4" />
                </button>
                <!-- Carousel indicator (if more than 1 and not showing grid) -->
                <div v-if="media.length > 1 && !showGrid" class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1">
                    <div
                        v-for="(_, index) in media.slice(0, 5)"
                        :key="index"
                        class="w-1.5 h-1.5 rounded-full"
                        :class="index === 0 ? 'bg-white' : 'bg-white/50'"
                    />
                    <span v-if="media.length > 5" class="text-white text-[10px] ml-1">+{{ media.length - 5 }}</span>
                </div>
                <!-- Upload more overlay (if can add more and single display) -->
                <button
                    v-if="canAddMore && !disabled && !showGrid"
                    type="button"
                    @click="openFilePicker"
                    class="absolute bottom-2 right-2 bg-black/60 hover:bg-black/80 text-white rounded-full p-2 opacity-0 group-hover:opacity-100 transition-opacity"
                >
                    <IconPlus class="h-4 w-4" />
                </button>
            </div>
        </div>

        <!-- Hidden file input -->
        <input
            ref="fileInput"
            type="file"
            :accept="acceptMimeTypes"
            :multiple="isMultiple"
            class="hidden"
            @change="handleFileSelect"
            :disabled="disabled || isUploading"
        />
    </div>
</template>
