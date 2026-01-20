<script setup lang="ts">
import {
    IconX,
    IconPhoto,
    IconShare,
    IconUpload,
} from '@tabler/icons-vue';
import { computed } from 'vue';

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

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
    socialAccount: SocialAccount;
    content: string;
    media: MediaItem[];
    contentType?: string;
    contentTypeOptions?: ContentTypeOption[];
    meta?: Record<string, any>;
    platformData?: {
        boards?: PinterestBoard[];
    };
    charCount: number;
    maxLength: number;
    isValid: boolean;
    validationMessage: string;
    isUploading?: boolean;
    readonly?: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:content': [value: string];
    'update:contentType': [value: string];
    'update:meta': [value: Record<string, any>];
    'upload': [event: Event];
    'remove-media': [mediaId: string];
}>();

const isCarousel = computed(() => props.contentType === 'pinterest_carousel');
const isVideoPin = computed(() => props.contentType === 'pinterest_video_pin');

const boards = computed(() => props.platformData?.boards || []);
const hasMultipleContentTypes = computed(() => (props.contentTypeOptions?.length || 0) > 1);

const updateBoardId = (boardId: string) => {
    emit('update:meta', { ...props.meta, board_id: boardId });
};
</script>

<template>
    <div class="space-y-4">
        <!-- Settings Bar -->
        <div v-if="(hasMultipleContentTypes || boards.length > 0) && !props.readonly" class="flex items-center justify-center gap-4 flex-wrap">
            <!-- Content Type Selector -->
            <div v-if="hasMultipleContentTypes" class="flex items-center gap-2">
                <span class="text-sm text-muted-foreground">Type:</span>
                <Select
                    :model-value="contentType"
                    @update:model-value="emit('update:contentType', $event)"
                >
                    <SelectTrigger class="w-[140px] h-8">
                        <SelectValue placeholder="Select type" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in contentTypeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Board Selector -->
            <div v-if="boards.length > 0" class="flex items-center gap-2">
                <span class="text-sm text-muted-foreground">Board:</span>
                <Select
                    :model-value="meta?.board_id || ''"
                    @update:model-value="updateBoardId"
                >
                    <SelectTrigger class="w-[160px] h-8">
                        <SelectValue placeholder="Select board" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="board in boards"
                            :key="board.id"
                            :value="board.id"
                        >
                            {{ board.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- No Boards Warning -->
            <div v-else class="text-sm text-amber-600 dark:text-amber-400">
                No boards found. Create a board on Pinterest first.
            </div>
        </div>

        <!-- Pinterest Card Preview -->
        <div class="bg-white dark:bg-[#1e1e1e] rounded-2xl overflow-hidden border border-gray-200 dark:border-gray-700 max-w-[350px] mx-auto shadow-sm">
            <!-- Pin Image/Video Area -->
            <div class="relative">
                <!-- Media Display -->
                <div v-if="media.length > 0" class="relative">
                    <!-- Single Image or Video -->
                    <div v-if="!isCarousel || media.length === 1" class="relative">
                        <img
                            v-if="media[0].type === 'image'"
                            :src="media[0].url"
                            :alt="media[0].original_filename"
                            class="w-full aspect-[2/3] object-cover"
                        />
                        <video
                            v-else
                            :src="media[0].url"
                            class="w-full aspect-[2/3] object-cover bg-black"
                            muted
                            loop
                            playsinline
                        />
                        <!-- Remove button -->
                        <button
                            v-if="!props.readonly"
                            type="button"
                            @click="emit('remove-media', media[0].id)"
                            class="absolute top-3 right-3 bg-black/60 text-white rounded-full p-1.5 opacity-0 hover:opacity-100 transition-opacity"
                        >
                            <IconX class="h-4 w-4" />
                        </button>
                        <!-- Video indicator -->
                        <div v-if="media[0].type === 'video'" class="absolute bottom-3 left-3 bg-black/60 text-white text-xs px-2 py-1 rounded-full flex items-center gap-1">
                            <IconUpload class="h-3 w-3" />
                            Video
                        </div>
                    </div>

                    <!-- Carousel -->
                    <div v-else class="relative">
                        <div class="flex overflow-x-auto snap-x snap-mandatory scrollbar-hide">
                            <div
                                v-for="item in media"
                                :key="item.id"
                                class="relative flex-shrink-0 w-full snap-center"
                            >
                                <img
                                    :src="item.url"
                                    :alt="item.original_filename"
                                    class="w-full aspect-[2/3] object-cover"
                                />
                                <button
                                    v-if="!props.readonly"
                                    type="button"
                                    @click="emit('remove-media', item.id)"
                                    class="absolute top-3 right-3 bg-black/60 text-white rounded-full p-1.5 opacity-0 hover:opacity-100 transition-opacity"
                                >
                                    <IconX class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <!-- Carousel indicator -->
                        <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5">
                            <div
                                v-for="(_, index) in media"
                                :key="index"
                                class="w-1.5 h-1.5 rounded-full"
                                :class="index === 0 ? 'bg-white' : 'bg-white/50'"
                            />
                        </div>
                    </div>

                    <!-- Pin Actions Overlay -->
                    <div class="absolute top-3 left-3 right-3 flex justify-between opacity-0 hover:opacity-100 transition-opacity">
                        <button class="bg-black/60 text-white rounded-full p-2">
                            <IconShare class="h-4 w-4" />
                        </button>
                        <button class="bg-[#e60023] text-white rounded-full px-4 py-2 font-semibold text-sm hover:bg-[#ad081b] transition-colors">
                            Save
                        </button>
                    </div>
                </div>

                <!-- Empty State - No Media -->
                <div v-else-if="!props.readonly" class="aspect-[2/3] bg-gray-100 dark:bg-gray-800 flex flex-col items-center justify-center">
                    <label class="cursor-pointer flex flex-col items-center gap-3 p-6 text-center">
                        <div class="p-4 bg-gray-200 dark:bg-gray-700 rounded-full">
                            <IconPhoto class="h-8 w-8 text-gray-500 dark:text-gray-400" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ isVideoPin ? 'Add a video' : 'Add an image' }}
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ isCarousel ? '2-5 images for carousel' : 'Recommended: 2:3 aspect ratio' }}
                            </p>
                        </div>
                        <input
                            type="file"
                            :accept="isVideoPin ? 'video/*' : 'image/*'"
                            :multiple="isCarousel"
                            class="hidden"
                            @change="emit('upload', $event)"
                            :disabled="isUploading"
                        />
                    </label>
                </div>
                <div v-else class="aspect-[2/3] bg-gray-100 dark:bg-gray-800 flex flex-col items-center justify-center">
                    <div class="p-4 bg-gray-200 dark:bg-gray-700 rounded-full">
                        <IconPhoto class="h-8 w-8 text-gray-500 dark:text-gray-400" />
                    </div>
                    <p class="text-sm text-gray-500 mt-3">No media</p>
                </div>
            </div>

            <!-- Pin Content -->
            <div class="p-4">
                <!-- Description -->
                <div v-if="props.readonly" class="w-full min-h-[60px] text-sm text-gray-900 dark:text-white whitespace-pre-wrap">
                    {{ content || 'No content' }}
                </div>
                <textarea
                    v-else
                    :value="content"
                    @input="emit('update:content', ($event.target as HTMLTextAreaElement).value)"
                    class="w-full min-h-[60px] bg-transparent border-0 p-0 text-sm text-gray-900 dark:text-white resize-none focus:outline-none focus:ring-0 placeholder:text-gray-400"
                    placeholder="Add a description..."
                />

                <!-- User Info -->
                <div class="flex items-center gap-2 mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                    <img
                        v-if="socialAccount.avatar_url"
                        :src="socialAccount.avatar_url"
                        :alt="socialAccount.display_name"
                        class="h-8 w-8 rounded-full object-cover"
                    />
                    <div v-else class="h-8 w-8 rounded-full bg-[#e60023] flex items-center justify-center text-white font-bold text-sm">
                        {{ socialAccount.display_name?.charAt(0) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                            {{ socialAccount.display_name }}
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ socialAccount.username ? `@${socialAccount.username}` : 'Pinterest' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer with upload and char count (only in edit mode) -->
            <div v-if="!props.readonly" class="border-t border-gray-200 dark:border-gray-700 px-4 py-3 flex items-center justify-between bg-gray-50 dark:bg-gray-800/50">
                <div class="flex items-center gap-2">
                    <span
                        class="text-xs px-2 py-1 rounded-full"
                        :class="isValid ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'"
                    >
                        {{ validationMessage }}
                    </span>
                </div>
                <label v-if="media.length > 0 && (isCarousel && media.length < 5)" class="cursor-pointer p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                    <input
                        type="file"
                        accept="image/*"
                        multiple
                        class="hidden"
                        @change="emit('upload', $event)"
                        :disabled="isUploading"
                    />
                    <IconPhoto class="h-5 w-5 text-gray-500" />
                </label>
            </div>
        </div>
    </div>
</template>
