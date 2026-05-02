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
import SettingsTabsNav from '@/components/settings/SettingsTabsNav.vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import { copyToClipboard } from '@/lib/utils';
import { members as membersRoute } from '@/routes/app';
import { index as apiKeysRoute } from '@/routes/app/api-keys';
import { brand as brandRoute, settings as workspaceSettings } from '@/routes/app/workspace';
import type { BreadcrumbItem } from '@/types';
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

const createDialogOpen = ref(false);
const confirmDeleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('settings.workspace.title'), href: workspaceSettings.url() },
    { title: trans('settings.workspace.tabs.api_keys') },
]);

const tabs = computed(() => [
    { name: 'workspace', label: trans('settings.workspace.tabs.workspace'), href: workspaceSettings.url() },
    { name: 'brand', label: trans('settings.workspace.tabs.brand'), href: brandRoute.url() },
    { name: 'members', label: trans('settings.workspace.tabs.users'), href: membersRoute.url() },
    { name: 'api-keys', label: trans('settings.workspace.tabs.api_keys'), href: apiKeysRoute.url() },
]);
</script>

<template>
    <Head :title="$t('settings.api_keys.page_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl space-y-6 px-4 py-6">
            <SettingsTabsNav :tabs="tabs" active="api-keys" />

            <div class="flex items-center justify-between">
                <HeadingSmall
                    :title="$t('settings.api_keys.heading')"
                    :description="$t('settings.api_keys.description')"
                />
                <Button @click="createDialogOpen = true">
                    {{ $t('settings.api_keys.create') }}
                </Button>
            </div>

            <!-- New token alert -->
            <div
                v-if="newToken"
                class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-950"
            >
                <p class="mb-2 text-sm font-medium text-green-800 dark:text-green-200">
                    {{ $t('settings.api_keys.new_token_message') }}
                </p>
                <div class="flex items-center gap-2">
                    <code class="flex-1 rounded bg-white px-3 py-2 font-mono text-sm dark:bg-black">
                        {{ newToken }}
                    </code>
                    <Button variant="outline" size="sm" @click="copyToClipboard(newToken!, trans('settings.api_keys.new_token_message'))">
                        {{ $t('settings.api_keys.copy') }}
                    </Button>
                </div>
            </div>

            <div v-if="apiTokens.length > 0" class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ $t('settings.api_keys.table.name') }}</TableHead>
                            <TableHead>{{ $t('settings.api_keys.table.key') }}</TableHead>
                            <TableHead>{{ $t('settings.api_keys.table.status') }}</TableHead>
                            <TableHead>{{ $t('settings.api_keys.table.expires') }}</TableHead>
                            <TableHead>{{ $t('settings.api_keys.table.last_used') }}</TableHead>
                            <TableHead class="w-10" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="token in apiTokens" :key="token.id">
                            <TableCell class="font-medium">{{ token.name }}</TableCell>
                            <TableCell>
                                <code class="text-xs text-muted-foreground">{{ token.key_hint }}</code>
                            </TableCell>
                            <TableCell>
                                <Badge :variant="token.status === 'active' ? 'default' : 'secondary'">
                                    {{ token.status }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ token.expires_at ? date.formatDateTime(token.expires_at) : $t('settings.api_keys.table.never') }}
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ token.last_used_at ? date.diffForHumans(token.last_used_at) : $t('settings.api_keys.table.never') }}
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
                                            @click="copyToClipboard(token.id, trans('settings.api_keys.actions.copy_id_success'))"
                                        >
                                            <IconCopy class="size-4" />
                                            {{ $t('settings.api_keys.actions.copy_id') }}
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            variant="destructive"
                                            @click="confirmDeleteModal?.open({ url: ApiKeyController.destroy.url(token), confirmText: token.name })"
                                        >
                                            <IconTrash class="size-4" />
                                            {{ $t('settings.api_keys.actions.delete') }}
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
                :title="$t('settings.api_keys.empty.title')"
                :description="$t('settings.api_keys.empty.description')"
            />
        </div>
    </AppLayout>

    <CreateApiKeyDialog v-model:open="createDialogOpen" />

    <ConfirmDeleteModal
        ref="confirmDeleteModal"
        :title="$t('settings.api_keys.delete_modal.title')"
        :description="$t('settings.api_keys.delete_modal.description')"
        :action="$t('settings.api_keys.delete_modal.action')"
    />
</template>
