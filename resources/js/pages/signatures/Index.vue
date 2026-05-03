<script setup lang="ts">
import { Head, InfiniteScroll, router } from '@inertiajs/vue3';
import { IconHash, IconPencil, IconSearch, IconTrash } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import CreateDialog from '@/components/signatures/CreateDialog.vue';
import EditDialog from '@/components/signatures/EditDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableLoadMore,
    TableRow,
} from '@/components/ui/table';
import dayjs from '@/dayjs';
import debounce from '@/debounce';
import AppLayout from '@/layouts/AppLayout.vue';
import { destroy as signaturesDestroy, index as signaturesIndex } from '@/routes/app/signatures';
import type { BreadcrumbItem } from '@/types';

interface Workspace {
    id: string;
    name: string;
}

interface Signature {
    id: string;
    name: string;
    content: string;
    created_at: string;
}

interface ScrollSignatures {
    data: Signature[];
    meta: { hasNextPage: boolean };
}

interface Props {
    workspace: Workspace;
    signatures: ScrollSignatures;
    filters: { search: string };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search);

const search = debounce(() => {
    router.get(
        signaturesIndex.url(),
        { search: searchQuery.value || undefined },
        { preserveState: true, preserveScroll: true, reset: ['signatures'] },
    );
}, 300);

watch(searchQuery, () => search());

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('signatures.title') },
]);

const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);
const isCreateDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const editingSignature = ref<Signature | null>(null);

const openEditDialog = (signature: Signature) => {
    editingSignature.value = signature;
    isEditDialogOpen.value = true;
};

const handleDelete = (signatureId: string) => {
    deleteModal.value?.open({ url: signaturesDestroy.url(signatureId) });
};

const formatDate = (date: string): string => dayjs.utc(date).local().format('D MMM YYYY');

const hasActiveSearch = computed(() => Boolean(searchQuery.value?.trim()));
</script>

<template>
    <Head :title="$t('signatures.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <PageHeader :title="$t('signatures.title')" />

            <div class="flex items-center justify-between gap-3">
                <div class="relative">
                    <IconSearch class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        :placeholder="trans('signatures.search')"
                        class="w-64 pl-9"
                    />
                </div>

                <Button @click="isCreateDialogOpen = true">{{ $t('signatures.new') }}</Button>
            </div>

            <EmptyState
                v-if="signatures.data.length === 0"
                :icon="IconHash"
                :title="hasActiveSearch ? $t('signatures.no_search_results') : $t('signatures.empty_title')"
                :description="hasActiveSearch ? $t('signatures.try_different_search') : $t('signatures.empty_description')"
            />

            <div v-else class="rounded-md border">
                <InfiniteScroll data="signatures" items-element="#signatures-body" preserve-url>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{{ $t('signatures.table.name') }}</TableHead>
                                <TableHead>{{ $t('signatures.table.content') }}</TableHead>
                                <TableHead>{{ $t('signatures.table.created_at') }}</TableHead>
                                <TableHead class="text-right" />
                            </TableRow>
                        </TableHeader>
                        <TableBody id="signatures-body">
                            <TableRow
                                v-for="signature in signatures.data"
                                :key="signature.id"
                                class="cursor-pointer"
                                @click="openEditDialog(signature)"
                            >
                                <TableCell class="font-medium">{{ signature.name }}</TableCell>
                                <TableCell class="max-w-md">
                                    <p class="truncate text-sm text-muted-foreground">{{ signature.content }}</p>
                                </TableCell>
                                <TableCell class="text-muted-foreground">{{ formatDate(signature.created_at) }}</TableCell>
                                <TableCell class="text-right" @click.stop>
                                    <div class="flex justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-8"
                                            @click="openEditDialog(signature)"
                                        >
                                            <IconPencil class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-8 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                            @click="handleDelete(signature.id)"
                                        >
                                            <IconTrash class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <template #next="{ loading }">
                        <TableLoadMore v-if="loading" />
                    </template>
                </InfiniteScroll>
            </div>
        </div>
    </AppLayout>

    <CreateDialog v-model:open="isCreateDialogOpen" />
    <EditDialog v-model:open="isEditDialogOpen" :signature="editingSignature" />

    <ConfirmDeleteModal
        ref="deleteModal"
        :title="$t('signatures.delete.title')"
        :description="$t('signatures.delete.description')"
        :action="$t('signatures.delete.confirm')"
        :cancel="$t('signatures.delete.cancel')"
    />
</template>
