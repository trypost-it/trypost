<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { IconCalendar, IconExternalLink, IconLoader2, IconX } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

import LabelBadge from '@/components/labels/LabelBadge.vue';
import PostPlatformMetrics from '@/components/posts/PostPlatformMetrics.vue';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { getPlatformLabel, getPlatformLogo } from '@/composables/usePlatformLogo';
import { getPlatformStatusConfig, getPostStatusConfig } from '@/composables/usePostStatus';
import dayjs from '@/dayjs';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as postsIndex } from '@/routes/app/posts';

interface MediaItem {
    id: string;
    url: string;
    type?: string;
    mime_type?: string;
    original_filename?: string;
}

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    avatar_url: string | null;
}

interface PostPlatform {
    id: string;
    platform: string;
    display_name: string;
    display_username: string | null;
    display_avatar: string | null;
    content_type: string | null;
    status: 'pending' | 'publishing' | 'published' | 'failed';
    platform_url: string | null;
    error_message: string | null;
    published_at: string | null;
    enabled: boolean;
    social_account: SocialAccount | null;
}

interface Post {
    id: string;
    content: string;
    media: MediaItem[];
    status: string;
    scheduled_at: string | null;
    published_at: string | null;
    platforms: PostPlatform[];
    labels?: { id: string; name: string; color: string }[];
}

interface Workspace {
    id: string;
    name: string;
}

const props = defineProps<{
    workspace: Workspace;
    post: Post;
}>();

const enabledPlatforms = computed(() => props.post.platforms.filter((pp) => pp.enabled));
const publishedCount = computed(() => enabledPlatforms.value.filter((pp) => pp.status === 'published').length);
const failedCount = computed(() => enabledPlatforms.value.filter((pp) => pp.status === 'failed').length);

const isPublishing = computed(() => props.post.status === 'publishing');

const postStatus = computed(() => getPostStatusConfig(props.post.status));

const pageTitle = computed(() => {
    const snippet = props.post.content?.trim().split('\n')[0]?.slice(0, 60) ?? '';
    return snippet ? `${trans('posts.show.title')} · ${snippet}${props.post.content.length > 60 ? '…' : ''}` : trans('posts.show.title');
});

const getDisplayName = (pp: PostPlatform): string => pp.display_name ?? pp.platform;

const getDisplayUsername = (pp: PostPlatform): string | null => pp.display_username;

const getDisplayAvatar = (pp: PostPlatform): string | null => pp.display_avatar;

const formatDateTime = (date: string | null): string =>
    date ? dayjs.utc(date).local().format('D MMM YYYY, HH:mm') : '';

const mediaGridClass = computed(() => {
    const count = props.post.media.length;
    if (count <= 1) return 'grid-cols-1';
    if (count === 2) return 'grid-cols-2';
    return 'grid-cols-3';
});

const lightboxOpen = ref(false);
const lightboxIndex = ref(0);
const openLightbox = (i: number) => {
    lightboxIndex.value = i;
    lightboxOpen.value = true;
};

useEcho(`post.${props.post.id}`, '.PostPlatformStatusUpdated', () => {
    router.reload({ only: ['post'] });
});
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout full-width>
        <!-- Publishing state: clean centered loader, nothing else visible. -->
        <div v-if="isPublishing" class="flex flex-1 flex-col items-center justify-center gap-4 p-6">
            <div class="inline-flex size-14 -rotate-3 items-center justify-center rounded-2xl border-2 border-foreground bg-violet-200 shadow-2xs">
                <IconLoader2 class="size-7 animate-spin text-foreground" stroke-width="2" />
            </div>
            <p
                class="text-2xl font-semibold leading-tight text-foreground"
                style="font-family: var(--font-display)"
            >
                {{ $t('posts.edit.publishing_overlay_title') }}
            </p>
            <p class="max-w-md text-center text-sm text-foreground/70">
                {{ $t('posts.edit.publishing_overlay_subtitle') }}
            </p>
        </div>

        <div v-else class="grid flex-1 grid-cols-1 lg:grid-cols-2 lg:divide-x-2 lg:divide-foreground/10">
            <!-- LEFT: post preview -->
            <div class="border-b-2 border-foreground/10 p-6 lg:border-b-0">
                <div class="flex flex-col gap-4">
                    <!-- Status row -->
                    <div class="flex flex-wrap items-center gap-3">
                        <Badge :variant="postStatus.variant">
                            <component :is="postStatus.icon" class="size-3" />
                            {{ postStatus.label }}
                        </Badge>
                        <span class="flex items-center gap-1.5 text-sm font-medium text-foreground/70">
                            <IconCalendar class="size-4 text-foreground/60" />
                            <span v-if="post.published_at">
                                {{ $t('posts.show.published_on', { date: formatDateTime(post.published_at) }) }}
                            </span>
                            <span v-else-if="post.scheduled_at">
                                {{ $t('posts.show.scheduled_for', { date: formatDateTime(post.scheduled_at) }) }}
                            </span>
                            <span v-else>{{ $t('posts.show.draft') }}</span>
                        </span>
                    </div>

                    <!-- Post preview card -->
                    <Card class="overflow-hidden py-0">
                        <CardContent v-if="post.content" class="p-6">
                            <p class="whitespace-pre-wrap text-sm leading-relaxed text-foreground">{{ post.content }}</p>
                        </CardContent>

                        <div
                            v-if="post.media.length > 0"
                            :class="['grid gap-1 bg-foreground', mediaGridClass, post.content ? 'border-t-2 border-foreground/10' : '']"
                        >
                            <button
                                v-for="(item, i) in post.media"
                                :key="item.id"
                                type="button"
                                class="group relative aspect-square cursor-pointer overflow-hidden bg-muted transition-opacity hover:opacity-90"
                                @click="openLightbox(i)"
                            >
                                <video
                                    v-if="item.type === 'video' || item.mime_type?.startsWith('video/')"
                                    :src="item.url"
                                    class="size-full object-cover"
                                    muted
                                />
                                <img
                                    v-else
                                    :src="item.url"
                                    :alt="item.original_filename"
                                    class="size-full object-cover"
                                    loading="lazy"
                                />
                            </button>
                        </div>

                        <div
                            v-if="post.labels && post.labels.length > 0"
                            class="flex flex-wrap gap-2 border-t-2 border-foreground/10 px-6 py-3"
                        >
                            <LabelBadge
                                v-for="label in post.labels"
                                :key="label.id"
                                :label="label"
                            />
                        </div>
                    </Card>
                </div>
            </div>

            <!-- RIGHT: platforms breakdown -->
            <div class="flex flex-col gap-4 p-6">
                <!-- Summary stats -->
                <div v-if="enabledPlatforms.length > 0" class="grid grid-cols-3 gap-4">
                    <Card>
                        <CardContent class="p-6">
                            <p class="text-[11px] font-black uppercase tracking-widest text-foreground/60">
                                {{ $t('posts.show.summary.platforms') }}
                            </p>
                            <p class="mt-2 text-3xl font-bold tabular-nums tracking-tight text-foreground">
                                {{ enabledPlatforms.length }}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="p-6">
                            <p class="text-[11px] font-black uppercase tracking-widest text-foreground/60">
                                {{ $t('posts.show.summary.published') }}
                            </p>
                            <p class="mt-2 text-3xl font-bold tabular-nums tracking-tight text-foreground">
                                {{ publishedCount }}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="p-6">
                            <p class="text-[11px] font-black uppercase tracking-widest text-foreground/60">
                                {{ $t('posts.show.summary.failed') }}
                            </p>
                            <p class="mt-2 text-3xl font-bold tabular-nums tracking-tight text-foreground">
                                {{ failedCount }}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Platforms list -->
                <h2 class="text-[11px] font-black uppercase tracking-widest text-foreground/60">
                    {{ $t('posts.show.platforms') }}
                </h2>

                <Card v-if="enabledPlatforms.length === 0" class="py-0">
                    <CardContent class="p-8 text-center text-sm font-medium text-foreground/60">
                        {{ $t('posts.show.no_platforms') }}
                    </CardContent>
                </Card>

                <div v-else class="space-y-3">
                    <Card v-for="pp in enabledPlatforms" :key="pp.id" class="overflow-hidden py-0">
                        <CardContent class="p-0">
                            <div class="flex items-center gap-3 p-4">
                                <div class="relative shrink-0">
                                    <Avatar
                                        :src="getDisplayAvatar(pp)"
                                        :name="getDisplayName(pp)"
                                        class="size-11 rounded-full border-2 border-foreground shadow-2xs"
                                    />
                                    <span class="absolute -bottom-1 -right-1 inline-flex size-5 items-center justify-center overflow-hidden rounded-full border-2 border-foreground bg-card shadow-2xs">
                                        <img
                                            :src="getPlatformLogo(pp.platform)"
                                            :alt="pp.platform"
                                            class="size-full object-cover"
                                        />
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-bold text-foreground">{{ getDisplayName(pp) }}</p>
                                    <p class="truncate text-xs font-medium text-foreground/60">
                                        <span v-if="getDisplayUsername(pp)">@{{ getDisplayUsername(pp) }} · </span>
                                        {{ getPlatformLabel(pp.platform) }}
                                    </p>
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    <Badge :variant="getPlatformStatusConfig(pp.status).variant">
                                        <component
                                            :is="getPlatformStatusConfig(pp.status).icon"
                                            class="size-3"
                                            :class="pp.status === 'publishing' ? 'animate-spin' : ''"
                                        />
                                        {{ getPlatformStatusConfig(pp.status).label }}
                                    </Badge>
                                    <TooltipProvider v-if="pp.status === 'published' && pp.platform_url" :delay-duration="200">
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <a
                                                    :href="pp.platform_url"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="inline-flex size-8 cursor-pointer items-center justify-center rounded-full border-2 border-foreground bg-card text-foreground shadow-2xs transition-transform hover:rotate-3 hover:bg-violet-100"
                                                >
                                                    <IconExternalLink class="size-4" stroke-width="2.5" />
                                                </a>
                                            </TooltipTrigger>
                                            <TooltipContent>{{ $t('posts.show.view_on_platform') }}</TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </div>
                            </div>

                            <!-- Failed: error message -->
                            <div
                                v-if="pp.status === 'failed' && pp.error_message"
                                class="border-t-2 border-foreground/10 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700"
                            >
                                {{ pp.error_message }}
                            </div>

                            <!-- Published: metrics -->
                            <PostPlatformMetrics
                                v-if="pp.status === 'published'"
                                :post-id="post.id"
                                :post-platform-id="pp.id"
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- Lightbox -->
        <Teleport to="body">
            <div
                v-if="lightboxOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
                @click="lightboxOpen = false"
            >
                <button
                    type="button"
                    class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20"
                    @click.stop="lightboxOpen = false"
                >
                    <IconX class="size-5" />
                </button>
                <video
                    v-if="post.media[lightboxIndex]?.type === 'video' || post.media[lightboxIndex]?.mime_type?.startsWith('video/')"
                    :src="post.media[lightboxIndex]?.url"
                    class="max-h-full max-w-full"
                    controls
                    autoplay
                    @click.stop
                />
                <img
                    v-else
                    :src="post.media[lightboxIndex]?.url"
                    :alt="post.media[lightboxIndex]?.original_filename"
                    class="max-h-full max-w-full object-contain"
                    @click.stop
                />
            </div>
        </Teleport>
    </AppLayout>
</template>
