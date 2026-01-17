<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Tag, Pencil, Trash2 } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as labelsIndex, create as labelsCreate, edit as labelsEdit, destroy as labelsDestroy } from '@/routes/labels';
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

const props = defineProps<Props>();

const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Labels', href: labelsIndex.url() },
];

const handleDelete = (labelId: string) => {
    deleteModal.value?.open({
        url: labelsDestroy.url(labelId),
    });
};
</script>

<template>
    <Head title="Labels" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Labels</h1>
                    <p class="text-muted-foreground">
                        Create labels to organize and categorize your posts
                    </p>
                </div>
                <Link :href="labelsCreate.url()">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        New Label
                    </Button>
                </Link>
            </div>

            <div v-if="labels.length === 0" class="flex flex-col items-center justify-center py-16">
                <div class="h-16 w-16 rounded-full bg-muted flex items-center justify-center mb-4">
                    <Tag class="h-8 w-8 text-muted-foreground" />
                </div>
                <h3 class="text-lg font-semibold mb-2">No labels yet</h3>
                <p class="text-muted-foreground mb-4 text-center max-w-sm">
                    Create labels to organize and categorize your posts
                </p>
                <Link :href="labelsCreate.url()">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        Create your first label
                    </Button>
                </Link>
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
                                <Link :href="labelsEdit.url(label.id)">
                                    <Button variant="ghost" size="icon" class="h-8 w-8">
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                </Link>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-destructive hover:text-destructive"
                                    @click="handleDelete(label.id)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                </Card>
            </div>
        </div>
    </AppLayout>

    <ConfirmDeleteModal
        ref="deleteModal"
        title="Delete Label"
        description="Are you sure you want to delete this label? This action cannot be undone."
        action="Delete"
        cancel="Cancel"
    />
</template>
