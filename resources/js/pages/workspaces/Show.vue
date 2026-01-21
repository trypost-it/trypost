<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { IconCalendar, IconUsers, IconFileText, IconCircleCheck, IconClock, IconAlertCircle, IconSettings } from '@tabler/icons-vue';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { accounts, calendar } from '@/routes';
import { settings } from '@/routes/workspace';
import { type BreadcrumbItemType } from '@/types';

interface SocialAccount {
    id: string;
    platform: string;
    username: string;
    display_name: string;
    avatar_url: string;
}

interface Workspace {
    id: string;
    name: string;
    social_accounts: SocialAccount[];
}

interface Stats {
    total_posts: number;
    scheduled_posts: number;
    published_posts: number;
    connected_accounts: number;
}

interface Props {
    workspace: Workspace;
    stats: Stats;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Calendar', href: calendar.url() },
];
</script>

<template>
    <Head :title="workspace.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ workspace.name }}</h1>
                    <p class="text-muted-foreground">
                        Workspace dashboard
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link :href="settings.url()">
                        <Button variant="ghost" size="icon">
                            <IconSettings class="h-4 w-4" />
                        </Button>
                    </Link>
                    <Link :href="accounts.url()">
                        <Button variant="outline">
                            <IconUsers class="mr-2 h-4 w-4" />
                            Accounts
                        </Button>
                    </Link>
                    <Link :href="calendar.url()">
                        <Button>
                            <IconCalendar class="mr-2 h-4 w-4" />
                            Calendar
                        </Button>
                    </Link>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total de Posts</CardTitle>
                        <IconFileText class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_posts }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Agendados</CardTitle>
                        <IconClock class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.scheduled_posts }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Publicados</CardTitle>
                        <IconCircleCheck class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.published_posts }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Contas Conectadas</CardTitle>
                        <IconUsers class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.connected_accounts }}</div>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="workspace.social_accounts.length === 0">
                <CardContent class="flex flex-col items-center justify-center py-12">
                    <IconAlertCircle class="h-12 w-12 text-muted-foreground" />
                    <h3 class="mt-4 text-lg font-semibold">Nenhuma conta conectada</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Conecte suas redes sociais para começar a agendar posts.
                    </p>
                    <Link :href="accounts.url()" class="mt-4">
                        <Button>
                            <IconUsers class="mr-2 h-4 w-4" />
                            Conectar Contas
                        </Button>
                    </Link>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
