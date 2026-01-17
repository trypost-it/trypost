<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Building2, Check } from 'lucide-vue-next';

import PopupLayout from '@/layouts/PopupLayout.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { select as selectLinkedInPage } from '@/routes/social/linkedin-page';

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

defineProps<Props>();

const handleSelectPage = (org: Organization) => {
    router.post(selectLinkedInPage.url(), {
        organization_id: org.id,
        organization_name: org.name,
        organization_vanity_name: org.vanity_name,
        organization_logo: org.logo,
    });
};
</script>

<template>
    <PopupLayout title="Select LinkedIn Page">
        <div class="flex flex-col gap-6">
            <div class="flex items-center gap-3">
                <img src="/images/accounts/linkedin.png" alt="LinkedIn" class="h-10 w-10" />
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Select LinkedIn Page</h1>
                    <p class="text-sm text-muted-foreground">Choose which page you want to connect</p>
                </div>
            </div>

            <Alert v-if="error" variant="destructive">
                <AlertDescription>{{ error }}</AlertDescription>
            </Alert>

            <div v-if="organizations.length === 0 && !error" class="text-center py-12">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-muted">
                    <Building2 class="h-7 w-7 text-muted-foreground" />
                </div>
                <h3 class="mt-4 text-lg font-semibold">No pages found</h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    You are not an administrator of any LinkedIn page.
                </p>
            </div>

            <div v-else class="grid gap-3">
                <button
                    v-for="org in organizations"
                    :key="org.id"
                    @click="handleSelectPage(org)"
                    class="group relative overflow-hidden rounded-lg border bg-card p-4 text-left transition-all hover:border-primary hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    <div class="flex items-center gap-4">
                        <Avatar class="h-12 w-12 rounded-lg">
                            <AvatarImage v-if="org.logo" :src="org.logo" class="object-cover" />
                            <AvatarFallback class="rounded-lg bg-blue-100 dark:bg-blue-900">
                                <Building2 class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                            </AvatarFallback>
                        </Avatar>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold truncate group-hover:text-primary transition-colors">
                                {{ org.name }}
                            </h3>
                            <p v-if="org.vanity_name" class="text-sm text-muted-foreground truncate">
                                linkedin.com/company/{{ org.vanity_name }}
                            </p>
                            <p v-else class="text-sm text-muted-foreground">LinkedIn Page</p>
                        </div>
                        <div class="shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-primary-foreground"
                            >
                                <Check class="h-4 w-4" />
                            </div>
                        </div>
                    </div>
                </button>
            </div>
        </div>
    </PopupLayout>
</template>
