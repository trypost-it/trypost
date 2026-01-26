<script setup lang="ts">
import { Head, Link, InfiniteScroll } from '@inertiajs/vue3';
import { IconClock, IconCircleCheck, IconAlertCircle, IconLoader2, IconFileText, IconPlus, IconEye, IconTrash } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

import { index as postsIndex, store as storePost, edit as editPost, destroy as destroyPost } from '@/actions/App/Http/Controllers/PostController';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import dayjs from '@/dayjs';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItemType } from '@/types';

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    avatar_url: string | null;
}

interface PostPlatform {
    id: string;
    social_account_id: string;
    enabled: boolean;
    platform: string;
    content: string;
    status: string;
    social_account: SocialAccount;
}

interface User {
    id: string;
    name: string;
}

interface Post {
    id: string;
    status: string;
    scheduled_at: string | null;
    published_at: string | null;
    post_platforms: PostPlatform[];
    user: User;
}

interface ScrollPosts {
    data: Post[];
    meta: {
        hasNextPage: boolean;
    };
}

interface Workspace {
    id: string;
    name: string;
    timezone: string;
}

interface Props {
    workspace: Workspace;
    posts: ScrollPosts;
    currentStatus: string | null;
}

const props = defineProps<Props>();

const pageTitle = computed(() => {
    if (props.currentStatus) {
        const statusLabel = trans(`posts.status.${props.currentStatus}`);
        return `${trans('posts.title')} - ${statusLabel}`;
    }
    return trans('posts.all_posts');
});

const pageDescription = computed(() => {
    if (props.currentStatus === 'draft') return trans('posts.descriptions.draft');
    if (props.currentStatus === 'scheduled') return trans('posts.descriptions.scheduled');
    if (props.currentStatus === 'published') return trans('posts.descriptions.published');
    return trans('posts.manage_posts');
});

const breadcrumbs = computed<BreadcrumbItemType[]>(() => [
    { title: trans('posts.title'), href: postsIndex.url() },
]);

const getPlatformLogo = (platform: string): string => {
    const logos: Record<string, string> = {
        'linkedin': '/images/accounts/linkedin.png',
        'linkedin-page': '/images/accounts/linkedin.png',
        'x': '/images/accounts/x.png',
        'tiktok': '/images/accounts/tiktok.png',
        'youtube': '/images/accounts/youtube.png',
        'facebook': '/images/accounts/facebook.png',
        'instagram': '/images/accounts/instagram.png',
        'threads': '/images/accounts/threads.png',
        'pinterest': '/images/accounts/pinterest.png',
        'bluesky': '/images/accounts/bluesky.png',
        'mastodon': '/images/accounts/mastodon.png',
    };
    return logos[platform] || '/images/accounts/default.png';
};

const getStatusConfig = (status: string) => {
    const configs: Record<string, { color: string; icon: typeof IconFileText }> = {
        'draft': { color: 'bg-neutral-100 text-neutral-800', icon: IconFileText },
        'scheduled': { color: 'bg-blue-100 text-blue-800', icon: IconClock },
        'publishing': { color: 'bg-yellow-100 text-yellow-800', icon: IconLoader2 },
        'published': { color: 'bg-green-100 text-green-800', icon: IconCircleCheck },
        'partially_published': { color: 'bg-orange-100 text-orange-800', icon: IconAlertCircle },
        'failed': { color: 'bg-red-100 text-red-800', icon: IconAlertCircle },
    };
    const config = configs[status] || configs['draft'];
    return {
        ...config,
        label: trans(`posts.status.${status}`),
    };
};

const formatDateTime = (date: string | null): string => {
    if (!date) return '-';
    return dayjs.utc(date).tz(props.workspace.timezone).format('D MMM YYYY, HH:mm');
};

const getEnabledPlatforms = (post: Post) => {
    return post.post_platforms.filter(pp => pp.enabled);
};

const getPostPreview = (post: Post): string => {
    const enabledPlatforms = getEnabledPlatforms(post);
    const noContent = trans('calendar.no_content');
    if (enabledPlatforms.length === 0) return noContent;
    const firstPlatform = enabledPlatforms[0];
    const content = firstPlatform.content || '';
    return content.length > 100 ? content.substring(0, 100) + '...' : content || noContent;
};

const canEdit = (post: Post): boolean => {
    return ['draft', 'scheduled'].includes(post.status);
};

const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);

const handleDelete = (post: Post) => {
    deleteModal.value?.open({
        url: destroyPost.url(post.id),
    });
};
</script>

<template>

    <Head :title="pageTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ pageTitle }}</h1>
                    <p class="text-muted-foreground">
                        {{ pageDescription }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link :href="storePost.url()" method="post">
                        <Button>
                            {{ $t('posts.new_post') }}
                        </Button>
                    </Link>
                </div>
            </div>

            <div v-if="posts.data.length === 0">
                <Card>
                    <CardContent class="flex flex-col items-center justify-center py-12">
                        <IconFileText class="h-12 w-12 text-muted-foreground" />
                        <h3 class="mt-4 text-lg font-semibold">{{ $t('posts.no_posts') }}</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ currentStatus ? $t('posts.no_posts_status', {
                                status: $t(`posts.status.${currentStatus}`)
                            }) : $t('posts.start_creating') }}
                        </p>
                        <Link v-if="!currentStatus" :href="storePost.url()" method="post" class="mt-4">
                            <Button>
                                <IconPlus class="h-4 w-4" />
                                {{ $t('posts.create_post') }}
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>

            <div v-else>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Card v-for="post in posts.data" :key="post.id" class="flex flex-col py-0">
                        <CardContent class="p-4 flex-1 flex flex-col">
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <Badge :class="getStatusConfig(post.status).color">
                                    <component :is="getStatusConfig(post.status).icon" class="mr-1 h-3 w-3" />
                                    {{ getStatusConfig(post.status).label }}
                                </Badge>
                                <span class="text-xs text-muted-foreground">
                                    {{ formatDateTime(post.scheduled_at) }}
                                </span>
                            </div>

                            <p class="text-sm text-foreground line-clamp-3 mb-4 flex-1">
                                {{ getPostPreview(post) }}
                            </p>

                            <div class="flex items-center justify-between mt-auto">
                                <div class="flex items-center gap-2">
                                    <div class="flex -space-x-2">
                                        <img v-for="pp in getEnabledPlatforms(post).slice(0, 4)" :key="pp.id"
                                            :src="getPlatformLogo(pp.platform)" :alt="pp.platform"
                                            class="h-6 w-6 rounded-full ring-2 ring-background" />
                                    </div>
                                    <span v-if="getEnabledPlatforms(post).length > 4"
                                        class="text-xs text-muted-foreground">
                                        +{{ getEnabledPlatforms(post).length - 4 }}
                                    </span>
                                </div>

                                <TooltipProvider>
                                    <div class="flex items-center gap-1">
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Link :href="editPost.url(post.id)">
                                                    <Button variant="ghost" size="icon" class="h-8 w-8">
                                                        <IconEye class="h-4 w-4" />
                                                    </Button>
                                                </Link>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                {{ $t('posts.actions.view') }}
                                            </TooltipContent>
                                        </Tooltip>
                                        <Tooltip v-if="canEdit(post)">
                                            <TooltipTrigger asChild>
                                                <Button variant="ghost" size="icon" class="h-8 w-8"
                                                    @click="handleDelete(post)">
                                                    <IconTrash class="h-4 w-4 text-red-500" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                {{ $t('posts.actions.delete') }}
                                            </TooltipContent>
                                        </Tooltip>
                                    </div>
                                </TooltipProvider>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <InfiniteScroll data="posts" #default="{ loading }">
                    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                        <Card v-for="i in 3" :key="i">
                            <CardContent class="p-4">
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <Skeleton class="h-5 w-20" />
                                        <Skeleton class="h-4 w-24" />
                                    </div>
                                    <Skeleton class="h-4 w-full" />
                                    <Skeleton class="h-4 w-full" />
                                    <Skeleton class="h-4 w-3/4" />
                                    <div class="flex items-center justify-between pt-2">
                                        <div class="flex -space-x-2">
                                            <Skeleton class="h-6 w-6 rounded-full" />
                                            <Skeleton class="h-6 w-6 rounded-full" />
                                            <Skeleton class="h-6 w-6 rounded-full" />
                                        </div>
                                        <div class="flex gap-1">
                                            <Skeleton class="h-8 w-8" />
                                            <Skeleton class="h-8 w-8" />
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </InfiniteScroll>
            </div>
        </div>
    </AppLayout>

    <ConfirmDeleteModal ref="deleteModal" :title="$t('posts.edit.delete_modal.title')"
        :description="$t('posts.edit.delete_modal.description')" :action="$t('posts.edit.delete_modal.action')"
        :cancel="$t('posts.edit.delete_modal.cancel')" />
</template>