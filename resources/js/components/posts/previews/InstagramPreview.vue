<script setup lang="ts">
import {
    IconDots,
    IconHeart,
    IconMessageCircle,
    IconSend,
    IconBookmark,
    IconMusic,
    IconVolume,
    IconCamera,
    IconPlayerPlayFilled,
    IconPhoto,
    IconChevronLeft,
    IconChevronRight,
} from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

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
    charCount?: number;
    maxLength?: number;
    isValid?: boolean;
    validationMessage?: string;
}

const props = defineProps<Props>();

// Content type helpers
const isReel = computed(() => props.contentType === 'instagram_reel');
const isStory = computed(() => props.contentType === 'instagram_story');
const isFeed = computed(() => !isReel.value && !isStory.value);

// Carousel state
const currentIndex = ref(0);

// Reset carousel index when media changes
watch(() => props.media.length, () => {
    if (currentIndex.value >= props.media.length) {
        currentIndex.value = Math.max(0, props.media.length - 1);
    }
});

// Format numbers like Instagram
const formatNumber = (num: number): string => {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'm';
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
    }
    return num.toString();
};

// Truncate caption for display
const truncatedCaption = computed(() => {
    if (!props.content) return '';
    if (props.content.length <= 80) return props.content;
    return props.content.substring(0, 80) + '...';
});

const username = computed(() => props.socialAccount.username || props.socialAccount.display_name);

// Carousel navigation
const goToPrevious = () => {
    if (currentIndex.value > 0) {
        currentIndex.value--;
    }
};

const goToNext = () => {
    if (currentIndex.value < props.media.length - 1) {
        currentIndex.value++;
    }
};

const goToSlide = (index: number) => {
    currentIndex.value = index;
};
</script>

<template>
    <div class="w-full h-full bg-white dark:bg-black text-[#262626] dark:text-[#f5f5f5] overflow-hidden flex flex-col">

        <!-- ==================== FEED POST ==================== -->
        <template v-if="isFeed">
            <!-- Instagram Header -->
            <div class="flex-shrink-0 h-11 border-b border-[#dbdbdb] dark:border-[#262626] flex items-center px-3">
                <div class="text-lg font-semibold tracking-tight"
                    style="font-family: 'Instagram Sans', -apple-system, BlinkMacSystemFont, sans-serif;">
                    Instagram
                </div>
            </div>

            <!-- Post Content - No scroll -->
            <div class="flex-1 flex flex-col min-h-0 pb-6">
                <!-- Post Header -->
                <div class="flex-shrink-0 flex items-center px-2.5 py-1.5">
                    <div class="flex items-center gap-2 flex-1">
                        <!-- Avatar with gradient ring -->
                        <div class="p-[2px] rounded-full"
                            style="background: conic-gradient(from 180deg, #feda75, #fa7e1e, #d62976, #962fbf, #4f5bd5, #feda75)">
                            <div class="p-[1.5px] bg-white dark:bg-black rounded-full">
                                <img v-if="socialAccount.avatar_url" :src="socialAccount.avatar_url"
                                    :alt="socialAccount.display_name" class="w-7 h-7 rounded-full object-cover" />
                                <div v-else
                                    class="w-7 h-7 rounded-full bg-gradient-to-br from-[#833ab4] to-[#fd1d1d] flex items-center justify-center text-white font-semibold text-[10px]">
                                    {{ socialAccount.display_name?.charAt(0).toUpperCase() }}
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col min-w-0">
                            <span class="text-[12px] font-semibold leading-tight truncate">{{ username }}</span>
                        </div>
                    </div>
                    <IconDots class="w-4 h-4 flex-shrink-0" />
                </div>

                <!-- Post Media - Fixed height to prevent overflow -->
                <div class="flex-1 relative bg-black min-h-0">
                    <template v-if="media.length > 0">
                        <img v-if="media[currentIndex]?.type === 'image'" :src="media[currentIndex].url"
                            :alt="media[currentIndex].original_filename" class="w-full h-full object-cover" />
                        <video v-else-if="media[currentIndex]" :src="media[currentIndex].url"
                            class="w-full h-full object-cover" muted loop playsinline />

                        <!-- Carousel navigation arrows -->
                        <template v-if="media.length > 1">
                            <!-- Previous arrow -->
                            <button v-if="currentIndex > 0" @click="goToPrevious"
                                class="absolute left-1.5 top-1/2 -translate-y-1/2 w-6 h-6 bg-white/90 rounded-full flex items-center justify-center shadow-sm hover:bg-white transition-colors z-10">
                                <IconChevronLeft class="w-4 h-4 text-[#262626]" />
                            </button>
                            <!-- Next arrow -->
                            <button v-if="currentIndex < media.length - 1" @click="goToNext"
                                class="absolute right-1.5 top-1/2 -translate-y-1/2 w-6 h-6 bg-white/90 rounded-full flex items-center justify-center shadow-sm hover:bg-white transition-colors z-10">
                                <IconChevronRight class="w-4 h-4 text-[#262626]" />
                            </button>
                            <!-- Carousel dots -->
                            <div class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1">
                                <button v-for="(_, i) in media" :key="i" @click="goToSlide(i)"
                                    class="w-[6px] h-[6px] rounded-full transition-colors"
                                    :class="i === currentIndex ? 'bg-[#0095f6]' : 'bg-white/50 hover:bg-white/70'" />
                            </div>
                        </template>
                    </template>
                    <div v-else class="w-full h-full flex items-center justify-center bg-[#fafafa] dark:bg-[#121212]">
                        <IconPhoto class="w-12 h-12 text-[#dbdbdb] dark:text-[#363636]" />
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex-shrink-0 flex items-center justify-between px-2.5 py-1.5">
                    <div class="flex items-center gap-3">
                        <IconHeart class="w-5 h-5" />
                        <IconMessageCircle class="w-5 h-5 -scale-x-100" />
                        <IconSend class="w-5 h-5 -rotate-12" />
                    </div>
                    <IconBookmark class="w-5 h-5" />
                </div>

                <!-- Likes -->
                <div class="flex-shrink-0 px-2.5">
                    <span class="text-[12px] font-semibold">{{ formatNumber(1234) }} likes</span>
                </div>

                <!-- Caption -->
                <div v-if="content" class="flex-shrink-0 px-2.5 py-0.5">
                    <p class="text-[12px] line-clamp-2">
                        <span class="font-semibold">{{ username }}</span>
                        <span class="ml-1">{{ content }}</span>
                    </p>
                </div>
            </div>
        </template>

        <!-- ==================== REELS ==================== -->
        <template v-else-if="isReel">
            <div class="relative flex-1 bg-[#fafafa] dark:bg-black overflow-hidden">
                <!-- Video/Media - Full screen -->
                <div class="absolute inset-0">
                    <template v-if="media.length > 0">
                        <img v-if="media[0].type === 'image'" :src="media[0].url" class="w-full h-full object-cover" />
                        <video v-else :src="media[0].url" class="w-full h-full object-cover" muted loop playsinline />
                    </template>
                    <div v-else class="w-full h-full flex items-center justify-center">
                        <IconPlayerPlayFilled class="w-12 h-12 text-[#dbdbdb] dark:text-white/30" />
                    </div>
                </div>

                <!-- Top Bar - below status bar -->
                <div class="absolute top-1 left-0 right-0 px-3 flex items-center justify-between z-10">
                    <span class="text-[#262626] dark:text-white text-[14px] font-semibold"
                        :class="media.length > 0 ? 'drop-shadow-lg dark:drop-shadow-lg text-white' : ''">Reels</span>
                    <IconCamera class="w-5 h-5"
                        :class="media.length > 0 ? 'text-white drop-shadow-lg' : 'text-[#262626] dark:text-white'" />
                </div>

                <!-- Right Sidebar Actions (only show when media exists) -->
                <div v-if="media.length > 0" class="absolute right-2 bottom-3 flex flex-col items-center gap-4 z-10">
                    <div class="flex flex-col items-center">
                        <IconHeart class="w-6 h-6 text-white drop-shadow-lg" />
                        <span class="text-white text-[10px] font-semibold mt-0.5 drop-shadow">{{ formatNumber(12453)
                        }}</span>
                    </div>
                    <div class="flex flex-col items-center">
                        <IconMessageCircle class="w-6 h-6 text-white drop-shadow-lg" />
                        <span class="text-white text-[10px] font-semibold mt-0.5 drop-shadow">{{ formatNumber(892)
                        }}</span>
                    </div>
                    <div class="flex flex-col items-center mb-4">
                        <IconSend class="w-6 h-6 text-white drop-shadow-lg -rotate-12" />
                    </div>
                </div>

                <!-- Bottom Info (only show when media exists) -->
                <div v-if="media.length > 0" class="absolute left-0 right-10 bottom-3 px-3 z-10">
                    <div class="flex items-center gap-2 mb-1.5">
                        <img v-if="socialAccount.avatar_url" :src="socialAccount.avatar_url"
                            class="w-7 h-7 rounded-full object-cover border border-white/30" />
                        <div v-else
                            class="w-7 h-7 rounded-full bg-gradient-to-br from-[#833ab4] to-[#fd1d1d] flex items-center justify-center text-white font-semibold text-[10px] border border-white/30">
                            {{ socialAccount.display_name?.charAt(0).toUpperCase() }}
                        </div>
                        <span class="text-white text-[12px] font-semibold drop-shadow-lg">{{ username }}</span>
                        <button class="px-2 py-0.5 border border-white/70 rounded text-white text-[10px] font-semibold">
                            Follow
                        </button>
                    </div>
                    <p v-if="content" class="text-white text-[11px] drop-shadow-lg line-clamp-2 mb-1.5">
                        {{ truncatedCaption }}
                    </p>
                    <div class="flex items-center">
                        <div class="flex items-center gap-1 bg-white/20 backdrop-blur-sm rounded-full px-2 py-0.5">
                            <IconMusic class="w-2.5 h-2.5 text-white" />
                            <span class="text-white text-[9px] truncate max-w-[100px]">Original audio</span>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- ==================== STORIES ==================== -->
        <template v-else-if="isStory">
            <div class="relative flex-1 bg-[#fafafa] dark:bg-black overflow-hidden">
                <!-- Media - Full screen -->
                <div class="absolute inset-0">
                    <template v-if="media.length > 0">
                        <img v-if="media[0].type === 'image'" :src="media[0].url" class="w-full h-full object-cover" />
                        <video v-else :src="media[0].url" class="w-full h-full object-cover" muted loop playsinline />
                    </template>
                    <div v-else class="w-full h-full flex items-center justify-center">
                        <IconPhoto class="w-12 h-12 text-[#dbdbdb] dark:text-white/30" />
                    </div>
                </div>

                <!-- Progress Bars - below status bar -->
                <div class="absolute top-0.5 left-2 right-2 flex gap-0.5 z-10">
                    <div class="flex-1 h-[2px] rounded-full overflow-hidden"
                        :class="media.length > 0 ? 'bg-white/30' : 'bg-[#dbdbdb] dark:bg-white/30'">
                        <div class="h-full w-1/3 rounded-full"
                            :class="media.length > 0 ? 'bg-white' : 'bg-[#262626] dark:bg-white'" />
                    </div>
                </div>

                <!-- User Info -->
                <div class="absolute top-3 left-0 right-0 px-2.5 flex items-center justify-between z-10">
                    <div class="flex items-center gap-2">
                        <div class="p-[2px] rounded-full"
                            style="background: conic-gradient(from 180deg, #feda75, #fa7e1e, #d62976, #962fbf, #4f5bd5, #feda75)">
                            <div class="p-[1.5px] bg-white dark:bg-black rounded-full">
                                <img v-if="socialAccount.avatar_url" :src="socialAccount.avatar_url"
                                    class="w-7 h-7 rounded-full object-cover" />
                                <div v-else
                                    class="w-7 h-7 rounded-full bg-gradient-to-br from-[#833ab4] to-[#fd1d1d] flex items-center justify-center text-white font-semibold text-[10px]">
                                    {{ socialAccount.display_name?.charAt(0).toUpperCase() }}
                                </div>
                            </div>
                        </div>
                        <span class="text-[12px] font-semibold"
                            :class="media.length > 0 ? 'text-white drop-shadow-lg' : 'text-[#262626] dark:text-white'">{{
                                username
                            }}</span>
                        <span class="text-[10px]"
                            :class="media.length > 0 ? 'text-white/70 drop-shadow' : 'text-[#737373] dark:text-white/70'">2h</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <IconVolume class="w-4 h-4"
                            :class="media.length > 0 ? 'text-white drop-shadow-lg' : 'text-[#262626] dark:text-white'" />
                        <IconDots class="w-4 h-4"
                            :class="media.length > 0 ? 'text-white drop-shadow-lg' : 'text-[#262626] dark:text-white'" />
                    </div>
                </div>

                <!-- Caption overlay (if content exists, only show when media) -->
                <div v-if="content && media.length > 0" class="absolute bottom-14 left-2.5 right-2.5 z-10">
                    <p
                        class="text-white text-[12px] text-center drop-shadow-lg bg-black/20 backdrop-blur-sm rounded-lg px-2.5 py-1.5 line-clamp-2">
                        {{ content }}
                    </p>
                </div>

                <!-- Bottom Reply Bar (only show when media exists) -->
                <div v-if="media.length > 0"
                    class="absolute bottom-2.5 left-2.5 right-2.5 flex items-center gap-2 z-10">
                    <div class="flex-1 bg-white/20 backdrop-blur-md rounded-full px-3 py-2 border border-white/20">
                        <span class="text-white/80 text-[12px]">Send message</span>
                    </div>
                    <IconHeart class="w-6 h-6 text-white drop-shadow-lg" />
                    <IconSend class="w-6 h-6 text-white drop-shadow-lg -rotate-12" />
                </div>
            </div>
        </template>
    </div>
</template>