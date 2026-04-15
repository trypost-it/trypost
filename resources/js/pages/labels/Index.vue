<script setup lang="ts">
import { Head, InfiniteScroll, router } from '@inertiajs/vue3';
import { IconPencil, IconSearch, IconTag, IconTrash } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import CreateDialog from '@/components/labels/CreateDialog.vue';
import EditDialog from '@/components/labels/EditDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import debounce from '@/debounce';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as labelsIndex, destroy as labelsDestroy } from '@/routes/app/labels';
interface Label {
    id: string;
    name: string;
    color: string;
    created_at: string;
}

interface ScrollLabels {
    data: Label[];
    meta: {
        hasNextPage: boolean;
    };
}

interface Props {
    labels: ScrollLabels;
    filters: {
        search: string;
    };
}

const props = defineProps<Props>();

const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);
const isCreateDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const editingLabel = ref<Label | null>(null);

const searchQuery = ref(props.filters.search);

const search = debounce(() => {
    router.get(
        labelsIndex.url(),
        { search: searchQuery.value || undefined },
        {
            preserveState: true,
            preserveScroll: true,
            reset: ['labels'],
        },
    );
}, 300);

watch(searchQuery, () => search());

const openEditDialog = (label: Label) => {
    editingLabel.value = label;
    isEditDialogOpen.value = true;
};

const handleDelete = (labelId: string) => {
    deleteModal.value?.open({
        url: labelsDestroy.url(labelId),
    });
};
</script>

<template>

    <Head :title="$t('labels.title')" />

    <AppLayout :title="$t('labels.title')">
        <template #header-actions>
            <div class="relative">
                <IconSearch class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="searchQuery"
                    :placeholder="trans('labels.search')"
                    class="w-64 pl-9"
                />
            </div>
            <Button @click="isCreateDialogOpen = true">
                {{ $t('labels.new_label') }}
            </Button>
        </template>

        <div class="flex flex-col gap-6 p-6">
            <EmptyState
                v-if="labels.data.length === 0"
                :icon="IconTag"
                :title="$t('labels.no_labels_yet')"
                :description="$t('labels.description')"
            />

            <div v-else>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <Card v-for="label in labels.data" :key="label.id">
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="h-6 w-6 rounded-md" :style="{ backgroundColor: label.color }" />
                                    <CardTitle class="text-lg">{{ label.name }}</CardTitle>
                                </div>
                                <div class="flex items-center gap-1">
                                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="openEditDialog(label)">
                                        <IconPencil class="h-4 w-4" />
                                    </Button>
                                    <Button variant="ghost" size="icon"
                                        class="h-8 w-8 text-destructive hover:text-destructive"
                                        @click="handleDelete(label.id)">
                                        <IconTrash class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                    </Card>
                </div>

                <InfiniteScroll data="labels" #default="{ loading }">
                    <div v-if="loading" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 mt-4">
                        <Card v-for="i in 4" :key="i">
                            <CardHeader class="pb-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <Skeleton class="h-6 w-6 rounded-md" />
                                        <Skeleton class="h-6 w-24" />
                                    </div>
                                    <div class="flex gap-1">
                                        <Skeleton class="h-8 w-8" />
                                        <Skeleton class="h-8 w-8" />
                                    </div>
                                </div>
                            </CardHeader>
                        </Card>
                    </div>
                </InfiniteScroll>
            </div>
        </div>
    </AppLayout>

    <CreateDialog v-model:open="isCreateDialogOpen" />
    <EditDialog v-model:open="isEditDialogOpen" :label="editingLabel" />

    <ConfirmDeleteModal ref="deleteModal" :title="$t('labels.delete.title')"
        :description="$t('labels.delete.description')" :action="$t('labels.delete.confirm')"
        :cancel="$t('labels.delete.cancel')" />
</template>
