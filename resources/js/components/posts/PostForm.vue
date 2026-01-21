<script setup lang="ts">
import {
    IconPhoto,
    IconVideo,
    IconX,
    IconPlus,
    IconCloudUpload,
    IconLoader2,
    IconGripVertical,
    IconCheck,
    IconChevronDown,
    IconSearch,
} from '@tabler/icons-vue';
import { FocusScope } from 'reka-ui';
import { computed, ref } from 'vue';

import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxList,
    ComboboxTrigger,
} from '@/components/ui/combobox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useMediaRules } from '@/composables/useMediaRules';

interface MediaItem {
    id: string;
    group_id: string | null;
    url: string;
    type: string;
    original_filename: string;
}

interface ContentTypeOption {
    value: string;
    label: string;
    description: string;
}

interface PinterestBoard {
    id: string;
    name: string;
}

interface Props {
    platform: string;
    content: string;
    media: MediaItem[];
    contentType: string;
    contentTypeOptions: ContentTypeOption[];
    meta?: Record<string, any>;
    platformData?: {
        boards?: PinterestBoard[];
    };
    charCount: number;
    maxLength: number;
    isValid: boolean;
    validationMessage: string;
    isUploading?: boolean;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    isUploading: false,
    disabled: false,
});

const emit = defineEmits<{
    'update:content': [value: string];
    'update:contentType': [value: string];
    'update:meta': [value: Record<string, any>];
    'upload': [files: File[]];
    'remove-media': [mediaId: string];
    'reorder-media': [mediaIds: string[]];
}>();

// Media rules
const contentTypeRef = computed(() => props.contentType);
const { rules: mediaRules } = useMediaRules(contentTypeRef);

// File drag and drop (for uploading)
const isDraggingFile = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

// Media reorder drag and drop
const draggedItemId = ref<string | null>(null);
const dragOverItemId = ref<string | null>(null);

// Pinterest board selection
interface BoardOption {
    value: string;
    label: string;
}

const boards = computed(() => props.platformData?.boards || []);
const boardOptions = computed<BoardOption[]>(() =>
    boards.value.map(board => ({
        value: board.id,
        label: board.name,
    }))
);

const selectedBoard = computed({
    get: () => boardOptions.value.find(b => b.value === props.meta?.board_id),
    set: (board: BoardOption | undefined) => {
        emit('update:meta', { ...props.meta, board_id: board?.value || null });
    },
});

// Computed
const hasMultipleContentTypes = computed(() => props.contentTypeOptions.length > 1);
const canAddMore = computed(() => props.media.length < mediaRules.value.maxFiles);
const acceptString = computed(() => {
    if (mediaRules.value.acceptImages && mediaRules.value.acceptVideos) return 'image/*,video/*';
    if (mediaRules.value.acceptImages) return 'image/*';
    return 'video/*';
});

// File validation
const isValidFileType = (file: File): boolean => {
    const isImage = file.type.startsWith('image/');
    const isVideo = file.type.startsWith('video/');
    if (isImage && !mediaRules.value.acceptImages) return false;
    if (isVideo && !mediaRules.value.acceptVideos) return false;
    return isImage || isVideo;
};

// File upload handlers
const handleFileDragOver = (e: DragEvent) => {
    e.preventDefault();
    // Only handle if dragging files (not internal reorder)
    if (e.dataTransfer?.types.includes('Files') && !props.disabled && !props.isUploading && canAddMore.value) {
        isDraggingFile.value = true;
    }
};

const handleFileDragLeave = (e: DragEvent) => {
    e.preventDefault();
    isDraggingFile.value = false;
};

const handleFileDrop = (e: DragEvent) => {
    e.preventDefault();
    isDraggingFile.value = false;

    if (props.disabled || props.isUploading || !e.dataTransfer?.files) return;

    const files = Array.from(e.dataTransfer.files).filter(isValidFileType);
    if (files.length === 0) return;

    const remainingSlots = mediaRules.value.maxFiles - props.media.length;
    const filesToUpload = files.slice(0, remainingSlots);

    if (filesToUpload.length > 0) {
        emit('upload', filesToUpload);
    }
};

const handleFileSelect = (e: Event) => {
    const input = e.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) return;

    const files = Array.from(input.files).filter(isValidFileType);
    const remainingSlots = mediaRules.value.maxFiles - props.media.length;
    const filesToUpload = files.slice(0, remainingSlots);

    if (filesToUpload.length > 0) {
        emit('upload', filesToUpload);
    }
    input.value = '';
};

const openFilePicker = () => {
    if (!props.disabled && !props.isUploading && canAddMore.value) {
        fileInput.value?.click();
    }
};

// Media reorder handlers
const handleDragStart = (e: DragEvent, itemId: string) => {
    if (props.disabled) return;
    draggedItemId.value = itemId;
    if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', itemId);
    }
};

const handleDragEnd = () => {
    draggedItemId.value = null;
    dragOverItemId.value = null;
};

const handleDragOverItem = (e: DragEvent, itemId: string) => {
    e.preventDefault();
    if (draggedItemId.value && draggedItemId.value !== itemId) {
        dragOverItemId.value = itemId;
        if (e.dataTransfer) {
            e.dataTransfer.dropEffect = 'move';
        }
    }
};

const handleDragLeaveItem = () => {
    dragOverItemId.value = null;
};

const handleDropOnItem = (e: DragEvent, targetId: string) => {
    e.preventDefault();

    if (!draggedItemId.value || draggedItemId.value === targetId) {
        handleDragEnd();
        return;
    }

    // Calculate new order
    const currentIds = props.media.map(m => m.id);
    const draggedIndex = currentIds.indexOf(draggedItemId.value);
    const targetIndex = currentIds.indexOf(targetId);

    if (draggedIndex === -1 || targetIndex === -1) {
        handleDragEnd();
        return;
    }

    // Create new order by moving dragged item to target position
    const newIds = [...currentIds];
    newIds.splice(draggedIndex, 1);
    newIds.splice(targetIndex, 0, draggedItemId.value);

    emit('reorder-media', newIds);
    handleDragEnd();
};
</script>

<template>
    <div class="space-y-6">
        <!-- Content Type Selector -->
        <div v-if="hasMultipleContentTypes">
            <Label class="text-sm font-medium mb-2 block">Post Type</Label>
            <Select :model-value="contentType" @update:model-value="emit('update:contentType', $event)">
                <SelectTrigger class="w-full">
                    <SelectValue placeholder="Select type" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="option in contentTypeOptions" :key="option.value" :value="option.value">
                        <div class="flex flex-col">
                            <span>{{ option.label }}</span>
                            <span class="text-xs text-muted-foreground">{{ option.description }}</span>
                        </div>
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- Pinterest Board Selector -->
        <div v-if="platform === 'pinterest' && boards.length > 0">
            <Label class="text-sm font-medium mb-2 block">Board</Label>
            <FocusScope as-child>
                <Combobox v-model="selectedBoard">
                    <ComboboxAnchor as-child>
                        <ComboboxTrigger as-child>
                            <button type="button"
                                class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                {{ selectedBoard ? selectedBoard.label : 'Select a board' }}
                                <IconChevronDown class="h-4 w-4 opacity-50" />
                            </button>
                        </ComboboxTrigger>
                    </ComboboxAnchor>
                    <ComboboxList class="w-[var(--reka-combobox-trigger-width)]">
                        <div class="relative">
                            <ComboboxInput placeholder="Search board..." class="pl-9" />
                            <span class="absolute inset-y-0 start-0 flex items-center justify-center px-3">
                                <IconSearch class="size-4 text-muted-foreground" />
                            </span>
                        </div>
                        <ComboboxEmpty>No board found</ComboboxEmpty>
                        <ComboboxGroup>
                            <ComboboxItem v-for="board in boardOptions" :key="board.value" :value="board">
                                <span class="min-w-0 flex-1 truncate">{{ board.label }}</span>
                                <ComboboxItemIndicator>
                                    <IconCheck class="ml-auto h-4 w-4" />
                                </ComboboxItemIndicator>
                            </ComboboxItem>
                        </ComboboxGroup>
                    </ComboboxList>
                </Combobox>
            </FocusScope>
        </div>

        <!-- Media Upload Area -->
        <div>
            <Label class="text-sm font-medium mb-2 block">
                Media
                <span class="text-muted-foreground font-normal ml-1">
                    ({{ props.media.length }}/{{ mediaRules.maxFiles }})
                </span>
                <span v-if="mediaRules.minFiles && mediaRules.minFiles > 1" class="text-muted-foreground font-normal">
                    · Min {{ mediaRules.minFiles }}
                </span>
            </Label>

            <div class="border-2 border-dashed rounded-lg transition-colors" :class="[
                isDraggingFile ? 'border-primary bg-primary/5' : 'border-muted-foreground/25 hover:border-muted-foreground/40',
                props.disabled ? 'opacity-50 cursor-not-allowed' : '',
            ]" @dragover="handleFileDragOver" @dragleave="handleFileDragLeave" @drop="handleFileDrop">
                <!-- Empty State -->
                <div v-if="media.length === 0" class="p-8 flex flex-col items-center justify-center cursor-pointer"
                    @click="openFilePicker">
                    <div v-if="isUploading" class="flex flex-col items-center gap-3">
                        <IconLoader2 class="h-10 w-10 text-muted-foreground animate-spin" />
                        <p class="text-sm text-muted-foreground">Uploading...</p>
                    </div>
                    <div v-else class="flex flex-col items-center gap-3">
                        <div class="p-4 rounded-full transition-colors"
                            :class="isDraggingFile ? 'bg-primary/10' : 'bg-muted'">
                            <IconCloudUpload v-if="isDraggingFile" class="h-8 w-8 text-primary" />
                            <IconVideo v-else-if="!mediaRules.acceptImages" class="h-8 w-8 text-muted-foreground" />
                            <IconPhoto v-else class="h-8 w-8 text-muted-foreground" />
                        </div>
                        <div class="text-center">
                            <p class="text-sm font-medium">
                                {{ isDraggingFile ? 'Drop to upload' : 'Drag & drop or click to upload' }}
                            </p>
                            <p class="text-xs text-muted-foreground mt-1">
                                {{ mediaRules.acceptImages && mediaRules.acceptVideos ? 'Photos and videos' :
                                    (mediaRules.acceptImages ? 'Photos only' : 'Videos only') }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Media Grid -->
                <div v-else class="p-3">
                    <div class="grid grid-cols-3 gap-2">
                        <!-- Media Items -->
                        <div v-for="(item, index) in media" :key="item.id" draggable="true"
                            @dragstart="handleDragStart($event, item.id)" @dragend="handleDragEnd"
                            @dragover="handleDragOverItem($event, item.id)" @dragleave="handleDragLeaveItem"
                            @drop="handleDropOnItem($event, item.id)"
                            class="relative aspect-square rounded-lg overflow-hidden bg-muted group transition-all"
                            :class="[
                                draggedItemId === item.id ? 'opacity-50 scale-95' : '',
                                dragOverItemId === item.id ? 'ring-2 ring-primary ring-offset-2' : '',
                                !props.disabled ? 'cursor-grab active:cursor-grabbing' : '',
                            ]">
                            <img v-if="item.type === 'image'" :src="item.url" :alt="item.original_filename"
                                class="w-full h-full object-cover pointer-events-none" />
                            <video v-else :src="item.url"
                                class="w-full h-full object-cover bg-black pointer-events-none" muted playsinline />
                            <!-- Order number -->
                            <div v-if="media.length > 1"
                                class="absolute top-1 left-1 bg-black/70 text-white text-[10px] font-bold w-5 h-5 rounded flex items-center justify-center">
                                {{ index + 1 }}
                            </div>
                            <!-- Video indicator -->
                            <div v-if="item.type === 'video'"
                                class="absolute bottom-1 left-1 bg-black/70 text-white text-[10px] px-1.5 py-0.5 rounded flex items-center gap-0.5">
                                <IconVideo class="h-3 w-3" />
                            </div>
                            <!-- Remove button -->
                            <button v-if="!props.disabled" type="button" @click.stop="emit('remove-media', item.id)"
                                class="absolute top-1 right-1 bg-black/70 hover:bg-black/90 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <IconX class="h-3.5 w-3.5" />
                            </button>
                            <!-- Drag hint -->
                            <div v-if="media.length > 1 && !props.disabled"
                                class="absolute bottom-1 right-1 bg-black/50 text-white rounded p-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                <IconGripVertical class="h-3.5 w-3.5" />
                            </div>
                        </div>

                        <!-- Add More Button -->
                        <button v-if="canAddMore && !props.disabled" type="button" @click="openFilePicker"
                            class="aspect-square rounded-lg border-2 border-dashed border-muted-foreground/25 hover:border-muted-foreground/40 flex items-center justify-center transition-colors"
                            :class="isUploading ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:bg-muted/50'">
                            <IconLoader2 v-if="isUploading" class="h-6 w-6 text-muted-foreground animate-spin" />
                            <IconPlus v-else class="h-6 w-6 text-muted-foreground" />
                        </button>
                    </div>
                    <p v-if="media.length > 1" class="text-xs text-muted-foreground mt-2 text-center">
                        Drag to reorder
                    </p>
                </div>
            </div>

            <!-- Hidden file input -->
            <input ref="fileInput" type="file" :accept="acceptString" :multiple="mediaRules.maxFiles > 1" class="hidden"
                @change="handleFileSelect" :disabled="props.disabled || props.isUploading" />
        </div>

        <!-- Caption/Text Input -->
        <div>
            <div class="flex items-center justify-between mb-2">
                <Label class="text-sm font-medium">Caption</Label>
                <span class="text-xs px-2 py-0.5 rounded-full"
                    :class="isValid ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'">
                    {{ validationMessage }}
                </span>
            </div>
            <Textarea :model-value="content" @update:model-value="emit('update:content', $event)"
                placeholder="Write your caption..." class="min-h-[120px] resize-none" :disabled="props.disabled" />
        </div>
    </div>
</template>
33