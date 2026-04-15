<script setup lang="ts">
import { Head, InfiniteScroll, router } from '@inertiajs/vue3';
import { IconHash, IconPencil, IconSearch, IconTrash } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import CreateDialog from '@/components/hashtags/CreateDialog.vue';
import EditDialog from '@/components/hashtags/EditDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import debounce from '@/debounce';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as hashtagsIndex, destroy as hashtagsDestroy } from '@/routes/app/hashtags';
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
    meta: {
        hasNextPage: boolean;
    };
}

interface Props {
    workspace: Workspace;
    hashtags: ScrollHashtags;
    filters: {
        search: string;
    };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search);

const search = debounce(() => {
    router.get(
        hashtagsIndex.url(),
        { search: searchQuery.value || undefined },
        {
            preserveState: true,
            preserveScroll: true,
            reset: ['hashtags'],
        },
    );
}, 300);

watch(searchQuery, () => search());

const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);
const isCreateDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const editingHashtag = ref<Hashtag | null>(null);

const openEditDialog = (hashtag: Hashtag) => {
    editingHashtag.value = hashtag;
    isEditDialogOpen.value = true;
};

const handleDelete = (hashtagId: string) => {
    deleteModal.value?.open({
        url: hashtagsDestroy.url(hashtagId),
    });
};

const getHashtagCount = (hashtags: string): number => {
    return hashtags.split(/[\s,]+/).filter(tag => tag.startsWith('#') || tag.length > 0).length;
};
</script>

<template>

    <Head :title="$t('hashtags.title')" />

    <AppLayout :title="$t('hashtags.title')">
        <template #header-actions>
            <div class="relative">
                <IconSearch class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="searchQuery"
                    :placeholder="trans('hashtags.search')"
                    class="w-64 pl-9"
                />
            </div>
            <Button @click="isCreateDialogOpen = true">
                {{ $t('hashtags.new_group') }}
            </Button>
        </template>

        <div class="flex flex-col gap-6 p-6">

            <EmptyState
                v-if="hashtags.data.length === 0"
                :icon="IconHash"
                :title="$t('hashtags.no_groups_yet')"
                :description="$t('hashtags.no_groups_description')"
            />

            <div v-else>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="hashtag in hashtags.data" :key="hashtag.id">
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-lg">{{ hashtag.name }}</CardTitle>
                                <div class="flex items-center gap-1">
                                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="openEditDialog(hashtag)">
                                        <IconPencil class="h-4 w-4" />
                                    </Button>
                                    <Button variant="ghost" size="icon"
                                        class="h-8 w-8 text-destructive hover:text-destructive"
                                        @click="handleDelete(hashtag.id)">
                                        <IconTrash class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                            <CardDescription>
                                {{ $t('hashtags.hashtags_count', { count: String(getHashtagCount(hashtag.hashtags)) }) }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p class="text-sm text-muted-foreground line-clamp-3">
                                {{ hashtag.hashtags }}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <InfiniteScroll data="hashtags" #default="{ loading }">
                    <div v-if="loading" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 mt-4">
                        <Card v-for="i in 3" :key="i">
                            <CardHeader class="pb-3">
                                <div class="flex items-center justify-between">
                                    <Skeleton class="h-6 w-32" />
                                    <div class="flex gap-1">
                                        <Skeleton class="h-8 w-8" />
                                        <Skeleton class="h-8 w-8" />
                                    </div>
                                </div>
                                <Skeleton class="h-4 w-20" />
                            </CardHeader>
                            <CardContent>
                                <Skeleton class="h-4 w-full" />
                                <Skeleton class="h-4 w-3/4 mt-2" />
                            </CardContent>
                        </Card>
                    </div>
                </InfiniteScroll>
            </div>
        </div>
    </AppLayout>

    <CreateDialog v-model:open="isCreateDialogOpen" />
    <EditDialog v-model:open="isEditDialogOpen" :hashtag="editingHashtag" />

    <ConfirmDeleteModal ref="deleteModal" :title="$t('hashtags.delete.title')"
        :description="$t('hashtags.delete.description')" :action="$t('hashtags.delete.confirm')"
        :cancel="$t('hashtags.delete.cancel')" />
</template>