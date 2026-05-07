<script setup lang="ts">
import { IconDots, IconPhoto } from '@tabler/icons-vue';
import { computed } from 'vue';

import PostMediaPreview from '@/components/posts/previews/PostMediaPreview.vue';
import type { MediaItem } from '@/composables/useMedia';

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    avatar_url: string | null;
}

interface Props {
    socialAccount: SocialAccount;
    content: string;
    media: MediaItem[];
    contentType?: string;
    charCount?: number;
    maxLength?: number;
    isValid?: boolean;
    validationMessage?: string;
}

const props = defineProps<Props>();

// Content type helpers
const isReel = computed(() => props.contentType === 'facebook_reel');
const isStory = computed(() => props.contentType === 'facebook_story');
const isFeed = computed(() => !isReel.value && !isStory.value);

// Format numbers like Facebook
const formatNumber = (num: number): string => {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
    }
    return num.toString();
};

// Truncate content for display
const truncatedContent = computed(() => {
    if (!props.content) return '';
    if (props.content.length <= 100) return props.content;
    return props.content.substring(0, 100) + '...';
});

const displayName = computed(
    () => props.socialAccount.display_name || props.socialAccount.username,
);
</script>

<template>
    <div
        class="flex h-full w-full flex-col overflow-hidden bg-[#f0f2f5] text-[#050505] dark:bg-[#18191a] dark:text-[#e4e6eb]"
    >
        <!-- ==================== FEED POST ==================== -->
        <template v-if="isFeed">
            <!-- Facebook Header -->
            <div
                class="flex h-11 flex-shrink-0 items-center border-b border-[#dddfe2] bg-white px-3 dark:border-[#3e4042] dark:bg-[#242526]"
            >
                <svg
                    class="h-6 w-auto text-[#1877f2]"
                    viewBox="0 0 36 36"
                    fill="currentColor"
                >
                    <path
                        d="M20.18 35.87C28.872 34.632 35.87 27.1 35.87 18c0-9.882-8.01-17.89-17.9-17.89C8.09.11.08 8.12.08 18c0 8.06 5.33 14.87 12.66 17.11v-12.3h-3.81V18h3.81v-3.64c0-3.76 2.24-5.84 5.67-5.84 1.64 0 3.36.29 3.36.29v3.69h-1.89c-1.86 0-2.45 1.16-2.45 2.35V18h4.16l-.67 4.81h-3.49v12.3c1.08-.17 2.13-.44 3.14-.79l.56-.45z"
                    />
                </svg>
                <div class="ml-auto flex items-center gap-2">
                    <!-- Search -->
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-[#e4e6eb] dark:bg-[#3a3b3c]"
                    >
                        <svg
                            class="h-4 w-4 text-[#050505] dark:text-[#e4e6eb]"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                        >
                            <circle cx="11" cy="11" r="8" />
                            <path d="m21 21-4.35-4.35" />
                        </svg>
                    </div>
                    <!-- Messenger -->
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-[#e4e6eb] dark:bg-[#3a3b3c]"
                    >
                        <svg
                            class="h-4 w-4 text-[#050505] dark:text-[#e4e6eb]"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path
                                d="M12 2C6.48 2 2 6.04 2 11c0 2.76 1.36 5.22 3.5 6.87V22l3.75-2.06c.98.27 2.01.43 3.08.43h.34c5.52 0 10-4.04 10-9s-4.48-9-10-9h-.67zm1.13 12.13l-2.54-2.71-4.96 2.71 5.46-5.79 2.6 2.71 4.9-2.71-5.46 5.79z"
                            />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Post Content -->
            <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                <!-- Post Card -->
                <div
                    class="flex min-h-0 flex-1 flex-col bg-white dark:bg-[#242526]"
                >
                    <!-- Post Header -->
                    <div class="flex flex-shrink-0 items-center px-3 py-2">
                        <div class="flex flex-1 items-center gap-2">
                            <img
                                v-if="socialAccount.avatar_url"
                                :src="socialAccount.avatar_url"
                                :alt="displayName"
                                class="h-10 w-10 rounded-full object-cover"
                            />
                            <div
                                v-else
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-[#1877f2] font-bold text-white"
                            >
                                {{ displayName?.charAt(0).toUpperCase() }}
                            </div>
                            <div class="flex min-w-0 flex-col">
                                <span
                                    class="text-[13px] leading-tight font-semibold"
                                    >{{ displayName }}</span
                                >
                                <div
                                    class="flex items-center gap-1 text-[11px] text-[#65676b] dark:text-[#b0b3b8]"
                                >
                                    <span>Just now</span>
                                    <span>·</span>
                                    <!-- Globe icon -->
                                    <svg
                                        class="h-3 w-3"
                                        viewBox="0 0 16 16"
                                        fill="currentColor"
                                    >
                                        <path
                                            d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0zm-.5 14.5v-2h1v2a6.5 6.5 0 0 1-1 0zm4.7-1.2a6.5 6.5 0 0 1-3.2.7v-1h2a1 1 0 0 0 1-1V9.5h1.5a6.5 6.5 0 0 1-1.3 3.8zM14.5 8a6.5 6.5 0 0 1-.5 2.5H12V9a1 1 0 0 0-1-1h-1V6a1 1 0 0 0-1-1H6.5V3.5h2a1 1 0 0 0 1-1V1.7a6.5 6.5 0 0 1 5 6.3zM1.5 8a6.5 6.5 0 0 1 3-5.5V4a1 1 0 0 0 1 1h1v2a1 1 0 0 0 1 1h2v3H6a1 1 0 0 0-1 1v2.5A6.5 6.5 0 0 1 1.5 8z"
                                        />
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <IconDots
                            class="h-5 w-5 text-[#65676b] dark:text-[#b0b3b8]"
                        />
                    </div>

                    <!-- Caption -->
                    <div v-if="content" class="flex-shrink-0 px-3 pb-2">
                        <p class="text-[14px] leading-snug">
                            {{ truncatedContent }}
                            <span
                                v-if="content.length > 100"
                                class="cursor-pointer text-[#65676b] dark:text-[#b0b3b8]"
                                >See more</span
                            >
                        </p>
                    </div>

                    <!-- Post Media -->
                    <div class="relative min-h-0 flex-1 bg-black">
                        <PostMediaPreview
                            :media="media"
                            :placeholder-icon="IconPhoto"
                            dot-active-class="bg-[#1877f2]"
                            placeholder-class="w-full h-full flex items-center justify-center bg-[#f0f2f5] dark:bg-[#3a3b3c]"
                        />
                    </div>

                    <!-- Reactions Bar -->
                    <div
                        class="flex flex-shrink-0 items-center justify-between border-b border-[#dddfe2] px-3 py-1.5 dark:border-[#3e4042]"
                    >
                        <div class="flex items-center gap-1">
                            <div class="flex -space-x-1">
                                <!-- Like reaction -->
                                <div
                                    class="flex h-[18px] w-[18px] items-center justify-center rounded-full bg-[#1877f2] ring-2 ring-white dark:ring-[#242526]"
                                >
                                    <svg
                                        class="h-2.5 w-2.5 text-white"
                                        viewBox="0 0 16 16"
                                        fill="currentColor"
                                    >
                                        <path
                                            d="M1 7.75v6.5c0 .41.34.75.75.75h2.5a.75.75 0 0 0 .75-.75v-6.5a.75.75 0 0 0-.75-.75h-2.5a.75.75 0 0 0-.75.75zm13.47-2.58l-3.25.01-.42-1.48a3.51 3.51 0 0 0-3.38-2.56H7.2a.75.75 0 0 0-.7.47l-1.32 3.45a1.75 1.75 0 0 0-.1.56V12c0 .97.78 1.75 1.75 1.75h4.67a1.75 1.75 0 0 0 1.7-1.34l1.3-5.21a1.75 1.75 0 0 0-1.03-2.08z"
                                        />
                                    </svg>
                                </div>
                                <!-- Love reaction -->
                                <div
                                    class="flex h-[18px] w-[18px] items-center justify-center rounded-full bg-[#f33e58] ring-2 ring-white dark:ring-[#242526]"
                                >
                                    <svg
                                        class="h-2.5 w-2.5 text-white"
                                        viewBox="0 0 16 16"
                                        fill="currentColor"
                                    >
                                        <path
                                            d="M8 15.25s-6.75-4.47-6.75-9A3.38 3.38 0 0 1 4.62.75 3.43 3.43 0 0 1 8 2.93 3.43 3.43 0 0 1 11.38.75a3.38 3.38 0 0 1 3.37 3.5c0 4.53-6.75 9-6.75 9z"
                                        />
                                    </svg>
                                </div>
                            </div>
                            <span
                                class="ml-1 text-[12px] text-[#65676b] dark:text-[#b0b3b8]"
                                >{{ formatNumber(156) }}</span
                            >
                        </div>
                        <span
                            class="text-[12px] text-[#65676b] dark:text-[#b0b3b8]"
                            >23 comments · 5 shares</span
                        >
                    </div>

                    <!-- Action Buttons -->
                    <div
                        class="flex flex-shrink-0 items-center border-b border-[#dddfe2] px-2 py-1 dark:border-[#3e4042]"
                    >
                        <button
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-lg py-1.5 text-[#65676b] transition-colors hover:bg-[#f0f2f5] dark:text-[#b0b3b8] dark:hover:bg-[#3a3b3c]"
                        >
                            <svg
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"
                                />
                            </svg>
                            <span class="text-[12px] font-semibold">Like</span>
                        </button>
                        <button
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-lg py-1.5 text-[#65676b] transition-colors hover:bg-[#f0f2f5] dark:text-[#b0b3b8] dark:hover:bg-[#3a3b3c]"
                        >
                            <svg
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                                />
                            </svg>
                            <span class="text-[12px] font-semibold"
                                >Comment</span
                            >
                        </button>
                        <button
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-lg py-1.5 text-[#65676b] transition-colors hover:bg-[#f0f2f5] dark:text-[#b0b3b8] dark:hover:bg-[#3a3b3c]"
                        >
                            <svg
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"
                                />
                                <polyline points="16 6 12 2 8 6" />
                                <line x1="12" y1="2" x2="12" y2="15" />
                            </svg>
                            <span class="text-[12px] font-semibold">Share</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- ==================== REELS ==================== -->
        <template v-else-if="isReel">
            <div class="relative flex-1 overflow-hidden bg-black">
                <!-- Video/Media - Full screen -->
                <div class="absolute inset-0">
                    <PostMediaPreview
                        :media="media"
                        :placeholder-icon="IconPhoto"
                        :show-arrows="false"
                        :show-dots="false"
                        placeholder-class="w-full h-full flex items-center justify-center bg-[#18191a]"
                    />
                </div>

                <!-- Gradient overlay -->
                <div
                    v-if="media.length > 0"
                    class="pointer-events-none absolute inset-x-0 bottom-0 z-[5] h-48 bg-gradient-to-t from-black/70 via-black/30 to-transparent"
                />

                <!-- Top Bar -->
                <div
                    v-if="media.length > 0"
                    class="absolute top-1 right-0 left-0 z-10 flex items-center justify-between px-3"
                >
                    <span
                        class="text-[14px] font-semibold text-white drop-shadow-lg"
                        >Reels</span
                    >
                    <svg
                        class="h-5 w-5 text-white drop-shadow-lg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <circle cx="12" cy="12" r="3" />
                        <path d="M16.5 7.5L21 3M21 3v4M21 3h-4" />
                    </svg>
                </div>

                <!-- Right Sidebar Actions -->
                <div
                    v-if="media.length > 0"
                    class="absolute right-2 bottom-[68px] z-10 flex flex-col items-center gap-4"
                >
                    <!-- Like -->
                    <div class="flex flex-col items-center">
                        <div class="flex h-10 w-10 items-center justify-center">
                            <svg
                                class="h-7 w-7 text-white drop-shadow-lg"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                            >
                                <path
                                    d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"
                                />
                            </svg>
                        </div>
                        <span
                            class="text-[10px] font-semibold text-white drop-shadow"
                            >{{ formatNumber(12453) }}</span
                        >
                    </div>
                    <!-- Comment -->
                    <div class="flex flex-col items-center">
                        <div class="flex h-10 w-10 items-center justify-center">
                            <svg
                                class="h-7 w-7 text-white drop-shadow-lg"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                            >
                                <path
                                    d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"
                                />
                            </svg>
                        </div>
                        <span
                            class="text-[10px] font-semibold text-white drop-shadow"
                            >{{ formatNumber(892) }}</span
                        >
                    </div>
                    <!-- Share -->
                    <div class="flex flex-col items-center">
                        <div class="flex h-10 w-10 items-center justify-center">
                            <svg
                                class="h-7 w-7 text-white drop-shadow-lg"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                            >
                                <path
                                    d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"
                                    transform="rotate(90 12 12)"
                                />
                            </svg>
                        </div>
                    </div>
                    <!-- More -->
                    <div class="flex h-10 w-10 items-center justify-center">
                        <IconDots class="h-6 w-6 text-white drop-shadow-lg" />
                    </div>
                    <!-- Audio spinning icon -->
                    <div
                        class="h-9 w-9 animate-[spin_3s_linear_infinite] overflow-hidden rounded-lg border border-white/30 bg-gradient-to-br from-neutral-600 to-neutral-900"
                    >
                        <img
                            v-if="socialAccount.avatar_url"
                            :src="socialAccount.avatar_url"
                            class="h-full w-full object-cover"
                        />
                        <div
                            v-else
                            class="flex h-full w-full items-center justify-center bg-[#1877f2] text-xs font-bold text-white"
                        >
                            {{ displayName?.charAt(0).toUpperCase() }}
                        </div>
                    </div>
                </div>

                <!-- Bottom Info -->
                <div
                    v-if="media.length > 0"
                    class="absolute right-14 bottom-[68px] left-0 z-10 px-3"
                >
                    <div class="mb-1.5 flex items-center gap-2">
                        <img
                            v-if="socialAccount.avatar_url"
                            :src="socialAccount.avatar_url"
                            class="h-8 w-8 rounded-full border border-white/30 object-cover"
                        />
                        <div
                            v-else
                            class="flex h-8 w-8 items-center justify-center rounded-full border border-white/30 bg-[#1877f2] text-[12px] font-bold text-white"
                        >
                            {{ displayName?.charAt(0).toUpperCase() }}
                        </div>
                        <span
                            class="text-[13px] font-semibold text-white drop-shadow-lg"
                            >{{ displayName }}</span
                        >
                        <button
                            class="rounded-md bg-[#1877f2] px-3 py-1 text-[11px] font-semibold text-white"
                        >
                            Follow
                        </button>
                    </div>
                    <p
                        v-if="content"
                        class="mb-1.5 line-clamp-2 text-[12px] text-white drop-shadow-lg"
                    >
                        {{ content }}
                    </p>
                    <div class="flex items-center">
                        <div
                            class="flex items-center gap-1 rounded-full bg-white/20 px-2 py-0.5 backdrop-blur-sm"
                        >
                            <svg
                                class="h-3 w-3 text-white"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                            >
                                <path
                                    d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"
                                />
                            </svg>
                            <span
                                class="max-w-[120px] truncate text-[10px] text-white"
                                >Original audio</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Bottom Navigation Bar -->
                <div
                    class="absolute right-0 bottom-0 left-0 z-20 flex h-[50px] items-center justify-around bg-black/80 px-4 backdrop-blur-sm"
                >
                    <!-- Home -->
                    <svg
                        class="h-6 w-6 text-white/70"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"
                        />
                    </svg>
                    <!-- Friends -->
                    <svg
                        class="h-6 w-6 text-white/70"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <circle cx="9" cy="7" r="4" />
                        <path d="M2 21v-2a4 4 0 0 1 4-4h6" />
                    </svg>
                    <!-- Reels - Active -->
                    <svg
                        class="h-6 w-6 text-white"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                    >
                        <rect x="2" y="2" width="20" height="20" rx="4" />
                        <polygon points="10,7 10,17 17,12" fill="black" />
                    </svg>
                    <!-- Marketplace -->
                    <svg
                        class="h-6 w-6 text-white/70"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"
                        />
                    </svg>
                    <!-- Profile -->
                    <div
                        class="h-6 w-6 overflow-hidden rounded-full ring-[1.5px] ring-white/50"
                    >
                        <img
                            v-if="socialAccount.avatar_url"
                            :src="socialAccount.avatar_url"
                            class="h-full w-full object-cover"
                        />
                        <div v-else class="h-full w-full bg-neutral-600" />
                    </div>
                </div>
            </div>
        </template>

        <!-- ==================== STORIES ==================== -->
        <template v-else-if="isStory">
            <div class="relative flex-1 overflow-hidden bg-black">
                <!-- Media - Full screen -->
                <div class="absolute inset-0">
                    <PostMediaPreview
                        :media="media"
                        :placeholder-icon="IconPhoto"
                        :show-arrows="false"
                        :show-dots="false"
                        placeholder-class="w-full h-full flex items-center justify-center bg-gradient-to-b from-[#1877f2]/50 to-[#833ab4]/50"
                    />
                </div>

                <!-- Progress Bars -->
                <div
                    v-if="media.length > 0"
                    class="absolute top-1 right-2 left-2 z-10 flex gap-0.5"
                >
                    <div
                        class="h-[2px] flex-1 overflow-hidden rounded-full bg-white/30"
                    >
                        <div class="h-full w-1/3 rounded-full bg-white" />
                    </div>
                </div>

                <!-- User Info -->
                <div
                    v-if="media.length > 0"
                    class="absolute top-3 right-0 left-0 z-10 flex items-center justify-between px-3"
                >
                    <div class="flex items-center gap-2">
                        <div class="rounded-full bg-[#1877f2] p-[2px]">
                            <img
                                v-if="socialAccount.avatar_url"
                                :src="socialAccount.avatar_url"
                                class="h-8 w-8 rounded-full border-2 border-black object-cover"
                            />
                            <div
                                v-else
                                class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-black bg-[#1877f2] text-[11px] font-bold text-white"
                            >
                                {{ displayName?.charAt(0).toUpperCase() }}
                            </div>
                        </div>
                        <span
                            class="text-[13px] font-semibold text-white drop-shadow-lg"
                            >{{ displayName }}</span
                        >
                        <span class="text-[11px] text-white/70 drop-shadow"
                            >2h</span
                        >
                    </div>
                    <div class="flex items-center gap-3">
                        <IconDots class="h-5 w-5 text-white drop-shadow-lg" />
                    </div>
                </div>

                <!-- Caption overlay (if content exists and media) -->
                <div
                    v-if="media.length > 0 && content"
                    class="absolute right-3 bottom-20 left-3 z-10"
                >
                    <p
                        class="line-clamp-2 rounded-xl bg-black/20 px-3 py-2 text-center text-[13px] text-white drop-shadow-lg backdrop-blur-sm"
                    >
                        {{ content }}
                    </p>
                </div>

                <!-- Bottom Reply Bar -->
                <div
                    v-if="media.length > 0"
                    class="absolute right-3 bottom-3 left-3 z-10 flex items-center gap-2"
                >
                    <div
                        class="flex-1 rounded-full border border-white/20 bg-white/20 px-4 py-2.5 backdrop-blur-md"
                    >
                        <span class="text-[13px] text-white/80"
                            >Reply to {{ displayName }}...</span
                        >
                    </div>
                    <svg
                        class="h-7 w-7 text-white drop-shadow-lg"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                    >
                        <path
                            d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"
                        />
                    </svg>
                    <svg
                        class="h-7 w-7 text-white drop-shadow-lg"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                    >
                        <path d="M2 21l21-9L2 3v7l15 2-15 2v7z" />
                    </svg>
                </div>
            </div>
        </template>
    </div>
</template>
