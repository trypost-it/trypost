<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { IconKey, IconCopy, IconTrash, IconPlus } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import date from '@/date';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { index as apiKeysIndex, store as storeApiKey, destroy as destroyApiKey } from '@/routes/app/api-keys';
import { type BreadcrumbItem } from '@/types';

interface ApiToken {
    id: string;
    name: string;
    key_hint: string;
    status: 'active' | 'expired';
    last_used_at: string | null;
    expires_at: string | null;
    created_at: string;
}

interface Workspace {
    id: string;
    name: string;
}

interface Props {
    workspace: Workspace;
    apiTokens: ApiToken[];
}

defineProps<Props>();

const page = usePage();

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    { title: 'API Keys', href: apiKeysIndex.url() },
]);

const isCreateDialogOpen = ref(false);
const isTokenDialogOpen = ref(false);
const plainToken = ref<string | null>(null);
const copied = ref(false);
const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);

const form = useForm({
    name: '',
    expires_at: '',
});

watch(() => page.props.flash, (flash: Record<string, unknown> | undefined) => {
    if (flash?.plainToken) {
        plainToken.value = flash.plainToken as string;
        isCreateDialogOpen.value = false;
        isTokenDialogOpen.value = true;
    }
}, { deep: true });

const submitCreate = () => {
    form.post(storeApiKey.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
};

const copyToken = async () => {
    if (!plainToken.value) return;
    await navigator.clipboard.writeText(plainToken.value);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
};

const handleDelete = (tokenId: string) => {
    deleteModal.value?.open({
        url: destroyApiKey.url(tokenId),
    });
};

const closeTokenDialog = () => {
    isTokenDialogOpen.value = false;
    plainToken.value = null;
    copied.value = false;
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="API Keys" />

        <h1 class="sr-only">API Keys</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <div class="flex items-center justify-between">
                    <HeadingSmall
                        title="API Keys"
                        description="Manage API keys for programmatic access to your workspace."
                    />
                    <Button @click="isCreateDialogOpen = true">
                        <IconPlus class="mr-2 h-4 w-4" />
                        Create API Key
                    </Button>
                </div>

                <div v-if="apiTokens.length === 0" class="flex flex-col items-center justify-center rounded-lg border border-dashed py-16">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-muted mb-4">
                        <IconKey class="h-8 w-8 text-muted-foreground" />
                    </div>
                    <h3 class="text-lg font-semibold mb-2">No API keys yet</h3>
                    <p class="text-muted-foreground mb-4 max-w-sm text-center">
                        Create an API key to access your workspace programmatically.
                    </p>
                    <Button @click="isCreateDialogOpen = true">
                        <IconPlus class="mr-2 h-4 w-4" />
                        Create your first API key
                    </Button>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="token in apiTokens"
                        :key="token.id"
                        class="flex items-center justify-between rounded-lg border p-4"
                    >
                        <div class="flex items-center gap-4 min-w-0">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted">
                                <IconKey class="h-5 w-5 text-muted-foreground" />
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="font-medium truncate">{{ token.name }}</p>
                                    <Badge
                                        :variant="token.status === 'active' ? 'default' : 'destructive'"
                                        class="shrink-0"
                                    >
                                        {{ token.status }}
                                    </Badge>
                                </div>
                                <p class="text-sm text-muted-foreground font-mono">
                                    {{ token.key_hint }}
                                </p>
                                <div class="flex items-center gap-3 mt-1 text-xs text-muted-foreground">
                                    <span>Created {{ date.diffForHumans(token.created_at) }}</span>
                                    <span v-if="token.last_used_at">
                                        Last used {{ date.diffForHumans(token.last_used_at) }}
                                    </span>
                                    <span v-else>Never used</span>
                                    <span v-if="token.expires_at">
                                        Expires {{ date.diffForHumans(token.expires_at) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="shrink-0"
                            @click="handleDelete(token.id)"
                        >
                            <IconTrash class="h-4 w-4 text-red-500" />
                        </Button>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>

    <Dialog :open="isCreateDialogOpen" @update:open="isCreateDialogOpen = $event">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Create API Key</DialogTitle>
                <DialogDescription>
                    Create a new API key for programmatic access to your workspace.
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submitCreate" class="space-y-4">
                <div class="space-y-2">
                    <Label for="token-name">Name</Label>
                    <Input
                        id="token-name"
                        v-model="form.name"
                        placeholder="e.g. Production API Key"
                        :class="{ 'border-red-500': form.errors.name }"
                    />
                    <p v-if="form.errors.name" class="text-sm text-red-500">
                        {{ form.errors.name }}
                    </p>
                </div>
                <div class="space-y-2">
                    <Label for="token-expires">Expiration date (optional)</Label>
                    <Input
                        id="token-expires"
                        v-model="form.expires_at"
                        type="date"
                        :class="{ 'border-red-500': form.errors.expires_at }"
                    />
                    <p v-if="form.errors.expires_at" class="text-sm text-red-500">
                        {{ form.errors.expires_at }}
                    </p>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="isCreateDialogOpen = false">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        Create
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog :open="isTokenDialogOpen" @update:open="closeTokenDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>API Key Created</DialogTitle>
                <DialogDescription>
                    Copy your API key now. You will not be able to see it again.
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-4">
                <div class="flex items-center gap-2">
                    <Input
                        :model-value="plainToken ?? ''"
                        readonly
                        class="font-mono text-sm"
                    />
                    <Button variant="outline" size="icon" class="shrink-0" @click="copyToken">
                        <IconCopy class="h-4 w-4" />
                    </Button>
                </div>
                <p v-if="copied" class="text-sm text-green-600">Copied to clipboard!</p>
            </div>
            <DialogFooter>
                <Button @click="closeTokenDialog">
                    Done
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <ConfirmDeleteModal
        ref="deleteModal"
        title="Delete API Key"
        description="Are you sure you want to delete this API key? Any applications using this key will lose access immediately."
        action="Delete"
        cancel="Cancel"
    />
</template>
