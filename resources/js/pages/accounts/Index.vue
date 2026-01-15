<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, ExternalLink, Trash2 } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { type BreadcrumbItemType } from '@/types';

interface SocialAccount {
    id: string;
    platform: string;
    username: string;
    display_name: string;
    avatar_url: string;
}

interface Platform {
    value: string;
    label: string;
    color: string;
    connected: boolean;
    account: SocialAccount | null;
}

interface Workspace {
    id: string;
    name: string;
}

interface Props {
    workspace: Workspace;
    platforms: Platform[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItemType[] = [
    {
        title: 'Workspaces',
        href: '/workspaces',
    },
    {
        title: props.workspace.name,
        href: `/workspaces/${props.workspace.id}`,
    },
    {
        title: 'Contas',
        href: `/workspaces/${props.workspace.id}/accounts`,
    },
];

const disconnect = (accountId: string) => {
    if (confirm('Tem certeza que deseja desconectar esta conta?')) {
        router.delete(`/workspaces/${props.workspace.id}/accounts/${accountId}`);
    }
};

const getPlatformLogo = (platform: string): string => {
    const logos: Record<string, string> = {
        'linkedin': '/images/accounts/linkedin.png',
        'linkedin-page': '/images/accounts/linkedin.png',
        'x': '/images/accounts/x.png',
        'tiktok': '/images/accounts/tiktok.png',
        'instagram': '/images/accounts/instagram.png',
        'facebook': '/images/accounts/facebook.png',
        'youtube': '/images/accounts/youtube.png',
        'threads': '/images/accounts/threads.png',
        'bluesky': '/images/accounts/bluesky.png',
        'pinterest': '/images/accounts/pinterest.png',
        'mastodon': '/images/accounts/mastodon.png',
    };
    return logos[platform] || '/images/accounts/linkedin.png';
};

const getProfileUrl = (platform: string, username: string | null): string | null => {
    if (!username) return null;

    const urls: Record<string, string> = {
        'linkedin': `https://linkedin.com/in/${username}`,
        'linkedin-page': `https://linkedin.com/company/${username}`,
        'x': `https://x.com/${username}`,
        'tiktok': `https://tiktok.com/@${username}`,
        'instagram': `https://instagram.com/${username}`,
        'facebook': `https://facebook.com/${username}`,
        'youtube': `https://youtube.com/@${username}`,
        'threads': `https://threads.net/@${username}`,
        'bluesky': `https://bsky.app/profile/${username}`,
        'pinterest': `https://pinterest.com/${username}`,
    };
    return urls[platform] || null;
};
</script>

<template>
    <Head title="Contas Conectadas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-8 p-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Contas Conectadas</h1>
                <p class="text-muted-foreground">
                    Conecte suas redes sociais para agendar e publicar posts
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <div
                    v-for="platform in platforms"
                    :key="platform.value"
                    class="group relative overflow-hidden rounded-xl border bg-card transition-all hover:shadow-md"
                    :class="platform.connected ? 'border-green-500/30 bg-green-50/50 dark:bg-green-950/20' : ''"
                >
                    <!-- Platform Header -->
                    <div class="flex items-center gap-3 p-4">
                        <div class="relative">
                            <img
                                :src="getPlatformLogo(platform.value)"
                                :alt="platform.label"
                                class="h-12 w-12 rounded-lg object-contain"
                            />
                            <div
                                v-if="platform.connected"
                                class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-green-500 text-white ring-2 ring-white dark:ring-gray-900"
                            >
                                <Check class="h-3 w-3" />
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold truncate">{{ platform.label }}</h3>
                            </div>
                            <p v-if="platform.connected && platform.account" class="text-sm text-muted-foreground truncate">
                                @{{ platform.account.username || platform.account.display_name }}
                            </p>
                            <p v-else class="text-sm text-muted-foreground">
                                Não conectado
                            </p>
                        </div>
                    </div>

                    <!-- Connected State -->
                    <div v-if="platform.connected && platform.account" class="border-t px-4 py-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Avatar class="h-8 w-8">
                                    <AvatarImage :src="platform.account.avatar_url" />
                                    <AvatarFallback class="text-xs">
                                        {{ platform.account.display_name?.charAt(0) }}
                                    </AvatarFallback>
                                </Avatar>
                                <span class="text-sm font-medium truncate max-w-[120px]">
                                    {{ platform.account.display_name }}
                                </span>
                            </div>
                            <div class="flex items-center gap-1">
                                <a
                                    v-if="getProfileUrl(platform.value, platform.account.username)"
                                    :href="getProfileUrl(platform.value, platform.account.username)!"
                                    target="_blank"
                                    class="p-2 text-muted-foreground hover:text-foreground transition-colors"
                                    title="Ver perfil"
                                >
                                    <ExternalLink class="h-4 w-4" />
                                </a>
                                <button
                                    @click="disconnect(platform.account.id)"
                                    class="p-2 text-muted-foreground hover:text-red-600 transition-colors"
                                    title="Desconectar"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Not Connected State -->
                    <div v-else class="border-t px-4 py-3">
                        <Link :href="`/workspaces/${workspace.id}/connect/${platform.value}`">
                            <Button variant="outline" class="w-full" size="sm">
                                Conectar
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
