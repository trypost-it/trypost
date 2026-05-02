<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { IconCalendar, IconExternalLink, IconLoader2, IconX } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

import PostPlatformMetrics from '@/components/posts/PostPlatformMetrics.vue';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { getPlatformLabel, getPlatformLogo } from '@/composables/usePlatformLogo';
import { getPlatformStatusConfig, getPostStatusConfig } from '@/composables/usePostStatus';
import dayjs from '@/dayjs';
import AppLayout from '@/layouts/AppLayout.vue';

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
    platform_name: string | null;
    platform_username: string | null;
    platform_avatar: string | null;
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
    post_platforms: PostPlatform[];
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

const enabledPlatforms = computed(() => props.post.post_platforms.filter((pp) => pp.enabled));
const publishedCount = computed(() => enabledPlatforms.value.filter((pp) => pp.status === 'published').length);
const failedCount = computed(() => enabledPlatforms.value.filter((pp) => pp.status === 'failed').length);

const isPublishing = computed(() => props.post.status === 'publishing');

const postStatus = computed(() => getPostStatusConfig(props.post.status));

const pageTitle = computed(() => {
    const snippet = props.post.content?.trim().split('\n')[0]?.slice(0, 60) ?? '';
    return snippet ? `${trans('posts.show.title')} · ${snippet}${props.post.content.length > 60 ? '…' : ''}` : trans('posts.show.title');
});

const getDisplayName = (pp: PostPlatform): string =>
    pp.social_account?.display_name ?? pp.platform_name ?? pp.platform;

const getDisplayUsername = (pp: PostPlatform): string | null =>
    pp.social_account?.username ?? pp.platform_username;

const getDisplayAvatar = (pp: PostPlatform): string | null =>
    pp.social_account?.avatar_url ?? pp.platform_avatar;

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

    <AppLayout :title="pageTitle" full-width>
        <!-- Publishing state: clean centered loader, nothing else visible. -->
        <div v-if="isPublishing" class="flex flex-1 flex-col items-center justify-center gap-3 p-6">
            <IconLoader2 class="h-10 w-10 animate-spin text-primary" />
            <p class="text-base font-medium">{{ $t('posts.edit.publishing_overlay_title') }}</p>
            <p class="max-w-md text-center text-sm text-muted-foreground">
                {{ $t('posts.edit.publishing_overlay_subtitle') }}
            </p>
        </div>

        <div v-else class="grid flex-1 grid-cols-1 lg:grid-cols-2 lg:divide-x">
            <!-- LEFT: post preview -->
            <div class="border-b p-6 lg:border-b-0">
                <div class="flex flex-col gap-4">
                    <!-- Status row -->
                    <div class="flex flex-wrap items-center gap-3">
                        <Badge :class="postStatus.color">
                            <component :is="postStatus.icon" class="mr-1 h-3 w-3" />
                            {{ postStatus.label }}
                        </Badge>
                        <span class="flex items-center gap-1.5 text-sm text-muted-foreground">
                            <IconCalendar class="h-4 w-4" />
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
                            <p class="whitespace-pre-wrap text-sm leading-relaxed">{{ post.content }}</p>
                        </CardContent>

                        <div
                            v-if="post.media.length > 0"
                            :class="['grid gap-1 bg-black', mediaGridClass, post.content ? 'border-t' : '']"
                        >
                            <button
                                v-for="(item, i) in post.media"
                                :key="item.id"
                                type="button"
                                class="group relative aspect-square overflow-hidden bg-muted transition-opacity hover:opacity-90"
                                @click="openLightbox(i)"
                            >
                                <video
                                    v-if="item.type === 'video' || item.mime_type?.startsWith('video/')"
                                    :src="item.url"
                                    class="h-full w-full object-cover"
                                    muted
                                />
                                <img
                                    v-else
                                    :src="item.url"
                                    :alt="item.original_filename"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                />
                            </button>
                        </div>

                        <div
                            v-if="post.labels && post.labels.length > 0"
                            class="flex flex-wrap gap-2 border-t bg-muted/30 px-6 py-3"
                        >
                            <span
                                v-for="label in post.labels"
                                :key="label.id"
                                class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs font-medium"
                                :style="{ backgroundColor: label.color + '20', color: label.color }"
                            >
                                {{ label.name }}
                            </span>
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
                            <p class="text-sm text-muted-foreground">{{ $t('posts.show.summary.platforms') }}</p>
                            <p class="mt-2 text-3xl font-bold tracking-tight tabular-nums">{{ enabledPlatforms.length }}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="p-6">
                            <p class="text-sm text-muted-foreground">{{ $t('posts.show.summary.published') }}</p>
                            <p class="mt-2 text-3xl font-bold tracking-tight tabular-nums">{{ publishedCount }}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="p-6">
                            <p class="text-sm text-muted-foreground">{{ $t('posts.show.summary.failed') }}</p>
                            <p class="mt-2 text-3xl font-bold tracking-tight tabular-nums">{{ failedCount }}</p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Platforms list -->
                <h2 class="text-sm font-semibold">{{ $t('posts.show.platforms') }}</h2>

                <Card v-if="enabledPlatforms.length === 0" class="py-0">
                    <CardContent class="p-8 text-center text-sm text-muted-foreground">
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
                                        class="size-11 rounded-full"
                                    />
                                    <img
                                        :src="getPlatformLogo(pp.platform)"
                                        :alt="pp.platform"
                                        class="absolute -bottom-0.5 -right-0.5 size-5 rounded-full bg-background object-contain ring-2 ring-card"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold">{{ getDisplayName(pp) }}</p>
                                    <p class="truncate text-xs text-muted-foreground">
                                        <span v-if="getDisplayUsername(pp)">@{{ getDisplayUsername(pp) }} · </span>
                                        {{ getPlatformLabel(pp.platform) }}
                                    </p>
                                </div>

                                <Badge :class="getPlatformStatusConfig(pp.status).color" class="shrink-0">
                                    <component
                                        :is="getPlatformStatusConfig(pp.status).icon"
                                        class="mr-1 h-3 w-3"
                                        :class="pp.status === 'publishing' ? 'animate-spin' : ''"
                                    />
                                    {{ getPlatformStatusConfig(pp.status).label }}
                                </Badge>
                            </div>

                            <!-- Failed: error message -->
                            <div
                                v-if="pp.status === 'failed' && pp.error_message"
                                class="border-t bg-destructive/5 px-4 py-3 text-xs text-destructive"
                            >
                                {{ pp.error_message }}
                            </div>

                            <!-- Published: metrics + footer -->
                            <template v-if="pp.status === 'published'">
                                <PostPlatformMetrics
                                    :post-id="post.id"
                                    :post-platform-id="pp.id"
                                />

                                <div class="flex items-center justify-between border-t bg-muted/20 px-4 py-2.5 text-xs">
                                    <span v-if="pp.published_at" class="text-muted-foreground">
                                        {{ formatDateTime(pp.published_at) }}
                                    </span>
                                    <a
                                        v-if="pp.platform_url"
                                        :href="pp.platform_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                                    >
                                        {{ $t('posts.show.view_on_platform') }}
                                        <IconExternalLink class="size-3.5" />
                                    </a>
                                </div>
                            </template>
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
