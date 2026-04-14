<script setup lang="ts">
import { Head, InfiniteScroll } from '@inertiajs/vue3';
import { IconBuildingStore, IconPencil, IconTrash } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import CreateDialog from '@/components/brands/CreateDialog.vue';
import EditDialog from '@/components/brands/EditDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as brandsIndex, destroy as brandsDestroy } from '@/routes/app/brands';
import { type BreadcrumbItemType } from '@/types';

interface Brand {
    id: string;
    name: string;
    social_accounts_count: number;
}

interface ScrollBrands {
    data: Brand[];
    meta: {
        hasNextPage: boolean;
    };
}

interface Props {
    brands: ScrollBrands;
    canCreate: boolean;
}

const props = defineProps<Props>();

const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);
const isCreateDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const editingBrand = ref<Brand | null>(null);

const breadcrumbs = computed<BreadcrumbItemType[]>(() => [
    { title: trans('sidebar.config.brands'), href: brandsIndex.url() },
]);

const openEditDialog = (brand: Brand) => {
    editingBrand.value = brand;
    isEditDialogOpen.value = true;
};

const handleDelete = (brandId: string) => {
    deleteModal.value?.open({
        url: brandsDestroy.url(brandId),
    });
};
</script>

<template>
    <Head :title="$t('sidebar.config.brands')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <template #header-right>
            <Button :disabled="!canCreate" @click="isCreateDialogOpen = true">
                {{ $t('brands.new_brand') }}
            </Button>
        </template>

        <div class="flex flex-col gap-6 p-6">
            <EmptyState
                v-if="brands.data.length === 0"
                :icon="IconBuildingStore"
                :title="$t('brands.no_brands_yet')"
                :description="$t('brands.no_brands_description')"
            />

            <div v-else>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="brand in brands.data" :key="brand.id">
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-lg">{{ brand.name }}</CardTitle>
                                <div class="flex items-center gap-1">
                                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="openEditDialog(brand)">
                                        <IconPencil class="h-4 w-4" />
                                    </Button>
                                    <Button variant="ghost" size="icon"
                                        class="h-8 w-8 text-destructive hover:text-destructive"
                                        @click="handleDelete(brand.id)">
                                        <IconTrash class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <p class="text-sm text-muted-foreground">
                                {{ $t('brands.accounts_count', { count: String(brand.social_accounts_count) }) }}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <InfiniteScroll data="brands" #default="{ loading }">
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
                            </CardHeader>
                            <CardContent>
                                <Skeleton class="h-4 w-24" />
                            </CardContent>
                        </Card>
                    </div>
                </InfiniteScroll>
            </div>
        </div>
    </AppLayout>

    <CreateDialog v-model:open="isCreateDialogOpen" />
    <EditDialog v-model:open="isEditDialogOpen" :brand="editingBrand" />

    <ConfirmDeleteModal ref="deleteModal" :title="$t('brands.delete.title')"
        :description="$t('brands.delete.description')" :action="$t('brands.delete.confirm')"
        :cancel="$t('brands.delete.cancel')" />
</template>
