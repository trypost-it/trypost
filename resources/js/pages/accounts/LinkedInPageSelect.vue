<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Building2, ArrowLeft, Check } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { type BreadcrumbItemType } from '@/types';

interface Organization {
    id: string;
    name: string;
    vanity_name: string | null;
    logo: string | null;
}

interface Workspace {
    id: string;
    name: string;
}

interface Props {
    workspace: Workspace;
    organizations: Organization[];
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
        title: 'Contas',
        href: `/workspaces/${props.workspace.id}/accounts`,
    },
    {
        title: 'Selecionar Página',
        href: '#',
    },
];

const selectPage = (org: Organization) => {
    router.post('/accounts/linkedin-page/select', {
        organization_id: org.id,
        organization_name: org.name,
        organization_vanity_name: org.vanity_name,
        organization_logo: org.logo,
    });
};

const goBack = () => {
    router.visit(`/workspaces/${props.workspace.id}/accounts`);
};
</script>

<template>
    <Head title="Selecionar LinkedIn Page" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-8 p-6">
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="icon" @click="goBack" class="shrink-0">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
                <div class="flex items-center gap-3">
                    <img
                        src="/images/accounts/linkedin.png"
                        alt="LinkedIn"
                        class="h-10 w-10"
                    />
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">Selecionar LinkedIn Page</h1>
                        <p class="text-muted-foreground">
                            Escolha qual página você deseja conectar
                        </p>
                    </div>
                </div>
            </div>

            <Alert v-if="error" variant="destructive">
                <AlertDescription>{{ error }}</AlertDescription>
            </Alert>

            <div v-if="organizations.length === 0 && !error" class="text-center py-16">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                    <Building2 class="h-8 w-8 text-muted-foreground" />
                </div>
                <h3 class="mt-4 text-lg font-semibold">Nenhuma página encontrada</h3>
                <p class="mt-1 text-muted-foreground">
                    Você não é administrador de nenhuma página do LinkedIn.
                </p>
                <Button class="mt-6" @click="goBack">
                    Voltar
                </Button>
            </div>

            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="org in organizations"
                    :key="org.id"
                    @click="selectPage(org)"
                    class="group relative overflow-hidden rounded-xl border bg-card p-4 text-left transition-all hover:border-primary hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    <div class="flex items-center gap-4">
                        <Avatar class="h-14 w-14 rounded-lg">
                            <AvatarImage v-if="org.logo" :src="org.logo" class="object-cover" />
                            <AvatarFallback class="rounded-lg bg-blue-100 dark:bg-blue-900">
                                <Building2 class="h-7 w-7 text-blue-600 dark:text-blue-400" />
                            </AvatarFallback>
                        </Avatar>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold truncate group-hover:text-primary transition-colors">
                                {{ org.name }}
                            </h3>
                            <p v-if="org.vanity_name" class="text-sm text-muted-foreground truncate">
                                linkedin.com/company/{{ org.vanity_name }}
                            </p>
                            <p v-else class="text-sm text-muted-foreground">
                                LinkedIn Page
                            </p>
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
