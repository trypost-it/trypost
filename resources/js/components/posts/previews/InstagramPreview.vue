<script setup lang="ts">
import {
    IconDots,
    IconVideo,
    IconX,
    IconPhoto,
    IconHeart,
    IconMessageCircle,
    IconSend,
    IconBookmark,
    IconMusic,
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

interface Props {
    socialAccount: SocialAccount;
    content: string;
    media: MediaItem[];
    contentType?: string;
    contentTypeOptions?: ContentTypeOption[];
    charCount: number;
    maxLength: number;
    isValid: boolean;
    validationMessage: string;
    isUploading?: boolean;
}

const props = defineProps<Props>();

// Computed helpers for content type
const isReel = computed(() => props.contentType === 'instagram_reel');
const isStory = computed(() => props.contentType === 'instagram_story');
const hasMultipleContentTypes = computed(() => (props.contentTypeOptions?.length || 0) > 1);

const emit = defineEmits<{
    'update:content': [value: string];
    'update:contentType': [value: string];
    'upload': [event: Event];
    'remove-media': [mediaId: string];
}>();
</script>

<template>
    <div class="space-y-4">
        <!-- Content Type Selector -->
        <div v-if="hasMultipleContentTypes" class="flex items-center justify-center gap-2">
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

        <!-- Reel/Story Preview (vertical) -->
        <div v-if="isReel || isStory" class="mx-auto" style="max-width: 320px;">
        <div class="relative bg-black rounded-2xl overflow-hidden" style="aspect-ratio: 9/16;">
            <!-- Media Area -->
            <div v-if="media.length > 0" class="w-full h-full">
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
                <!-- Remove button -->
                <button
                    type="button"
                    @click="emit('remove-media', media[0].id)"
                    class="absolute top-3 right-3 bg-black/70 text-white rounded-full p-2 z-20"
                >
                    <IconX class="h-4 w-4" />
                </button>
            </div>
            <div v-else class="w-full h-full flex flex-col items-center justify-center text-gray-400">
                <IconVideo class="h-16 w-16 mb-2" />
                <p class="text-sm">Add a {{ isReel ? 'video' : 'photo or video' }}</p>
                <label class="mt-2 cursor-pointer text-blue-500 font-medium text-sm hover:text-blue-600">
                    <input
                        type="file"
                        :accept="isReel ? 'video/*' : 'image/*,video/*'"
                        class="hidden"
                        @change="emit('upload', $event)"
                        :disabled="isUploading"
                    />
                    Upload
                </label>
            </div>

            <!-- Overlay UI -->
            <div class="absolute inset-0 pointer-events-none">
                <!-- Top bar -->
                <div class="absolute top-0 left-0 right-0 p-3 flex items-center justify-between">
                    <div v-if="isStory" class="flex items-center gap-2">
                        <div class="p-0.5 bg-gradient-to-tr from-yellow-400 via-red-500 to-purple-600 rounded-full">
                            <div class="p-0.5 bg-black rounded-full">
                                <img
                                    v-if="socialAccount.avatar_url"
                                    :src="socialAccount.avatar_url"
                                    :alt="socialAccount.display_name"
                                    class="h-8 w-8 rounded-full object-cover"
                                />
                                <div v-else class="h-8 w-8 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold text-sm">
                                    {{ socialAccount.display_name?.charAt(0) }}
                                </div>
                            </div>
                        </div>
                        <span class="text-white text-sm font-semibold">{{ socialAccount.username || socialAccount.display_name }}</span>
                    </div>
                    <div v-if="isReel" class="bg-black/50 text-white text-xs px-2 py-1 rounded flex items-center gap-1">
                        <IconVideo class="h-3 w-3" />
                        Reel
                    </div>
                </div>

                <!-- Right sidebar actions (Reels) -->
                <div v-if="isReel" class="absolute bottom-20 right-3 flex flex-col items-center gap-4">
                    <button class="flex flex-col items-center gap-1">
                        <IconHeart class="h-7 w-7 text-white drop-shadow" />
                        <span class="text-white text-xs drop-shadow">1.2K</span>
                    </button>
                    <button class="flex flex-col items-center gap-1">
                        <IconMessageCircle class="h-7 w-7 text-white drop-shadow" />
                        <span class="text-white text-xs drop-shadow">89</span>
                    </button>
                    <button class="flex flex-col items-center gap-1">
                        <IconSend class="h-7 w-7 text-white drop-shadow" />
                    </button>
                    <button class="flex flex-col items-center gap-1">
                        <IconDots class="h-7 w-7 text-white drop-shadow" />
                    </button>
                </div>

                <!-- Bottom content (Reels) -->
                <div v-if="isReel" class="absolute bottom-3 left-3 right-14">
                    <div class="flex items-center gap-2 mb-2">
                        <img
                            v-if="socialAccount.avatar_url"
                            :src="socialAccount.avatar_url"
                            :alt="socialAccount.display_name"
                            class="h-8 w-8 rounded-full object-cover border-2 border-white"
                        />
                        <div v-else class="h-8 w-8 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold text-sm border-2 border-white">
                            {{ socialAccount.display_name?.charAt(0) }}
                        </div>
                        <span class="text-white text-sm font-semibold drop-shadow">{{ socialAccount.username || socialAccount.display_name }}</span>
                        <button class="ml-1 px-3 py-1 bg-white/20 text-white text-xs font-semibold rounded">Follow</button>
                    </div>
                    <p v-if="content" class="text-white text-sm drop-shadow line-clamp-2">{{ content }}</p>
                    <div class="flex items-center gap-1 mt-2 text-white/80 text-xs">
                        <IconMusic class="h-3 w-3" />
                        <span class="truncate">Original audio</span>
                    </div>
                </div>

                <!-- Story reply bar -->
                <div v-if="isStory" class="absolute bottom-3 left-3 right-3">
                    <div class="flex items-center gap-2">
                        <div class="flex-1 bg-white/20 backdrop-blur rounded-full px-4 py-2 text-white/80 text-sm">
                            Send message
                        </div>
                        <IconHeart class="h-6 w-6 text-white" />
                        <IconSend class="h-6 w-6 text-white" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Caption input for Reels -->
        <div v-if="isReel" class="mt-4 p-4 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 block">Caption</label>
            <textarea
                :value="content"
                @input="emit('update:content', ($event.target as HTMLTextAreaElement).value)"
                class="w-full min-h-[80px] bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3 text-sm text-gray-900 dark:text-white resize-none focus:outline-none focus:ring-2 focus:ring-pink-500 placeholder:text-gray-500"
                placeholder="Write a caption..."
            />
            <div class="flex items-center justify-between mt-2">
                <span
                    class="text-xs px-2 py-1 rounded-full"
                    :class="isValid ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'"
                >
                    {{ validationMessage }}
                </span>
                <label class="cursor-pointer p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                    <input
                        type="file"
                        accept="video/*"
                        class="hidden"
                        @change="emit('upload', $event)"
                        :disabled="isUploading"
                    />
                    <IconPhoto class="h-5 w-5 text-gray-500" />
                </label>
            </div>
        </div>

        <!-- Story note -->
        <div v-if="isStory" class="mt-4 p-4 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400 text-center">Stories disappear after 24 hours</p>
            <div class="flex items-center justify-between mt-2">
                <span
                    class="text-xs px-2 py-1 rounded-full"
                    :class="isValid ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'"
                >
                    {{ validationMessage }}
                </span>
                <label class="cursor-pointer p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                    <input
                        type="file"
                        accept="image/*,video/*"
                        class="hidden"
                        @change="emit('upload', $event)"
                        :disabled="isUploading"
                    />
                    <IconPhoto class="h-5 w-5 text-gray-500" />
                </label>
            </div>
        </div>
    </div>

    <!-- Feed Post Preview (original layout) -->
    <div v-else class="bg-white dark:bg-black rounded-lg overflow-hidden border border-gray-200 dark:border-gray-800">
        <!-- Post Header -->
        <div class="px-3 py-2.5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <!-- Avatar with gradient ring -->
                <div class="p-0.5 bg-gradient-to-tr from-yellow-400 via-red-500 to-purple-600 rounded-full">
                    <div class="p-0.5 bg-white dark:bg-black rounded-full">
                        <img
                            v-if="socialAccount.avatar_url"
                            :src="socialAccount.avatar_url"
                            :alt="socialAccount.display_name"
                            class="h-8 w-8 rounded-full object-cover"
                        />
                        <div v-else class="h-8 w-8 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold text-sm">
                            {{ socialAccount.display_name?.charAt(0) }}
                        </div>
                    </div>
                </div>
                <div>
                    <p class="font-semibold text-sm text-gray-900 dark:text-white">
                        {{ socialAccount.username || socialAccount.display_name }}
                    </p>
                </div>
            </div>
            <button class="p-1">
                <IconDots class="h-5 w-5 text-gray-900 dark:text-white" />
            </button>
        </div>

        <!-- Media (required for Instagram) -->
        <div class="relative aspect-square bg-gray-100 dark:bg-gray-900 group">
            <div v-if="media.length > 0" class="w-full h-full">
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
                <!-- Remove button -->
                <button
                    type="button"
                    @click="emit('remove-media', media[0].id)"
                    class="absolute top-3 right-3 bg-black/70 text-white rounded-full p-2 opacity-0 group-hover:opacity-100 transition-opacity"
                >
                    <IconX class="h-4 w-4" />
                </button>
            </div>
            <div v-else class="w-full h-full flex flex-col items-center justify-center text-gray-400">
                <IconPhoto class="h-16 w-16 mb-2" />
                <p class="text-sm">Add a photo or video</p>
                <label class="mt-2 cursor-pointer text-blue-500 font-medium text-sm hover:text-blue-600">
                    <input
                        type="file"
                        accept="image/*,video/*"
                        class="hidden"
                        @change="emit('upload', $event)"
                        :disabled="isUploading"
                    />
                    Upload
                </label>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="px-3 py-2 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button class="hover:opacity-60 transition-opacity">
                    <IconHeart class="h-6 w-6 text-gray-900 dark:text-white" />
                </button>
                <button class="hover:opacity-60 transition-opacity">
                    <IconMessageCircle class="h-6 w-6 text-gray-900 dark:text-white" />
                </button>
                <button class="hover:opacity-60 transition-opacity">
                    <IconSend class="h-6 w-6 text-gray-900 dark:text-white" />
                </button>
            </div>
            <button class="hover:opacity-60 transition-opacity">
                <IconBookmark class="h-6 w-6 text-gray-900 dark:text-white" />
            </button>
        </div>

        <!-- Likes -->
        <div class="px-3 pb-1">
            <p class="font-semibold text-sm text-gray-900 dark:text-white">1,234 likes</p>
        </div>

        <!-- Caption -->
        <div class="px-3 pb-2">
            <div class="text-sm">
                <span class="font-semibold text-gray-900 dark:text-white mr-1">
                    {{ socialAccount.username || socialAccount.display_name }}
                </span>
                <textarea
                    :value="content"
                    @input="emit('update:content', ($event.target as HTMLTextAreaElement).value)"
                    class="w-full min-h-[60px] bg-transparent border-0 p-0 text-sm text-gray-900 dark:text-white resize-none focus:outline-none focus:ring-0 placeholder:text-gray-500"
                    placeholder="Write a caption..."
                />
            </div>
        </div>

        <!-- Timestamp -->
        <div class="px-3 pb-3">
            <p class="text-[10px] text-gray-500 uppercase tracking-wide">Just now</p>
        </div>

        <!-- Footer with char count -->
        <div class="border-t border-gray-200 dark:border-gray-800 px-4 py-3 flex items-center justify-between bg-gray-50 dark:bg-gray-900/50">
            <div class="flex items-center gap-2">
                <span
                    class="text-xs px-2 py-1 rounded-full"
                    :class="isValid ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'"
                >
                    {{ validationMessage }}
                </span>
            </div>
            <label v-if="media.length > 0" class="cursor-pointer p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-800 transition-colors">
                <input
                    type="file"
                    accept="image/*,video/*"
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
