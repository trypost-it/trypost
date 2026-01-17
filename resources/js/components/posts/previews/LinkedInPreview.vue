<script setup lang="ts">
import { computed } from 'vue';
import {
    IconWorld,
    IconThumbUp,
    IconMessageCircle,
    IconRepeat,
    IconSend,
    IconDots,
    IconVideo,
    IconX,
    IconPhoto,
    IconHeart,
} from '@tabler/icons-vue';

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

interface Props {
    socialAccount: SocialAccount;
    content: string;
    media: MediaItem[];
    charCount: number;
    maxLength: number;
    isValid: boolean;
    validationMessage: string;
    isUploading?: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:content': [value: string];
    'upload': [event: Event];
    'remove-media': [mediaId: string];
}>();
</script>

<template>
    <div class="bg-white dark:bg-[#1b1f23] rounded-lg shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700">
        <!-- Post Header -->
        <div class="p-4">
            <div class="flex items-start gap-3">
                <img
                    v-if="socialAccount.avatar_url"
                    :src="socialAccount.avatar_url"
                    :alt="socialAccount.display_name"
                    class="h-12 w-12 rounded-full object-cover"
                />
                <div v-else class="h-12 w-12 rounded-full bg-[#0a66c2] flex items-center justify-center text-white font-semibold">
                    {{ socialAccount.display_name?.charAt(0) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm text-gray-900 dark:text-white hover:text-[#0a66c2] hover:underline cursor-pointer">
                        {{ socialAccount.display_name }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                        Software Engineer
                    </p>
                    <div class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                        <span>now</span>
                        <span>·</span>
                        <IconWorld class="h-3 w-3" />
                    </div>
                </div>
                <button class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-full">
                    <IconDots class="h-5 w-5 text-gray-500" />
                </button>
            </div>

            <!-- Content -->
            <div class="mt-3">
                <textarea
                    :value="content"
                    @input="emit('update:content', ($event.target as HTMLTextAreaElement).value)"
                    class="w-full min-h-[120px] bg-transparent border-0 p-0 text-sm text-gray-900 dark:text-white resize-none focus:outline-none focus:ring-0 placeholder:text-gray-500"
                    placeholder="What do you want to talk about?"
                />
            </div>
        </div>

        <!-- Media -->
        <div v-if="media.length > 0" class="border-t border-gray-200 dark:border-gray-700">
            <div
                class="grid"
                :class="{
                    'grid-cols-1': media.length === 1,
                    'grid-cols-2': media.length >= 2,
                }"
            >
                <div
                    v-for="(item, index) in media.slice(0, 4)"
                    :key="item.id"
                    class="relative group aspect-square overflow-hidden"
                    :class="{
                        'col-span-2 aspect-video': media.length === 1,
                        'row-span-2': media.length === 3 && index === 0,
                    }"
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
                        loop
                        playsinline
                    />
                    <button
                        type="button"
                        @click="emit('remove-media', item.id)"
                        class="absolute top-2 right-2 bg-black/70 text-white rounded-full p-1.5 opacity-0 group-hover:opacity-100 transition-opacity"
                    >
                        <IconX class="h-4 w-4" />
                    </button>
                    <div
                        v-if="media.length > 4 && index === 3"
                        class="absolute inset-0 bg-black/60 flex items-center justify-center"
                    >
                        <span class="text-white text-2xl font-semibold">+{{ media.length - 4 }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Engagement Stats -->
        <div class="px-4 py-2 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-1">
                <div class="flex -space-x-1">
                    <div class="h-4 w-4 rounded-full bg-[#0a66c2] flex items-center justify-center">
                        <IconThumbUp class="h-2.5 w-2.5 text-white" />
                    </div>
                    <div class="h-4 w-4 rounded-full bg-red-500 flex items-center justify-center">
                        <IconHeart class="h-2.5 w-2.5 text-white" />
                    </div>
                </div>
                <span>24</span>
            </div>
            <div class="flex items-center gap-2">
                <span>3 comments</span>
                <span>·</span>
                <span>1 repost</span>
            </div>
        </div>

        <!-- Actions -->
        <div class="px-2 py-1 flex items-center justify-between border-t border-gray-200 dark:border-gray-700">
            <button class="flex-1 flex items-center justify-center gap-2 py-3 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg text-gray-600 dark:text-gray-400 font-medium text-sm">
                <IconThumbUp class="h-5 w-5" />
                <span class="hidden sm:inline">Like</span>
            </button>
            <button class="flex-1 flex items-center justify-center gap-2 py-3 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg text-gray-600 dark:text-gray-400 font-medium text-sm">
                <IconMessageCircle class="h-5 w-5" />
                <span class="hidden sm:inline">Comment</span>
            </button>
            <button class="flex-1 flex items-center justify-center gap-2 py-3 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg text-gray-600 dark:text-gray-400 font-medium text-sm">
                <IconRepeat class="h-5 w-5" />
                <span class="hidden sm:inline">Repost</span>
            </button>
            <button class="flex-1 flex items-center justify-center gap-2 py-3 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg text-gray-600 dark:text-gray-400 font-medium text-sm">
                <IconSend class="h-5 w-5" />
                <span class="hidden sm:inline">Send</span>
            </button>
        </div>

        <!-- Footer with upload and char count -->
        <div class="border-t border-gray-200 dark:border-gray-700 px-4 py-3 flex items-center justify-between bg-gray-50 dark:bg-gray-800/50">
            <div class="flex items-center gap-2">
                <span
                    class="text-xs px-2 py-1 rounded-full"
                    :class="isValid ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'"
                >
                    {{ validationMessage }}
                </span>
            </div>
            <label class="cursor-pointer p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
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
</template>
