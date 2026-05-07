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
        class="flex h-full w-full flex-col overflow-hidden bg-white text-black dark:bg-[#0a1424] dark:text-white"
    >
        <!-- Bluesky Mobile Header -->
        <div
            class="flex h-11 flex-shrink-0 items-center justify-between border-b border-neutral-200 px-4 dark:border-[#1e3a5f]"
        >
            <!-- Back arrow -->
            <button class="-ml-1 p-1">
                <svg
                    class="h-5 w-5 text-black dark:text-white"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path d="M19 12H5M12 19l-7-7 7-7" />
                </svg>
            </button>

            <!-- Title -->
            <span class="text-[16px] font-semibold">Post</span>

            <!-- More options -->
            <button class="-mr-1 p-1">
                <svg
                    class="h-5 w-5 text-black dark:text-white"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                >
                    <circle cx="12" cy="12" r="1.5" />
                    <circle cx="12" cy="6" r="1.5" />
                    <circle cx="12" cy="18" r="1.5" />
                </svg>
            </button>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto">
            <!-- Post -->
            <div class="px-3 pt-3 pb-2">
                <!-- Author row -->
                <div class="flex items-start gap-3">
                    <!-- Avatar -->
                    <img
                        v-if="socialAccount.avatar_url"
                        :src="socialAccount.avatar_url"
                        :alt="socialAccount.display_name"
                        class="h-11 w-11 shrink-0 rounded-full object-cover"
                    />
                    <div
                        v-else
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#0085ff] to-[#00d4ff] font-semibold text-white"
                    >
                        {{ socialAccount.display_name?.charAt(0) }}
                    </div>

                    <!-- Name and handle -->
                    <div class="min-w-0 flex-1">
                        <div
                            class="text-[15px] font-semibold text-black dark:text-white"
                        >
                            {{ socialAccount.display_name }}
                        </div>
                        <div
                            class="truncate text-[14px] text-neutral-500 dark:text-[#7b8d9e]"
                        >
                            @{{
                                socialAccount.username || 'handle'
                            }}.bsky.social
                        </div>
                    </div>
                </div>

                <!-- Post content -->
                <div
                    v-if="content"
                    class="mt-3 text-[16px] leading-[22px] whitespace-pre-wrap text-black dark:text-white"
                >
                    {{ content }}
                </div>

                <!-- Media -->
                <div v-if="media.length > 0" class="mt-3">
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
                                'col-span-2': media.length === 3 && index === 0,
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
                    class="mt-3 text-[13px] text-neutral-500 dark:text-[#7b8d9e]"
                >
                    4:18 PM · Jan 21, 2026
                </div>
            </div>

            <!-- Engagement Actions -->
            <div class="border-t border-neutral-200 dark:border-[#1e3a5f]">
                <div class="flex items-center justify-between px-3 py-1">
                    <!-- Reply -->
                    <button
                        class="group rounded-full p-2 hover:bg-neutral-100 dark:hover:bg-[#1e3a5f]/50"
                    >
                        <svg
                            class="h-5 w-5 text-neutral-500 group-hover:text-[#1185fe] dark:text-[#7b8d9e]"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                        >
                            <path
                                d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                            />
                        </svg>
                    </button>

                    <!-- Repost -->
                    <button
                        class="group rounded-full p-2 hover:bg-neutral-100 dark:hover:bg-[#1e3a5f]/50"
                    >
                        <svg
                            class="h-5 w-5 text-neutral-500 group-hover:text-[#00ba7c] dark:text-[#7b8d9e]"
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

                    <!-- Like -->
                    <button
                        class="group rounded-full p-2 hover:bg-neutral-100 dark:hover:bg-[#1e3a5f]/50"
                    >
                        <svg
                            class="h-5 w-5 text-neutral-500 group-hover:text-[#f91880] dark:text-[#7b8d9e]"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                        >
                            <path
                                d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"
                            />
                        </svg>
                    </button>

                    <!-- Bookmark -->
                    <button
                        class="group rounded-full p-2 hover:bg-neutral-100 dark:hover:bg-[#1e3a5f]/50"
                    >
                        <svg
                            class="h-5 w-5 text-neutral-500 group-hover:text-[#1185fe] dark:text-[#7b8d9e]"
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

                    <!-- Share -->
                    <button
                        class="group rounded-full p-2 hover:bg-neutral-100 dark:hover:bg-[#1e3a5f]/50"
                    >
                        <svg
                            class="h-5 w-5 text-neutral-500 group-hover:text-[#1185fe] dark:text-[#7b8d9e]"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                        >
                            <path
                                d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"
                            />
                            <polyline points="16 6 12 2 8 6" />
                            <line x1="12" y1="2" x2="12" y2="15" />
                        </svg>
                    </button>

                    <!-- More -->
                    <button
                        class="group rounded-full p-2 hover:bg-neutral-100 dark:hover:bg-[#1e3a5f]/50"
                    >
                        <svg
                            class="h-5 w-5 text-neutral-500 group-hover:text-[#1185fe] dark:text-[#7b8d9e]"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <circle cx="12" cy="12" r="1.5" />
                            <circle cx="19" cy="12" r="1.5" />
                            <circle cx="5" cy="12" r="1.5" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Reply input -->
            <div
                class="flex items-center gap-3 border-t border-neutral-200 px-4 py-3 dark:border-[#1e3a5f]"
            >
                <img
                    v-if="socialAccount.avatar_url"
                    :src="socialAccount.avatar_url"
                    :alt="socialAccount.display_name"
                    class="h-8 w-8 shrink-0 rounded-full object-cover"
                />
                <div
                    v-else
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#0085ff] to-[#00d4ff] text-xs font-semibold text-white"
                >
                    {{ socialAccount.display_name?.charAt(0) }}
                </div>
                <div
                    class="flex-1 text-[15px] text-neutral-400 dark:text-[#7b8d9e]"
                >
                    Write your reply
                </div>
            </div>
        </div>
    </div>
</template>
