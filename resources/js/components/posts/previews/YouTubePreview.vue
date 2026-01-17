<script setup lang="ts">
import {
    IconVideo,
    IconX,
    IconPhoto,
    IconThumbUp,
    IconThumbDown,
    IconMessageCircle,
    IconShare,
    IconDots,
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
    <div class="bg-[#0f0f0f] rounded-xl overflow-hidden relative" style="aspect-ratio: 9/16; max-height: 600px;">
        <!-- Video Area -->
        <div class="absolute inset-0">
            <div v-if="media.length > 0 && media[0].type === 'video'" class="w-full h-full">
                <video :src="media[0].url" class="w-full h-full object-cover" muted loop playsinline />
                <button
                    type="button"
                    @click="emit('remove-media', media[0].id)"
                    class="absolute top-4 right-4 bg-black/50 text-white rounded-full p-2"
                >
                    <IconX class="h-5 w-5" />
                </button>
            </div>
            <div v-else-if="media.length > 0 && media[0].type === 'image'" class="w-full h-full">
                <img :src="media[0].url" :alt="media[0].original_filename" class="w-full h-full object-cover" />
                <button
                    type="button"
                    @click="emit('remove-media', media[0].id)"
                    class="absolute top-4 right-4 bg-black/50 text-white rounded-full p-2"
                >
                    <IconX class="h-5 w-5" />
                </button>
            </div>
            <div v-else class="w-full h-full flex flex-col items-center justify-center bg-[#0f0f0f] text-gray-400">
                <IconVideo class="h-20 w-20 mb-4" />
                <p class="text-sm mb-2">Add a Short video</p>
                <label class="cursor-pointer bg-[#ff0000] text-white px-6 py-2 rounded-full font-semibold text-sm hover:bg-[#cc0000] transition-colors">
                    <input
                        type="file"
                        accept="video/*,image/*"
                        class="hidden"
                        @change="emit('upload', $event)"
                        :disabled="isUploading"
                    />
                    Upload
                </label>
            </div>
        </div>

        <!-- Gradient overlay at bottom -->
        <div class="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-black/80 via-black/40 to-transparent pointer-events-none" />

        <!-- Right side actions -->
        <div class="absolute right-3 bottom-28 flex flex-col items-center gap-5">
            <!-- Like -->
            <button class="flex flex-col items-center">
                <div class="h-12 w-12 bg-[#272727] rounded-full flex items-center justify-center">
                    <IconThumbUp class="h-6 w-6 text-white" />
                </div>
                <span class="text-white text-xs mt-1">12K</span>
            </button>

            <!-- Dislike -->
            <button class="flex flex-col items-center">
                <div class="h-12 w-12 bg-[#272727] rounded-full flex items-center justify-center">
                    <IconThumbDown class="h-6 w-6 text-white" />
                </div>
                <span class="text-white text-xs mt-1">Dislike</span>
            </button>

            <!-- Comment -->
            <button class="flex flex-col items-center">
                <div class="h-12 w-12 bg-[#272727] rounded-full flex items-center justify-center">
                    <IconMessageCircle class="h-6 w-6 text-white" />
                </div>
                <span class="text-white text-xs mt-1">234</span>
            </button>

            <!-- Share -->
            <button class="flex flex-col items-center">
                <div class="h-12 w-12 bg-[#272727] rounded-full flex items-center justify-center">
                    <IconShare class="h-6 w-6 text-white" />
                </div>
                <span class="text-white text-xs mt-1">Share</span>
            </button>

            <!-- More -->
            <button class="flex flex-col items-center">
                <div class="h-12 w-12 bg-[#272727] rounded-full flex items-center justify-center">
                    <IconDots class="h-6 w-6 text-white" />
                </div>
            </button>

            <!-- Channel avatar -->
            <div class="relative mt-1">
                <img
                    v-if="socialAccount.avatar_url"
                    :src="socialAccount.avatar_url"
                    :alt="socialAccount.display_name"
                    class="h-10 w-10 rounded-full object-cover border-2 border-white"
                />
                <div v-else class="h-10 w-10 rounded-full bg-[#ff0000] flex items-center justify-center text-white font-bold border-2 border-white text-sm">
                    {{ socialAccount.display_name?.charAt(0) }}
                </div>
            </div>
        </div>

        <!-- Bottom info -->
        <div class="absolute left-3 right-20 bottom-4 text-white">
            <div class="flex items-center gap-2 mb-2">
                <img
                    v-if="socialAccount.avatar_url"
                    :src="socialAccount.avatar_url"
                    :alt="socialAccount.display_name"
                    class="h-8 w-8 rounded-full object-cover"
                />
                <div v-else class="h-8 w-8 rounded-full bg-[#ff0000] flex items-center justify-center text-white font-bold text-xs">
                    {{ socialAccount.display_name?.charAt(0) }}
                </div>
                <span class="text-sm font-medium">@{{ socialAccount.username || socialAccount.display_name }}</span>
                <button class="ml-1 bg-white text-black text-xs font-semibold px-3 py-1 rounded-full hover:bg-gray-200">
                    Subscribe
                </button>
            </div>
            <div>
                <textarea
                    :value="content"
                    @input="emit('update:content', ($event.target as HTMLTextAreaElement).value)"
                    class="w-full min-h-[40px] max-h-[80px] bg-transparent border-0 p-0 text-sm text-white resize-none focus:outline-none focus:ring-0 placeholder:text-gray-400"
                    placeholder="Add a title..."
                />
            </div>
        </div>

        <!-- Shorts badge -->
        <div class="absolute top-4 left-3 flex items-center gap-2">
            <div class="bg-[#ff0000] text-white text-xs font-bold px-2 py-1 rounded">
                Shorts
            </div>
        </div>

        <!-- Footer with char count -->
        <div class="absolute top-4 right-3 flex items-center gap-2">
            <span
                class="text-xs px-2 py-1 rounded-full"
                :class="isValid ? 'bg-green-500/30 text-green-300' : 'bg-red-500/30 text-red-300'"
            >
                {{ validationMessage }}
            </span>
            <label v-if="media.length > 0" class="cursor-pointer p-2 rounded-full bg-black/30 hover:bg-black/50 transition-colors">
                <input
                    type="file"
                    accept="video/*,image/*"
                    class="hidden"
                    @change="emit('upload', $event)"
                    :disabled="isUploading"
                />
                <IconPhoto class="h-5 w-5 text-white" />
            </label>
        </div>
    </div>
</template>
