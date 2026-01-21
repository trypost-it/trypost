<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconPlus, IconTag, IconPencil, IconTrash } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import CreateDialog from '@/components/labels/CreateDialog.vue';
import EditDialog from '@/components/labels/EditDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as labelsIndex, destroy as labelsDestroy } from '@/routes/labels';
import { type BreadcrumbItemType } from '@/types';

interface Workspace {
    id: string;
    name: string;
}

interface Label {
    id: string;
    name: string;
    color: string;
    created_at: string;
}

interface Props {
    workspace: Workspace;
    labels: Label[];
}

defineProps<Props>();

const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);
const isCreateDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const editingLabel = ref<Label | null>(null);

const breadcrumbs: BreadcrumbItemType[] = [
    { title: trans('labels.title'), href: labelsIndex.url() },
];

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

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div v-if="labels.length > 0" class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ $t('labels.title') }}</h1>
                    <p class="text-muted-foreground">
                        {{ $t('labels.description') }}
                    </p>
                </div>
                <Button @click="isCreateDialogOpen = true">
                    <IconPlus class="mr-2 h-4 w-4" />
                    {{ $t('labels.new_label') }}
                </Button>
            </div>

            <div v-if="labels.length === 0" class="flex flex-col items-center justify-center py-16">
                <div class="h-16 w-16 rounded-full bg-muted flex items-center justify-center mb-4">
                    <IconTag class="h-8 w-8 text-muted-foreground" />
                </div>
                <h3 class="text-lg font-semibold mb-2">{{ $t('labels.no_labels_yet') }}</h3>
                <p class="text-muted-foreground mb-4 text-center max-w-sm">
                    {{ $t('labels.description') }}
                </p>
                <Button @click="isCreateDialogOpen = true">
                    <IconPlus class="mr-2 h-4 w-4" />
                    {{ $t('labels.create_first_label') }}
                </Button>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <Card v-for="label in labels" :key="label.id">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div
                                    class="h-6 w-6 rounded-md"
                                    :style="{ backgroundColor: label.color }"
                                />
                                <CardTitle class="text-lg">{{ label.name }}</CardTitle>
                            </div>
                            <div class="flex items-center gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8"
                                    @click="openEditDialog(label)"
                                >
                                    <IconPencil class="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-destructive hover:text-destructive"
                                    @click="handleDelete(label.id)"
                                >
                                    <IconTrash class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                </Card>
            </div>
        </div>
    </AppLayout>

    <CreateDialog v-model:open="isCreateDialogOpen" />
    <EditDialog v-model:open="isEditDialogOpen" :label="editingLabel" />

    <ConfirmDeleteModal
        ref="deleteModal"
        :title="$t('labels.delete.title')"
        :description="$t('labels.delete.description')"
        :action="$t('labels.delete.confirm')"
        :cancel="$t('labels.delete.cancel')"
    />
</template>
