<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { IconCopy, IconDots, IconKey, IconTrash } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

import ApiKeyController from '@/actions/App/Http/Controllers/App/ApiKeyController';
import CreateApiKeyDialog from '@/components/api-keys/CreateApiKeyDialog.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import date from '@/date';
import { copyToClipboard } from '@/lib/utils';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { index as apiKeysIndex } from '@/routes/app/api-keys';
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

interface Props {
    apiTokens: ApiToken[];
}

defineProps<Props>();

const page = usePage();
const newToken = computed(() => (page.props.flash as Record<string, unknown>)?.plainToken as string | undefined);

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    { title: 'API Keys', href: apiKeysIndex.url() },
]);

const createDialogOpen = ref(false);
const confirmDeleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);
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
                    <Button @click="createDialogOpen = true">
                        Create API Key
                    </Button>
                </div>

                <!-- New token alert -->
                <div
                    v-if="newToken"
                    class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-950"
                >
                    <p class="mb-2 text-sm font-medium text-green-800 dark:text-green-200">
                        Your new API key has been created. Copy it now — you won't be able to see it again.
                    </p>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 rounded bg-white px-3 py-2 font-mono text-sm dark:bg-black">
                            {{ newToken }}
                        </code>
                        <Button variant="outline" size="sm" @click="copyToClipboard(newToken!, 'API key copied to clipboard')">
                            Copy
                        </Button>
                    </div>
                </div>

                <div v-if="apiTokens.length > 0" class="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Key</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Expires</TableHead>
                                <TableHead>Last Used</TableHead>
                                <TableHead class="w-10" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="token in apiTokens" :key="token.id">
                                <TableCell class="font-medium">
                                    {{ token.name }}
                                </TableCell>
                                <TableCell>
                                    <code class="text-xs text-muted-foreground">
                                        {{ token.key_hint }}
                                    </code>
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="token.status === 'active' ? 'default' : 'secondary'">
                                        {{ token.status }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-muted-foreground">
                                    {{ token.expires_at ? date.formatDate(token.expires_at) : 'Never' }}
                                </TableCell>
                                <TableCell class="text-muted-foreground">
                                    {{ token.last_used_at ? date.diffForHumans(token.last_used_at) : 'Never' }}
                                </TableCell>
                                <TableCell>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="icon" class="h-8 w-8">
                                                <IconDots class="size-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem
                                                @click="copyToClipboard(token.id, 'API Key ID copied to clipboard')"
                                            >
                                                <IconCopy class="size-4" />
                                                Copy API Key ID
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                variant="destructive"
                                                @click="confirmDeleteModal?.open({ url: ApiKeyController.destroy.url(token), confirmText: token.name })"
                                            >
                                                <IconTrash class="size-4" />
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <EmptyState
                    v-else-if="!newToken"
                    :icon="IconKey"
                    title="No API keys yet"
                    description="Create an API key to access your workspace programmatically."
                />
            </div>
        </SettingsLayout>
    </AppLayout>

    <CreateApiKeyDialog v-model:open="createDialogOpen" />

    <ConfirmDeleteModal
        ref="confirmDeleteModal"
        title="Delete API key"
        description="Are you sure you want to delete this API key? Any applications using this key will lose access immediately."
        action="Delete API key"
    />
</template>
