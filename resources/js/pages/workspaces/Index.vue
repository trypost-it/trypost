<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Calendar, Users, Settings } from 'lucide-vue-next';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { accounts, calendar } from '@/routes';
import { settings } from '@/routes/workspace';
import { create as createWorkspace, index as workspacesIndex, switchMethod } from '@/routes/workspaces';
import { type BreadcrumbItemType } from '@/types';

interface Workspace {
    id: string;
    name: string;
    social_accounts_count: number;
    posts_count: number;
    created_at: string;
}

interface Props {
    workspaces: Workspace[];
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Workspaces', href: workspacesIndex.url() },
];

function switchAndNavigate(workspaceId: string, destination: string) {
    router.post(switchMethod.url(workspaceId), {}, {
        onSuccess: () => {
            router.visit(destination);
        },
    });
}
</script>

<template>
    <Head title="Workspaces" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Workspaces</h1>
                    <p class="text-muted-foreground">
                        Gerencie seus workspaces e redes sociais
                    </p>
                </div>
                <Link :href="createWorkspace.url()">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        Novo Workspace
                    </Button>
                </Link>
            </div>

            <div v-if="workspaces.length === 0" class="flex flex-col items-center justify-center rounded-lg border border-dashed p-12">
                <div class="mx-auto flex max-w-[420px] flex-col items-center justify-center text-center">
                    <h3 class="mt-4 text-lg font-semibold">Nenhum workspace</h3>
                    <p class="mb-4 mt-2 text-sm text-muted-foreground">
                        Crie seu primeiro workspace para começar a agendar posts.
                    </p>
                    <Link :href="createWorkspace.url()">
                        <Button>
                            <Plus class="mr-2 h-4 w-4" />
                            Criar Workspace
                        </Button>
                    </Link>
                </div>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card v-for="workspace in workspaces" :key="workspace.id" class="hover:bg-accent/50 transition-colors">
                    <CardHeader>
                        <CardTitle>{{ workspace.name }}</CardTitle>
                        <CardDescription>
                            {{ workspace.social_accounts_count }} redes conectadas
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4 text-sm text-muted-foreground">
                                <span class="flex items-center gap-1">
                                    <Calendar class="h-4 w-4" />
                                    {{ workspace.posts_count }} posts
                                </span>
                            </div>
                            <div class="flex gap-2">
                                <Button variant="outline" size="sm" @click="switchAndNavigate(workspace.id, calendar.url())">
                                    <Calendar class="h-4 w-4" />
                                </Button>
                                <Button variant="outline" size="sm" @click="switchAndNavigate(workspace.id, accounts.url())">
                                    <Users class="h-4 w-4" />
                                </Button>
                                <Button variant="outline" size="sm" @click="switchAndNavigate(workspace.id, settings.url())">
                                    <Settings class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
