<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Clock, CheckCircle, AlertCircle, Loader2, FileText, Plus, Eye, Pencil, Trash2 } from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import dayjs from '@/dayjs';
import AppLayout from '@/layouts/AppLayout.vue';
import { calendar } from '@/routes';
import { index as postsIndex, create as createPost, show as showPost, edit as editPost, destroy as destroyPost } from '@/routes/posts';
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

interface PaginatedPosts {
    data: Post[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
    };
}

interface Workspace {
    id: string;
    name: string;
    timezone: string;
}

interface Props {
    workspace: Workspace;
    posts: PaginatedPosts;
}

const props = defineProps<Props>();

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
    const configs: Record<string, { label: string; color: string; icon: typeof FileText }> = {
        'draft': { label: 'Rascunho', color: 'bg-gray-100 text-gray-800', icon: FileText },
        'scheduled': { label: 'Agendado', color: 'bg-blue-100 text-blue-800', icon: Clock },
        'publishing': { label: 'Publicando', color: 'bg-yellow-100 text-yellow-800', icon: Loader2 },
        'published': { label: 'Publicado', color: 'bg-green-100 text-green-800', icon: CheckCircle },
        'partially_published': { label: 'Parcialmente Publicado', color: 'bg-orange-100 text-orange-800', icon: AlertCircle },
        'failed': { label: 'Falhou', color: 'bg-red-100 text-red-800', icon: AlertCircle },
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
    if (enabledPlatforms.length === 0) return 'Sem conteudo';
    const firstPlatform = enabledPlatforms[0];
    const content = firstPlatform.content || '';
    return content.length > 100 ? content.substring(0, 100) + '...' : content || 'Sem conteudo';
};

const canEdit = (post: Post): boolean => {
    return ['draft', 'scheduled'].includes(post.status);
};

const handleDelete = (post: Post) => {
    if (confirm('Tem certeza que deseja excluir este post?')) {
        router.delete(destroyPost.url(post.id));
    }
};
</script>

<template>
    <Head title="Posts" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Posts</h1>
                    <p class="text-muted-foreground">
                        Gerencie todos os seus posts
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link :href="calendar.url()">
                        <Button variant="outline">
                            <Clock class="mr-2 h-4 w-4" />
                            Calendario
                        </Button>
                    </Link>
                    <Link :href="createPost.url()">
                        <Button>
                            <Plus class="mr-2 h-4 w-4" />
                            Novo Post
                        </Button>
                    </Link>
                </div>
            </div>

            <div v-if="posts.data.length === 0">
                <Card>
                    <CardContent class="flex flex-col items-center justify-center py-12">
                        <FileText class="h-12 w-12 text-muted-foreground" />
                        <h3 class="mt-4 text-lg font-semibold">Nenhum post ainda</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Comece criando seu primeiro post.
                        </p>
                        <Link :href="createPost.url()" class="mt-4">
                            <Button>
                                <Plus class="mr-2 h-4 w-4" />
                                Criar Post
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
                                        por {{ post.user.name }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center gap-1">
                                <Link :href="showPost.url(post.id)">
                                    <Button variant="ghost" size="icon">
                                        <Eye class="h-4 w-4" />
                                    </Button>
                                </Link>
                                <Link v-if="canEdit(post)" :href="editPost.url(post.id)">
                                    <Button variant="ghost" size="icon">
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                </Link>
                                <Button
                                    v-if="canEdit(post)"
                                    variant="ghost"
                                    size="icon"
                                    @click="handleDelete(post)"
                                >
                                    <Trash2 class="h-4 w-4 text-red-500" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div v-if="posts.meta.last_page > 1" class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">
                        Mostrando {{ posts.meta.from }} a {{ posts.meta.to }} de {{ posts.meta.total }} posts
                    </p>
                    <div class="flex gap-2">
                        <Link
                            v-if="posts.links.prev"
                            :href="posts.links.prev"
                            preserve-scroll
                        >
                            <Button variant="outline" size="sm">
                                Anterior
                            </Button>
                        </Link>
                        <Link
                            v-if="posts.links.next"
                            :href="posts.links.next"
                            preserve-scroll
                        >
                            <Button variant="outline" size="sm">
                                Proximo
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
