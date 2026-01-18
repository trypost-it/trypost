<script setup lang="ts">
import {
    IconDots,
    IconX,
    IconPhoto,
    IconHeart,
    IconMessageCircle,
    IconRepeat,
    IconShare,
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
    <div class="bg-white dark:bg-[#161e27] rounded-xl overflow-hidden border border-gray-200 dark:border-[#2e3f50]">
        <!-- Post Content -->
        <div class="p-4">
            <div class="flex gap-3">
                <!-- Avatar -->
                <div class="shrink-0">
                    <img
                        v-if="socialAccount.avatar_url"
                        :src="socialAccount.avatar_url"
                        :alt="socialAccount.display_name"
                        class="h-11 w-11 rounded-full object-cover"
                    />
                    <div v-else class="h-11 w-11 rounded-full bg-[#0085ff] flex items-center justify-center text-white font-bold">
                        {{ socialAccount.display_name?.charAt(0) }}
                    </div>
                </div>

                <!-- Content Area -->
                <div class="flex-1 min-w-0">
                    <!-- Header -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1">
                            <span class="font-semibold text-[15px] text-gray-900 dark:text-white">
                                {{ socialAccount.display_name }}
                            </span>
                            <span class="text-gray-500 text-sm">
                                @{{ socialAccount.username }}
                            </span>
                            <span class="text-gray-400 text-sm">· now</span>
                        </div>
                        <button class="p-1 hover:bg-gray-100 dark:hover:bg-[#1e2a35] rounded-full">
                            <IconDots class="h-5 w-5 text-gray-400" />
                        </button>
                    </div>

                    <!-- Post Content -->
                    <div class="mt-1">
                        <textarea
                            :value="content"
                            @input="emit('update:content', ($event.target as HTMLTextAreaElement).value)"
                            class="w-full min-h-[60px] bg-transparent border-0 p-0 text-[15px] text-gray-900 dark:text-white resize-none focus:outline-none focus:ring-0 placeholder:text-gray-400"
                            placeholder="What's up?"
                        />
                    </div>

                    <!-- Media -->
                    <div v-if="media.length > 0" class="mt-3">
                        <div
                            class="grid gap-1 rounded-lg overflow-hidden"
                            :class="{
                                'grid-cols-1': media.length === 1,
                                'grid-cols-2': media.length === 2,
                                'grid-cols-2 grid-rows-2': media.length >= 3,
                            }"
                        >
                            <div
                                v-for="(item, index) in media.slice(0, 4)"
                                :key="item.id"
                                class="relative group overflow-hidden"
                                :class="{
                                    'aspect-video': media.length === 1,
                                    'aspect-square': media.length > 1,
                                    'col-span-2': media.length === 3 && index === 0,
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
                                    <span class="text-white text-xl font-semibold">+{{ media.length - 4 }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Engagement Actions -->
                    <div class="flex items-center justify-between mt-3 -ml-2">
                        <div class="flex items-center gap-1">
                            <button class="flex items-center gap-1 px-2 py-1.5 hover:bg-[#0085ff]/10 rounded-full group text-gray-500">
                                <IconMessageCircle class="h-5 w-5 group-hover:text-[#0085ff]" />
                                <span class="text-sm group-hover:text-[#0085ff]">12</span>
                            </button>
                            <button class="flex items-center gap-1 px-2 py-1.5 hover:bg-green-500/10 rounded-full group text-gray-500">
                                <IconRepeat class="h-5 w-5 group-hover:text-green-500" />
                                <span class="text-sm group-hover:text-green-500">5</span>
                            </button>
                            <button class="flex items-center gap-1 px-2 py-1.5 hover:bg-red-500/10 rounded-full group text-gray-500">
                                <IconHeart class="h-5 w-5 group-hover:text-red-500" />
                                <span class="text-sm group-hover:text-red-500">89</span>
                            </button>
                        </div>
                        <button class="p-1.5 hover:bg-[#0085ff]/10 rounded-full group text-gray-500">
                            <IconShare class="h-5 w-5 group-hover:text-[#0085ff]" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer with upload and char count -->
        <div class="border-t border-gray-200 dark:border-[#2e3f50] px-4 py-3 flex items-center justify-between bg-gray-50 dark:bg-[#1a2634]">
            <div class="flex items-center gap-2">
                <span
                    class="text-xs px-2 py-1 rounded-full"
                    :class="isValid ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'"
                >
                    {{ validationMessage }}
                </span>
            </div>
            <label class="cursor-pointer p-2 rounded-full hover:bg-gray-200 dark:hover:bg-[#2e3f50] transition-colors">
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
</template>
