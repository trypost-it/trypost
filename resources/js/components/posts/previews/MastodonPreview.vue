<script setup lang="ts">
import { isVideoMedia, type MediaItem } from '@/composables/useMedia';

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
}

defineProps<Props>();
</script>

<template>
    <div
        class="flex h-full w-full flex-col overflow-hidden bg-white text-[#1f232b] dark:bg-[#191b22] dark:text-white"
    >
        <!-- Main Content -->
        <div class="mt-4 flex-1 overflow-y-auto">
            <!-- Post -->
            <div class="px-4 pb-3">
                <!-- Author -->
                <div class="mb-3 flex items-center gap-3">
                    <img
                        v-if="socialAccount.avatar_url"
                        :src="socialAccount.avatar_url"
                        :alt="socialAccount.display_name"
                        class="h-10 w-10 rounded-full object-cover"
                    />
                    <div
                        v-else
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-[#6364ff] to-[#563acc] font-semibold text-white"
                    >
                        {{ socialAccount.display_name?.charAt(0) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-[15px] font-bold">
                            {{ socialAccount.display_name }}
                        </div>
                        <div
                            class="truncate text-[14px] text-[#606984] dark:text-[#9baec8]"
                        >
                            @{{
                                socialAccount.username || 'user'
                            }}@mastodon.social
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div
                    v-if="content"
                    class="mb-3 text-[16px] leading-[22px] whitespace-pre-wrap"
                >
                    {{ content }}
                </div>

                <!-- Media -->
                <div v-if="media.length > 0" class="mb-3">
                    <div
                        class="overflow-hidden rounded-lg"
                        :class="{
                            'grid grid-cols-2 gap-0.5': media.length >= 2,
                        }"
                    >
                        <div
                            v-for="(item, index) in media.slice(0, 4)"
                            :key="item.id"
                            class="relative overflow-hidden"
                            :class="{
                                'aspect-[4/3]': media.length === 1,
                                'aspect-square': media.length > 1,
                            }"
                        >
                            <img
                                v-if="!isVideoMedia(item)"
                                :src="item.url"
                                :alt="item.original_filename"
                                class="h-full w-full object-cover"
                            />
                            <video
                                v-else
                                :src="item.url"
                                class="h-full w-full bg-black object-cover"
                                muted
                                loop
                                playsinline
                            />
                            <!-- Hide button -->
                            <button
                                class="absolute top-2 right-2 rounded bg-black/60 px-2 py-0.5 text-[12px] text-white"
                            >
                                Hide
                            </button>
                            <div
                                v-if="media.length > 4 && index === 3"
                                class="absolute inset-0 flex items-center justify-center bg-black/60"
                            >
                                <span class="text-xl font-semibold text-white"
                                    >+{{ media.length - 4 }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timestamp -->
                <div
                    class="mb-2 text-[14px] text-[#606984] dark:text-[#9baec8]"
                >
                    <span>Jan 21, 2026, 04:30 PM</span>
                    <span class="mx-1.5">·</span>
                    <svg
                        class="inline h-4 w-4 align-text-bottom"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <circle cx="12" cy="12" r="10" />
                        <line x1="2" y1="12" x2="22" y2="12" />
                        <path
                            d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"
                        />
                    </svg>
                    <span class="mx-1.5">·</span>
                    <span>Web</span>
                </div>

                <!-- Stats -->
                <div class="text-[14px] text-[#606984] dark:text-[#9baec8]">
                    <span class="font-semibold text-[#1f232b] dark:text-white"
                        >0</span
                    >
                    boosts
                    <span class="mx-1.5">·</span>
                    <span class="font-semibold text-[#1f232b] dark:text-white"
                        >0</span
                    >
                    quotes
                    <span class="mx-1.5">·</span>
                    <span class="font-semibold text-[#1f232b] dark:text-white"
                        >0</span
                    >
                    favorites
                </div>
            </div>

            <!-- Actions -->
            <div class="border-t border-[#c9d4de] dark:border-[#313543]">
                <div class="flex items-center justify-around py-2">
                    <!-- Reply -->
                    <button class="rounded-full p-2 hover:bg-[#6364ff]/10">
                        <svg
                            class="h-5 w-5 text-[#606984] dark:text-[#9baec8]"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                        >
                            <path
                                d="M3 10h10a5 5 0 0 1 5 5v6M3 10l6 6M3 10l6-6"
                            />
                        </svg>
                    </button>
                    <!-- Boost -->
                    <button class="rounded-full p-2 hover:bg-[#8c8dff]/10">
                        <svg
                            class="h-5 w-5 text-[#606984] dark:text-[#9baec8]"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                        >
                            <path d="M17 1l4 4-4 4" />
                            <path d="M3 11V9a4 4 0 0 1 4-4h14" />
                            <path d="M7 23l-4-4 4-4" />
                            <path d="M21 13v2a4 4 0 0 1-4 4H3" />
                        </svg>
                    </button>
                    <!-- Favourite (Star) -->
                    <button class="rounded-full p-2 hover:bg-[#ca8f04]/10">
                        <svg
                            class="h-5 w-5 text-[#606984] dark:text-[#9baec8]"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                        >
                            <polygon
                                points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"
                            />
                        </svg>
                    </button>
                    <!-- Bookmark -->
                    <button class="rounded-full p-2 hover:bg-[#6364ff]/10">
                        <svg
                            class="h-5 w-5 text-[#606984] dark:text-[#9baec8]"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                        >
                            <path
                                d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"
                            />
                        </svg>
                    </button>
                    <!-- More -->
                    <button
                        class="rounded-full p-2 hover:bg-neutral-100 dark:hover:bg-[#313543]"
                    >
                        <svg
                            class="h-5 w-5 text-[#606984] dark:text-[#9baec8]"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <circle cx="12" cy="12" r="1.5" />
                            <circle cx="6" cy="12" r="1.5" />
                            <circle cx="18" cy="12" r="1.5" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
