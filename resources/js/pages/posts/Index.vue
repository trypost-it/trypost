<script setup lang="ts">
import { Head, Link, router, InfiniteScroll } from '@inertiajs/vue3';
import { IconClock, IconCircleCheck, IconAlertCircle, IconLoader2, IconFileText, IconPlus, IconEye, IconPencil, IconTrash } from '@tabler/icons-vue';
import { computed } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import dayjs from '@/dayjs';
import AppLayout from '@/layouts/AppLayout.vue';
import { calendar } from '@/routes';
import { index as postsIndex, create as createPost, show as showPost, edit as editPost, destroy as destroyPost } from '@/actions/App/Http/Controllers/PostController';
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

const statusLabels: Record<string, string> = {
    draft: 'Draft',
    scheduled: 'Scheduled',
    published: 'Published',
};

const pageTitle = computed(() => {
    if (props.currentStatus && statusLabels[props.currentStatus]) {
        return `Posts - ${statusLabels[props.currentStatus]}`;
    }
    return 'All Posts';
});

const pageDescription = computed(() => {
    if (props.currentStatus === 'draft') return 'Posts waiting to be scheduled';
    if (props.currentStatus === 'scheduled') return 'Posts scheduled for publishing';
    if (props.currentStatus === 'published') return 'Posts already published';
    return 'Manage all your posts';
});

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Posts', href: postsIndex.url() },
];

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
    const configs: Record<string, { label: string; color: string; icon: typeof IconFileText }> = {
        'draft': { label: 'Draft', color: 'bg-gray-100 text-gray-800', icon: IconFileText },
        'scheduled': { label: 'Scheduled', color: 'bg-blue-100 text-blue-800', icon: IconClock },
        'publishing': { label: 'Publishing', color: 'bg-yellow-100 text-yellow-800', icon: IconLoader2 },
        'published': { label: 'Published', color: 'bg-green-100 text-green-800', icon: IconCircleCheck },
        'partially_published': { label: 'Partially Published', color: 'bg-orange-100 text-orange-800', icon: IconAlertCircle },
        'failed': { label: 'Failed', color: 'bg-red-100 text-red-800', icon: IconAlertCircle },
    };
    return configs[status] || configs['draft'];
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
    if (enabledPlatforms.length === 0) return 'No content';
    const firstPlatform = enabledPlatforms[0];
    const content = firstPlatform.content || '';
    return content.length > 100 ? content.substring(0, 100) + '...' : content || 'No content';
};

const canEdit = (post: Post): boolean => {
    return ['draft', 'scheduled'].includes(post.status);
};

const handleDelete = (post: Post) => {
    if (confirm('Are you sure you want to delete this post?')) {
        router.delete(destroyPost.url(post.id));
    }
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
                    <Link :href="calendar.url()">
                        <Button variant="outline">
                            <IconClock class="mr-2 h-4 w-4" />
                            Calendar
                        </Button>
                    </Link>
                    <Link :href="createPost.url()">
                        <Button>
                            <IconPlus class="mr-2 h-4 w-4" />
                            New Post
                        </Button>
                    </Link>
                </div>
            </div>

            <div v-if="posts.data.length === 0">
                <Card>
                    <CardContent class="flex flex-col items-center justify-center py-12">
                        <IconFileText class="h-12 w-12 text-muted-foreground" />
                        <h3 class="mt-4 text-lg font-semibold">No posts found</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ currentStatus ? `No ${currentStatus} posts yet.` : 'Start by creating your first post.' }}
                        </p>
                        <Link v-if="!currentStatus" :href="createPost.url()" class="mt-4">
                            <Button>
                                <IconPlus class="mr-2 h-4 w-4" />
                                Create Post
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>

            <div v-else class="space-y-4">
                <Card v-for="post in posts.data" :key="post.id">
                    <CardContent class="p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-2">
                                    <Badge :class="getStatusConfig(post.status).color">
                                        <component :is="getStatusConfig(post.status).icon" class="mr-1 h-3 w-3" />
                                        {{ getStatusConfig(post.status).label }}
                                    </Badge>
                                    <span class="text-sm text-muted-foreground">
                                        {{ formatDateTime(post.scheduled_at) }}
                                    </span>
                                </div>

                                <p class="text-sm text-foreground line-clamp-2 mb-3">
                                    {{ getPostPreview(post) }}
                                </p>

                                <div class="flex items-center gap-2">
                                    <div class="flex -space-x-2">
                                        <img
                                            v-for="pp in getEnabledPlatforms(post).slice(0, 5)"
                                            :key="pp.id"
                                            :src="getPlatformLogo(pp.platform)"
                                            :alt="pp.platform"
                                            class="h-6 w-6 rounded-full ring-2 ring-background"
                                        />
                                    </div>
                                    <span v-if="getEnabledPlatforms(post).length > 5" class="text-xs text-muted-foreground">
                                        +{{ getEnabledPlatforms(post).length - 5 }}
                                    </span>
                                    <span class="text-xs text-muted-foreground ml-2">
                                        by {{ post.user.name }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center gap-1">
                                <Link :href="showPost.url(post.id)">
                                    <Button variant="ghost" size="icon">
                                        <IconEye class="h-4 w-4" />
                                    </Button>
                                </Link>
                                <Link v-if="canEdit(post)" :href="editPost.url(post.id)">
                                    <Button variant="ghost" size="icon">
                                        <IconPencil class="h-4 w-4" />
                                    </Button>
                                </Link>
                                <Button
                                    v-if="canEdit(post)"
                                    variant="ghost"
                                    size="icon"
                                    @click="handleDelete(post)"
                                >
                                    <IconTrash class="h-4 w-4 text-red-500" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <InfiniteScroll data="posts" #default="{ loading }">
                    <div v-if="loading" class="space-y-4">
                        <Card v-for="i in 3" :key="i">
                            <CardContent class="p-4">
                                <div class="flex items-start gap-4">
                                    <div class="flex-1 space-y-3">
                                        <div class="flex items-center gap-2">
                                            <Skeleton class="h-5 w-20" />
                                            <Skeleton class="h-4 w-32" />
                                        </div>
                                        <Skeleton class="h-4 w-full" />
                                        <Skeleton class="h-4 w-3/4" />
                                        <div class="flex items-center gap-2">
                                            <Skeleton class="h-6 w-6 rounded-full" />
                                            <Skeleton class="h-6 w-6 rounded-full" />
                                            <Skeleton class="h-4 w-24" />
                                        </div>
                                    </div>
                                    <div class="flex gap-1">
                                        <Skeleton class="h-8 w-8" />
                                        <Skeleton class="h-8 w-8" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </InfiniteScroll>
            </div>
        </div>
    </AppLayout>
</template>
