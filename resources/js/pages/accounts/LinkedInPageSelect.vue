<script setup lang="ts">
import { IconBuilding, IconCheck } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import PopupLayout from '@/layouts/PopupLayout.vue';
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

const formRef = ref<HTMLFormElement | null>(null);
const selectedOrg = ref<Organization | null>(null);

const handleSelectPage = (org: Organization) => {
    selectedOrg.value = org;
    // Submit via regular form to get Blade response
    setTimeout(() => formRef.value?.submit(), 0);
};

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
</script>

<template>
    <PopupLayout :title="$t('accounts.linkedin.title')">
        <!-- Hidden form for regular POST submission -->
        <form ref="formRef" :action="selectLinkedInPage.url()" method="POST" class="hidden">
            <input type="hidden" name="_token" :value="csrfToken" />
            <input type="hidden" name="organization_id" :value="selectedOrg?.id" />
            <input type="hidden" name="organization_name" :value="selectedOrg?.name" />
            <input type="hidden" name="organization_vanity_name" :value="selectedOrg?.vanity_name ?? ''" />
            <input type="hidden" name="organization_logo" :value="selectedOrg?.logo ?? ''" />
        </form>

        <div class="flex flex-col gap-6">
            <div class="flex items-center gap-3">
                <img src="/images/accounts/linkedin.png" alt="LinkedIn" class="h-10 w-10" />
                <div>
                    <h1 class="text-xl font-bold tracking-tight">{{ $t('accounts.linkedin.title') }}</h1>
                    <p class="text-sm text-muted-foreground">{{ $t('accounts.linkedin.description') }}</p>
                </div>
            </div>

            <Alert v-if="error" variant="destructive">
                <AlertDescription>{{ error }}</AlertDescription>
            </Alert>

            <div v-if="organizations.length === 0 && !error" class="text-center py-12">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-muted">
                    <IconBuilding class="h-7 w-7 text-muted-foreground" />
                </div>
                <h3 class="mt-4 text-lg font-semibold">{{ $t('accounts.linkedin.no_pages') }}</h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ $t('accounts.linkedin.no_pages_description') }}
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
                                <IconBuilding class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                            </AvatarFallback>
                        </Avatar>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold truncate group-hover:text-primary transition-colors">
                                {{ org.name }}
                            </h3>
                            <p v-if="org.vanity_name" class="text-sm text-muted-foreground truncate">
                                linkedin.com/company/{{ org.vanity_name }}
                            </p>
                            <p v-else class="text-sm text-muted-foreground">{{ $t('accounts.linkedin.page_label') }}</p>
                        </div>
                        <div class="shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-primary-foreground"
                            >
                                <IconCheck class="h-4 w-4" />
                            </div>
                        </div>
                    </div>
                </button>
            </div>
        </div>
    </PopupLayout>
</template>
