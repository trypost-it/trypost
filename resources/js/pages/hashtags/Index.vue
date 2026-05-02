<script setup lang="ts">
import { Head, InfiniteScroll, router } from '@inertiajs/vue3';
import { IconHash, IconPencil, IconSearch, IconTrash } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import CreateDialog from '@/components/hashtags/CreateDialog.vue';
import EditDialog from '@/components/hashtags/EditDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
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
import { destroy as hashtagsDestroy, index as hashtagsIndex } from '@/routes/app/hashtags';
import type { BreadcrumbItem } from '@/types';

interface Workspace {
    id: string;
    name: string;
}

interface Hashtag {
    id: string;
    name: string;
    hashtags: string;
    created_at: string;
}

interface ScrollHashtags {
    data: Hashtag[];
    meta: { hasNextPage: boolean };
}

interface Props {
    workspace: Workspace;
    hashtags: ScrollHashtags;
    filters: { search: string };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search);

const search = debounce(() => {
    router.get(
        hashtagsIndex.url(),
        { search: searchQuery.value || undefined },
        { preserveState: true, preserveScroll: true, reset: ['hashtags'] },
    );
}, 300);

watch(searchQuery, () => search());

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('hashtags.title') },
]);

const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);
const isCreateDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const editingHashtag = ref<Hashtag | null>(null);

const openEditDialog = (hashtag: Hashtag) => {
    editingHashtag.value = hashtag;
    isEditDialogOpen.value = true;
};

const handleDelete = (hashtagId: string) => {
    deleteModal.value?.open({ url: hashtagsDestroy.url(hashtagId) });
};

const getHashtagCount = (hashtags: string): number =>
    hashtags.split(/[\s,]+/).filter((tag) => tag.startsWith('#') || tag.length > 0).length;

const formatDate = (date: string): string => dayjs.utc(date).local().format('D MMM YYYY');

const hasActiveSearch = computed(() => Boolean(searchQuery.value?.trim()));
</script>

<template>
    <Head :title="$t('hashtags.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <PageHeader :title="$t('hashtags.title')" />

            <div class="flex items-center justify-between gap-3">
                <div class="relative">
                    <IconSearch class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        :placeholder="trans('hashtags.search')"
                        class="w-64 pl-9"
                    />
                </div>

                <Button @click="isCreateDialogOpen = true">{{ $t('hashtags.new_group') }}</Button>
            </div>

            <EmptyState
                v-if="hashtags.data.length === 0"
                :icon="IconHash"
                :title="hasActiveSearch ? $t('hashtags.no_search_results') : $t('hashtags.no_groups_yet')"
                :description="hasActiveSearch ? $t('hashtags.try_different_search') : $t('hashtags.no_groups_description')"
            />

            <div v-else class="rounded-md border">
                <InfiniteScroll data="hashtags" items-element="#hashtags-body" preserve-url>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{{ $t('hashtags.table.name') }}</TableHead>
                                <TableHead>{{ $t('hashtags.table.tags') }}</TableHead>
                                <TableHead>{{ $t('hashtags.table.count') }}</TableHead>
                                <TableHead>{{ $t('hashtags.table.created_at') }}</TableHead>
                                <TableHead class="text-right" />
                            </TableRow>
                        </TableHeader>
                        <TableBody id="hashtags-body">
                            <TableRow
                                v-for="hashtag in hashtags.data"
                                :key="hashtag.id"
                                class="cursor-pointer"
                                @click="openEditDialog(hashtag)"
                            >
                                <TableCell class="font-medium">{{ hashtag.name }}</TableCell>
                                <TableCell class="max-w-md">
                                    <p class="truncate text-sm text-muted-foreground">{{ hashtag.hashtags }}</p>
                                </TableCell>
                                <TableCell class="text-muted-foreground tabular-nums">
                                    {{ getHashtagCount(hashtag.hashtags) }}
                                </TableCell>
                                <TableCell class="text-muted-foreground">{{ formatDate(hashtag.created_at) }}</TableCell>
                                <TableCell class="text-right" @click.stop>
                                    <div class="flex justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-8"
                                            @click="openEditDialog(hashtag)"
                                        >
                                            <IconPencil class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-8 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                            @click="handleDelete(hashtag.id)"
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
    <EditDialog v-model:open="isEditDialogOpen" :hashtag="editingHashtag" />

    <ConfirmDeleteModal
        ref="deleteModal"
        :title="$t('hashtags.delete.title')"
        :description="$t('hashtags.delete.description')"
        :action="$t('hashtags.delete.confirm')"
        :cancel="$t('hashtags.delete.cancel')"
    />
</template>
