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
    IconChevronUp,
} from '@tabler/icons-vue';
import { FocusScope } from 'reka-ui';
import { computed, ref } from 'vue';

import { Checkbox } from '@/components/ui/checkbox';
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
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

// TikTok settings
const isTikTok = computed(() => props.platform === 'tiktok');
const tiktokSettingsOpen = ref(true);

const tiktokPrivacyLevel = computed({
    get: () => props.meta?.privacy_level || 'SELF_ONLY',
    set: (value: string) => {
        emit('update:meta', { ...props.meta, privacy_level: value });
    },
});

const tiktokAllowComments = computed({
    get: () => props.meta?.allow_comments ?? true,
    set: (value: boolean) => {
        emit('update:meta', { ...props.meta, allow_comments: value });
    },
});

const tiktokAllowDuet = computed({
    get: () => props.meta?.allow_duet ?? false,
    set: (value: boolean) => {
        emit('update:meta', { ...props.meta, allow_duet: value });
    },
});

const tiktokAllowStitch = computed({
    get: () => props.meta?.allow_stitch ?? false,
    set: (value: boolean) => {
        emit('update:meta', { ...props.meta, allow_stitch: value });
    },
});

const tiktokAutoAddMusic = computed({
    get: () => props.meta?.auto_add_music ?? false,
    set: (value: boolean) => {
        emit('update:meta', { ...props.meta, auto_add_music: value });
    },
});

const tiktokIsAigc = computed({
    get: () => props.meta?.is_aigc ?? false,
    set: (value: boolean) => {
        emit('update:meta', { ...props.meta, is_aigc: value });
    },
});

const tiktokBrandContentToggle = computed({
    get: () => props.meta?.brand_content_toggle ?? false,
    set: (value: boolean) => {
        emit('update:meta', { ...props.meta, brand_content_toggle: value });
    },
});

const tiktokBrandOrganicToggle = computed({
    get: () => props.meta?.brand_organic_toggle ?? false,
    set: (value: boolean) => {
        emit('update:meta', { ...props.meta, brand_organic_toggle: value });
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
        <!-- Content Type Selector (Tabs Style) -->
        <div v-if="hasMultipleContentTypes">
            <Label class="text-sm font-medium mb-2 block">{{ $t('posts.form.post_type') }}</Label>
            <div
                class="bg-muted text-muted-foreground inline-flex h-10 w-auto items-center justify-start rounded-lg p-1">
                <button v-for="option in contentTypeOptions" :key="option.value" type="button"
                    @click="emit('update:contentType', option.value)" :class="[
                        'inline-flex items-center justify-center whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium transition-all',
                        contentType === option.value
                            ? 'bg-background text-foreground shadow-sm'
                            : 'hover:bg-background/50 hover:text-foreground'
                    ]">
                    {{ option.label }}
                </button>
            </div>
        </div>

        <!-- Pinterest Board Selector -->
        <div v-if="platform === 'pinterest' && boards.length > 0">
            <Label class="text-sm font-medium mb-2 block">{{ $t('posts.form.board') }}</Label>
            <FocusScope as-child>
                <Combobox v-model="selectedBoard">
                    <ComboboxAnchor as-child>
                        <ComboboxTrigger as-child>
                            <button type="button"
                                class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                {{ selectedBoard ? selectedBoard.label : $t('posts.form.select_board') }}
                                <IconChevronDown class="h-4 w-4 opacity-50" />
                            </button>
                        </ComboboxTrigger>
                    </ComboboxAnchor>
                    <ComboboxList class="w-[var(--reka-combobox-trigger-width)]">
                        <div class="relative">
                            <ComboboxInput :placeholder="$t('posts.form.search_board')" />
                            <span class="absolute inset-y-0 start-0 flex items-center justify-center px-3">
                                <IconSearch class="size-4 text-muted-foreground" />
                            </span>
                        </div>
                        <ComboboxEmpty>{{ $t('posts.form.no_board_found') }}</ComboboxEmpty>
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
                {{ $t('posts.form.media') }}
                <span class="text-muted-foreground font-normal ml-1">
                    ({{ props.media.length }}/{{ mediaRules.maxFiles }})
                </span>
                <span v-if="mediaRules.minFiles && mediaRules.minFiles > 1" class="text-muted-foreground font-normal">
                    · {{ $t('posts.form.min') }} {{ mediaRules.minFiles }}
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
                        <p class="text-sm text-muted-foreground">{{ $t('posts.form.uploading') }}</p>
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
                                {{ isDraggingFile ? $t('posts.form.drop_to_upload') : $t('posts.form.drag_and_drop') }}
                            </p>
                            <p class="text-xs text-muted-foreground mt-1">
                                {{ mediaRules.acceptImages && mediaRules.acceptVideos ? $t('posts.form.photos_and_videos') :
                                    (mediaRules.acceptImages ? $t('posts.form.photos_only') : $t('posts.form.videos_only')) }}
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
                        {{ $t('posts.form.drag_to_reorder') }}
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
                <Label class="text-sm font-medium">{{ $t('posts.form.caption') }}</Label>
                <span class="text-xs px-2 py-0.5 rounded-full"
                    :class="isValid ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'">
                    {{ validationMessage }}
                </span>
            </div>
            <Textarea :model-value="content" @update:model-value="emit('update:content', $event as string)"
                :placeholder="$t('posts.form.write_caption')" class="min-h-[120px] resize-none" :disabled="props.disabled" />
        </div>

        <!-- TikTok Settings -->
        <div v-if="isTikTok" class="rounded-lg border">
            <button type="button"
                class="flex w-full items-center justify-between p-4 text-sm font-medium"
                @click="tiktokSettingsOpen = !tiktokSettingsOpen">
                {{ $t('posts.form.tiktok.settings') }}
                <IconChevronUp v-if="tiktokSettingsOpen" class="h-4 w-4 text-muted-foreground" />
                <IconChevronDown v-else class="h-4 w-4 text-muted-foreground" />
            </button>

            <div v-if="tiktokSettingsOpen" class="space-y-5 border-t px-4 pb-4 pt-4">
                <!-- Privacy Level -->
                <div class="space-y-2">
                    <Label class="text-sm font-medium">{{ $t('posts.form.tiktok.privacy_level') }}</Label>
                    <Select v-model="tiktokPrivacyLevel" :disabled="props.disabled">
                        <SelectTrigger class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="PUBLIC_TO_EVERYONE">{{ $t('posts.form.tiktok.privacy.public') }}</SelectItem>
                            <SelectItem value="MUTUAL_FOLLOW_FRIENDS">{{ $t('posts.form.tiktok.privacy.friends') }}</SelectItem>
                            <SelectItem value="FOLLOWER_OF_CREATOR">{{ $t('posts.form.tiktok.privacy.followers') }}</SelectItem>
                            <SelectItem value="SELF_ONLY">{{ $t('posts.form.tiktok.privacy.private') }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">{{ $t('posts.form.tiktok.privacy_hint') }}</p>
                </div>

                <!-- Auto Add Music (photos only) -->
                <div class="space-y-2">
                    <Label class="text-sm font-medium">{{ $t('posts.form.tiktok.auto_add_music') }}</Label>
                    <Select :model-value="tiktokAutoAddMusic ? 'yes' : 'no'" @update:model-value="tiktokAutoAddMusic = $event === 'yes'" :disabled="props.disabled">
                        <SelectTrigger class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="yes">{{ $t('posts.form.tiktok.yes') }}</SelectItem>
                            <SelectItem value="no">{{ $t('posts.form.tiktok.no') }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">{{ $t('posts.form.tiktok.auto_add_music_hint') }}</p>
                </div>

                <!-- Allow User To -->
                <div class="space-y-2">
                    <Label class="text-sm font-medium">{{ $t('posts.form.tiktok.allow_users') }}</Label>
                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox :checked="tiktokAllowComments" @update:checked="tiktokAllowComments = $event" :disabled="props.disabled" />
                            {{ $t('posts.form.tiktok.comments') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox :checked="tiktokAllowDuet" @update:checked="tiktokAllowDuet = $event" :disabled="props.disabled" />
                            {{ $t('posts.form.tiktok.duet') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox :checked="tiktokAllowStitch" @update:checked="tiktokAllowStitch = $event" :disabled="props.disabled" />
                            {{ $t('posts.form.tiktok.stitch') }}
                        </label>
                    </div>
                </div>

                <!-- Content Disclosure -->
                <div class="space-y-3">
                    <div>
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox :checked="tiktokIsAigc" @update:checked="tiktokIsAigc = $event" :disabled="props.disabled" />
                            {{ $t('posts.form.tiktok.is_aigc') }}
                        </label>
                    </div>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox :checked="tiktokBrandContentToggle" @update:checked="tiktokBrandContentToggle = $event" :disabled="props.disabled" />
                            {{ $t('posts.form.tiktok.brand_content') }}
                        </label>
                        <p v-if="tiktokBrandContentToggle" class="text-xs text-muted-foreground ml-6">{{ $t('posts.form.tiktok.brand_content_hint') }}</p>
                    </div>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox :checked="tiktokBrandOrganicToggle" @update:checked="tiktokBrandOrganicToggle = $event" :disabled="props.disabled" />
                            {{ $t('posts.form.tiktok.brand_organic') }}
                        </label>
                        <p v-if="tiktokBrandOrganicToggle" class="text-xs text-muted-foreground ml-6">{{ $t('posts.form.tiktok.brand_organic_hint') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
33