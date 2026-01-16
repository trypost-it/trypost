<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { Clock, CheckCircle, AlertCircle, Loader2, FileText, ExternalLink, Pencil } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import dayjs from '@/dayjs';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { type BreadcrumbItemType } from '@/types';

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

interface PostPlatform {
    id: string;
    social_account_id: string;
    enabled: boolean;
    platform: string;
    content: string;
    status: string;
    platform_url: string | null;
    error_message: string | null;
    published_at: string | null;
    social_account: SocialAccount;
    media: MediaItem[];
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

interface Workspace {
    id: string;
    name: string;
    timezone: string;
}

interface Props {
    workspace: Workspace;
    post: Post;
}

const props = defineProps<Props>();

// Reactive state for real-time updates
const post = ref(props.post);

// Listen for status updates via WebSocket
useEcho(
    `posts.${props.post.id}`,
    'PostPlatformStatusUpdated',
    (e: { post_platform: { id: string; status: string; platform_url: string | null; error_message: string | null; published_at: string | null }; post: { id: string; status: string; published_at: string | null } }) => {
        // Update post platform status
        const platformIndex = post.value.post_platforms.findIndex(pp => pp.id === e.post_platform.id);
        if (platformIndex !== -1) {
            post.value.post_platforms[platformIndex].status = e.post_platform.status;
            post.value.post_platforms[platformIndex].platform_url = e.post_platform.platform_url;
            post.value.post_platforms[platformIndex].error_message = e.post_platform.error_message;
            post.value.post_platforms[platformIndex].published_at = e.post_platform.published_at;
        }

        // Update post status
        post.value.status = e.post.status;
        post.value.published_at = e.post.published_at;
    },
);

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Workspaces', href: '/workspaces' },
    { title: props.workspace.name, href: `/workspaces/${props.workspace.id}` },
    { title: 'Calendar', href: `/workspaces/${props.workspace.id}/calendar` },
    { title: 'Post', href: '#' },
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
    };
    return logos[platform] || '/images/accounts/default.png';
};

const getPlatformLabel = (platform: string): string => {
    const labels: Record<string, string> = {
        'linkedin': 'LinkedIn',
        'linkedin-page': 'LinkedIn Page',
        'x': 'X',
        'tiktok': 'TikTok',
        'youtube': 'YouTube Shorts',
        'facebook': 'Facebook Page',
        'instagram': 'Instagram',
        'threads': 'Threads',
    };
    return labels[platform] || platform;
};

const getStatusConfig = (status: string) => {
    const configs: Record<string, { label: string; color: string; icon: any }> = {
        'draft': { label: 'Draft', color: 'bg-gray-100 text-gray-800', icon: FileText },
        'scheduled': { label: 'Scheduled', color: 'bg-blue-100 text-blue-800', icon: Clock },
        'publishing': { label: 'Publishing', color: 'bg-yellow-100 text-yellow-800', icon: Loader2 },
        'published': { label: 'Published', color: 'bg-green-100 text-green-800', icon: CheckCircle },
        'partially_published': { label: 'Partially Published', color: 'bg-orange-100 text-orange-800', icon: AlertCircle },
        'failed': { label: 'Failed', color: 'bg-red-100 text-red-800', icon: AlertCircle },
    };
    return configs[status] || configs['draft'];
};

const formatDateTime = (date: string | null): string => {
    if (!date) return '-';
    return dayjs.utc(date).tz(props.workspace.timezone).format('MMM D, YYYY [at] h:mm A');
};

const canEdit = computed(() => ['draft', 'scheduled'].includes(post.value.status));
const enabledPlatforms = computed(() => post.value.post_platforms.filter(pp => pp.enabled));
</script>

<template>
    <Head title="Post Details" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Post Details</h1>
                    <p class="text-muted-foreground">
                        Created by {{ post.user.name }}
                    </p>
                </div>
                <Link v-if="canEdit" :href="`/workspaces/${workspace.id}/posts/${post.id}/edit`">
                    <Button>
                        <Pencil class="mr-2 h-4 w-4" />
                        Edit Post
                    </Button>
                </Link>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Platform Posts -->
                    <Card v-for="pp in enabledPlatforms" :key="pp.id">
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="relative shrink-0">
                                        <img
                                            v-if="pp.social_account.avatar_url"
                                            :src="pp.social_account.avatar_url"
                                            :alt="pp.social_account.display_name"
                                            class="h-10 w-10 rounded-full object-cover"
                                        />
                                        <div v-else class="h-10 w-10 rounded-full bg-muted flex items-center justify-center">
                                            <span class="text-sm font-medium">{{ pp.social_account.display_name?.charAt(0) }}</span>
                                        </div>
                                        <img
                                            :src="getPlatformLogo(pp.platform)"
                                            :alt="pp.platform"
                                            class="absolute -bottom-1 -right-1 h-5 w-5 rounded-full ring-2 ring-background"
                                        />
                                    </div>
                                    <div>
                                        <CardTitle class="text-base">{{ pp.social_account.display_name }}</CardTitle>
                                        <CardDescription>{{ getPlatformLabel(pp.platform) }}</CardDescription>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Badge :class="getStatusConfig(pp.status).color">
                                        <component :is="getStatusConfig(pp.status).icon" class="mr-1 h-3 w-3" />
                                        {{ getStatusConfig(pp.status).label }}
                                    </Badge>
                                    <a
                                        v-if="pp.platform_url"
                                        :href="pp.platform_url"
                                        target="_blank"
                                        class="text-muted-foreground hover:text-foreground"
                                    >
                                        <ExternalLink class="h-4 w-4" />
                                    </a>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- Content -->
                            <div class="whitespace-pre-wrap text-sm">
                                {{ pp.content || 'No content' }}
                            </div>

                            <!-- Media -->
                            <div v-if="pp.media.length > 0" class="flex flex-wrap gap-2">
                                <div
                                    v-for="media in pp.media"
                                    :key="media.id"
                                    class="w-24 h-24 rounded-lg overflow-hidden border bg-muted"
                                >
                                    <img
                                        v-if="media.type === 'image'"
                                        :src="media.url"
                                        :alt="media.original_filename"
                                        class="w-full h-full object-cover"
                                    />
                                    <div v-else class="w-full h-full flex items-center justify-center text-xs text-muted-foreground">
                                        {{ media.type }}
                                    </div>
                                </div>
                            </div>

                            <!-- Error Message -->
                            <div v-if="pp.error_message" class="p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700">
                                <p class="font-medium">Error:</p>
                                <p class="mt-1">{{ pp.error_message }}</p>
                            </div>

                            <!-- Published Info -->
                            <div v-if="pp.published_at" class="text-xs text-muted-foreground">
                                Published {{ formatDateTime(pp.published_at) }}
                            </div>
                        </CardContent>
                    </Card>

                    <Card v-if="enabledPlatforms.length === 0">
                        <CardContent class="py-8 text-center text-muted-foreground">
                            No platforms selected for this post.
                        </CardContent>
                    </Card>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Status</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center gap-2">
                                <Badge :class="getStatusConfig(post.status).color" class="text-sm">
                                    <component :is="getStatusConfig(post.status).icon" class="mr-1 h-4 w-4" />
                                    {{ getStatusConfig(post.status).label }}
                                </Badge>
                            </div>

                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Scheduled</span>
                                    <span>{{ formatDateTime(post.scheduled_at) }}</span>
                                </div>
                                <div v-if="post.published_at" class="flex justify-between">
                                    <span class="text-muted-foreground">Published</span>
                                    <span>{{ formatDateTime(post.published_at) }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Platforms</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-2">
                                <div
                                    v-for="pp in enabledPlatforms"
                                    :key="pp.id"
                                    class="flex items-center justify-between text-sm"
                                >
                                    <div class="flex items-center gap-2">
                                        <img :src="getPlatformLogo(pp.platform)" class="h-4 w-4" />
                                        <span>{{ pp.social_account.display_name }}</span>
                                    </div>
                                    <Badge variant="outline" :class="getStatusConfig(pp.status).color" class="text-xs">
                                        {{ getStatusConfig(pp.status).label }}
                                    </Badge>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
