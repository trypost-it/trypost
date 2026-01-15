<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Youtube, ArrowLeft, Check, Users } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { type BreadcrumbItemType } from '@/types';

interface Channel {
    id: string;
    title: string;
    description: string;
    thumbnail: string | null;
    custom_url: string | null;
    subscriber_count: number | string;
}

interface Workspace {
    id: string;
    name: string;
}

interface Props {
    workspace: Workspace;
    channels: Channel[];
    error?: string;
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
        title: 'Accounts',
        href: `/workspaces/${props.workspace.id}/accounts`,
    },
    {
        title: 'Select Channel',
        href: '#',
    },
];

const selectChannel = (channel: Channel) => {
    router.post('/accounts/youtube/select', {
        channel_id: channel.id,
    });
};

const goBack = () => {
    router.visit(`/workspaces/${props.workspace.id}/accounts`);
};

const formatSubscribers = (count: number | string) => {
    const num = typeof count === 'string' ? parseInt(count) : count;
    if (num >= 1000000) {
        return `${(num / 1000000).toFixed(1)}M subscribers`;
    }
    if (num >= 1000) {
        return `${(num / 1000).toFixed(1)}K subscribers`;
    }
    return `${num} subscribers`;
};
</script>

<template>
    <Head title="Select YouTube Channel" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-8 p-6">
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="icon" @click="goBack" class="shrink-0">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-600">
                        <Youtube class="h-6 w-6 text-white" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">Select YouTube Channel</h1>
                        <p class="text-muted-foreground">
                            Choose which channel you want to connect for Shorts
                        </p>
                    </div>
                </div>
            </div>

            <Alert v-if="error" variant="destructive">
                <AlertDescription>{{ error }}</AlertDescription>
            </Alert>

            <div v-if="channels.length === 0 && !error" class="text-center py-16">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                    <Youtube class="h-8 w-8 text-muted-foreground" />
                </div>
                <h3 class="mt-4 text-lg font-semibold">No channels found</h3>
                <p class="mt-1 text-muted-foreground">
                    You don't have any YouTube channels associated with your account.
                </p>
                <Button class="mt-6" @click="goBack">
                    Go Back
                </Button>
            </div>

            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="channel in channels"
                    :key="channel.id"
                    @click="selectChannel(channel)"
                    class="group relative overflow-hidden rounded-xl border bg-card p-4 text-left transition-all hover:border-primary hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    <div class="flex items-center gap-4">
                        <Avatar class="h-14 w-14 rounded-full">
                            <AvatarImage v-if="channel.thumbnail" :src="channel.thumbnail" class="object-cover" />
                            <AvatarFallback class="bg-red-100 dark:bg-red-900">
                                <Youtube class="h-7 w-7 text-red-600 dark:text-red-400" />
                            </AvatarFallback>
                        </Avatar>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold truncate group-hover:text-primary transition-colors">
                                {{ channel.title }}
                            </h3>
                            <p v-if="channel.custom_url" class="text-sm text-muted-foreground truncate">
                                youtube.com/{{ channel.custom_url }}
                            </p>
                            <div class="flex items-center gap-1 text-sm text-muted-foreground mt-1">
                                <Users class="h-3 w-3" />
                                {{ formatSubscribers(channel.subscriber_count) }}
                            </div>
                        </div>
                        <div class="shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                <Check class="h-4 w-4" />
                            </div>
                        </div>
                    </div>
                </button>
            </div>
        </div>
    </AppLayout>
</template>
