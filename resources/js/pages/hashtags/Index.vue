<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Hash, Pencil, Trash2 } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import CreateDialog from '@/components/hashtags/CreateDialog.vue';
import EditDialog from '@/components/hashtags/EditDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { index as hashtagsIndex, destroy as hashtagsDestroy } from '@/routes/hashtags';
import { type BreadcrumbItemType } from '@/types';

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

interface Props {
    workspace: Workspace;
    hashtags: Hashtag[];
}

defineProps<Props>();

const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);
const isCreateDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const editingHashtag = ref<Hashtag | null>(null);

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Hashtags', href: hashtagsIndex.url() },
];

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
    <Head title="Hashtags" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Hashtags</h1>
                    <p class="text-muted-foreground">
                        Create hashtag groups to quickly add to your posts
                    </p>
                </div>
                <Button @click="isCreateDialogOpen = true">
                    <Plus class="mr-2 h-4 w-4" />
                    New Group
                </Button>
            </div>

            <div v-if="hashtags.length === 0" class="flex flex-col items-center justify-center py-16">
                <div class="h-16 w-16 rounded-full bg-muted flex items-center justify-center mb-4">
                    <Hash class="h-8 w-8 text-muted-foreground" />
                </div>
                <h3 class="text-lg font-semibold mb-2">No hashtag groups yet</h3>
                <p class="text-muted-foreground mb-4 text-center max-w-sm">
                    Create hashtag groups to quickly add popular hashtags to your posts
                </p>
                <Button @click="isCreateDialogOpen = true">
                    <Plus class="mr-2 h-4 w-4" />
                    Create your first group
                </Button>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card v-for="hashtag in hashtags" :key="hashtag.id">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-lg">{{ hashtag.name }}</CardTitle>
                            <div class="flex items-center gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8"
                                    @click="openEditDialog(hashtag)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-destructive hover:text-destructive"
                                    @click="handleDelete(hashtag.id)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                        <CardDescription>
                            {{ getHashtagCount(hashtag.hashtags) }} hashtags
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm text-muted-foreground line-clamp-3">
                            {{ hashtag.hashtags }}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>

    <CreateDialog v-model:open="isCreateDialogOpen" />
    <EditDialog v-model:open="isEditDialogOpen" :hashtag="editingHashtag" />

    <ConfirmDeleteModal
        ref="deleteModal"
        title="Delete Hashtag Group"
        description="Are you sure you want to delete this hashtag group? This action cannot be undone."
        action="Delete"
        cancel="Cancel"
    />
</template>
