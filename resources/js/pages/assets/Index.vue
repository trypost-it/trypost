<script setup lang="ts">
import { Head, InfiniteScroll, router, useHttp } from '@inertiajs/vue3';
import { IconCloudUpload, IconDownload, IconPhoto, IconSearch, IconTrash } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import debounce from '@/debounce';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as assetsIndex, destroy as assetsDestroy, store as assetsStore, storeFromUrl } from '@/routes/app/assets';
import { search as unsplashSearch } from '@/routes/app/assets/unsplash';
import { type BreadcrumbItem } from '@/types';

interface AssetMedia {
    id: string;
    url: string;
    type: string;
    original_filename: string;
    size: number;
    meta: { width?: number; height?: number } | null;
    created_at: string;
}

interface ScrollAssets {
    data: AssetMedia[];
    meta: {
        hasNextPage: boolean;
    };
}

interface UnsplashPhoto {
    id: string;
    url_small: string;
    url_regular: string;
    url_full: string;
    download_location: string;
    description: string | null;
    width: number;
    height: number;
    author: {
        name: string;
        url: string;
    };
}

const props = defineProps<{
    assets: ScrollAssets;
}>();

const http = useHttp({});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('assets.title'), href: assetsIndex.url() },
]);

// Upload
const fileInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const uploading = ref(false);

const triggerFileInput = () => fileInput.value?.click();

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files) {
        uploadFiles(Array.from(target.files));
        target.value = '';
    }
};

const handleDrop = (event: DragEvent) => {
    isDragging.value = false;
    if (event.dataTransfer?.files) {
        uploadFiles(Array.from(event.dataTransfer.files));
    }
};

const uploadFiles = async (files: File[]) => {
    uploading.value = true;

    for (const file of files) {
        const formData = new FormData();
        formData.append('media', file);

        try {
            await http.post(assetsStore.url(), formData);
        } catch {
            // Silently handle individual file failures
        }
    }

    uploading.value = false;
    router.reload({ only: ['assets'], reset: ['assets'] });
};

// Delete
const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);

const handleDelete = (assetId: string) => {
    deleteModal.value?.open({
        url: assetsDestroy.url(assetId),
    });
};

// Unsplash
const unsplashQuery = ref('');
const unsplashResults = ref<UnsplashPhoto[]>([]);
const unsplashPage = ref(1);
const unsplashTotalPages = ref(0);
const unsplashLoading = ref(false);
const savingPhotoId = ref<string | null>(null);

const searchUnsplash = debounce(async () => {
    if (!unsplashQuery.value.trim()) {
        unsplashResults.value = [];
        return;
    }

    unsplashLoading.value = true;
    unsplashPage.value = 1;

    try {
        const response = await http.get(unsplashSearch.url({ query: { query: unsplashQuery.value, page: 1 } }));
        unsplashResults.value = response.results;
        unsplashTotalPages.value = response.total_pages;
    } catch {
        unsplashResults.value = [];
    } finally {
        unsplashLoading.value = false;
    }
}, 400);

const loadMoreUnsplash = async () => {
    if (unsplashPage.value >= unsplashTotalPages.value || unsplashLoading.value) return;

    unsplashLoading.value = true;
    unsplashPage.value++;

    try {
        const response = await http.get(unsplashSearch.url({ query: { query: unsplashQuery.value, page: unsplashPage.value } }));
        unsplashResults.value.push(...response.results);
    } catch {
        // ignore
    } finally {
        unsplashLoading.value = false;
    }
};

const saveFromUnsplash = async (photo: UnsplashPhoto) => {
    savingPhotoId.value = photo.id;

    try {
        await http.post(storeFromUrl.url(), {
            url: photo.url_regular,
            filename: `unsplash-${photo.id}.jpg`,
            download_location: photo.download_location,
        });

        router.reload({ only: ['assets'], reset: ['assets'] });
    } catch {
        // ignore
    } finally {
        savingPhotoId.value = null;
    }
};

const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
};
</script>

<template>
    <Head :title="$t('assets.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <Tabs default-value="uploads">
                <TabsList>
                    <TabsTrigger value="uploads">{{ $t('assets.tabs.my_uploads') }}</TabsTrigger>
                    <TabsTrigger value="stock">{{ $t('assets.tabs.stock_photos') }}</TabsTrigger>
                </TabsList>

                <!-- My Uploads -->
                <TabsContent value="uploads" class="mt-6">
                    <!-- Upload Zone -->
                    <div
                        class="relative mb-6 flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-10 transition-colors"
                        :class="isDragging ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50'"
                        @click="triggerFileInput"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="handleDrop"
                    >
                        <IconCloudUpload class="mb-3 size-10 text-muted-foreground" />
                        <p class="text-sm font-medium">{{ $t('assets.upload.drag_drop') }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ $t('assets.upload.formats') }}</p>
                        <input
                            ref="fileInput"
                            type="file"
                            class="hidden"
                            multiple
                            accept="image/jpeg,image/png,image/gif,image/webp,video/mp4"
                            @change="handleFileSelect"
                        />
                        <div v-if="uploading" class="absolute inset-0 flex items-center justify-center rounded-lg bg-background/80">
                            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                <div class="size-4 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                                {{ $t('assets.upload.uploading') }}
                            </div>
                        </div>
                    </div>

                    <!-- Assets Grid -->
                    <EmptyState
                        v-if="assets.data.length === 0 && !uploading"
                        :icon="IconPhoto"
                        :title="$t('assets.empty.title')"
                        :description="$t('assets.empty.description')"
                    />

                    <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                        <div
                            v-for="asset in assets.data"
                            :key="asset.id"
                            class="group relative overflow-hidden rounded-lg border bg-muted"
                        >
                            <div class="aspect-square">
                                <video
                                    v-if="asset.type === 'video'"
                                    :src="asset.url"
                                    class="size-full object-cover"
                                    muted
                                />
                                <img
                                    v-else
                                    :src="asset.url"
                                    :alt="asset.original_filename"
                                    class="size-full object-cover"
                                    loading="lazy"
                                />
                            </div>

                            <!-- Hover overlay -->
                            <div class="absolute inset-0 flex flex-col justify-between bg-black/60 p-2 opacity-0 transition-opacity group-hover:opacity-100">
                                <div class="flex justify-end">
                                    <Button
                                        variant="destructive"
                                        size="icon"
                                        class="size-7"
                                        @click="handleDelete(asset.id)"
                                    >
                                        <IconTrash class="size-3.5" />
                                    </Button>
                                </div>
                                <div class="space-y-0.5">
                                    <p class="truncate text-xs font-medium text-white">{{ asset.original_filename }}</p>
                                    <p class="text-xs text-white/70">{{ formatFileSize(asset.size) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <InfiniteScroll data="assets" #default="{ loading }">
                        <div v-if="loading" class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                            <Skeleton v-for="i in 5" :key="i" class="aspect-square rounded-lg" />
                        </div>
                    </InfiniteScroll>
                </TabsContent>

                <!-- Stock Photos (Unsplash) -->
                <TabsContent value="stock" class="mt-6">
                    <div class="relative mb-6">
                        <IconSearch class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="unsplashQuery"
                            :placeholder="$t('assets.unsplash.search_placeholder')"
                            class="pl-9"
                            @input="searchUnsplash"
                        />
                    </div>

                    <!-- Unsplash Results Grid -->
                    <div
                        v-if="unsplashResults.length > 0"
                        class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4"
                    >
                        <div
                            v-for="photo in unsplashResults"
                            :key="photo.id"
                            class="group relative overflow-hidden rounded-lg bg-muted"
                        >
                            <div class="aspect-[4/3]">
                                <img
                                    :src="photo.url_small"
                                    :alt="photo.description || 'Unsplash photo'"
                                    class="size-full object-cover"
                                    loading="lazy"
                                />
                            </div>

                            <!-- Hover overlay -->
                            <div class="absolute inset-0 flex flex-col justify-between bg-black/60 p-2 opacity-0 transition-opacity group-hover:opacity-100">
                                <div class="flex justify-end">
                                    <Button
                                        variant="secondary"
                                        size="icon"
                                        class="size-7"
                                        :disabled="savingPhotoId === photo.id"
                                        @click="saveFromUnsplash(photo)"
                                    >
                                        <div v-if="savingPhotoId === photo.id" class="size-3.5 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                                        <IconDownload v-else class="size-3.5" />
                                    </Button>
                                </div>
                                <div>
                                    <a
                                        :href="photo.author.url + '?utm_source=trypost&utm_medium=referral'"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-xs text-white/80 hover:text-white"
                                    >
                                        {{ photo.author.name }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <EmptyState
                        v-else-if="unsplashQuery && !unsplashLoading"
                        :icon="IconSearch"
                        :title="$t('assets.unsplash.no_results')"
                        :description="$t('assets.unsplash.no_results_description')"
                    />

                    <div v-else-if="!unsplashQuery" class="flex flex-col items-center py-16 text-muted-foreground">
                        <IconPhoto class="mb-3 size-10" />
                        <p class="text-sm">{{ $t('assets.unsplash.start_searching') }}</p>
                    </div>

                    <!-- Loading -->
                    <div v-if="unsplashLoading" class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                        <Skeleton v-for="i in 8" :key="i" class="aspect-[4/3] rounded-lg" />
                    </div>

                    <!-- Load More -->
                    <div v-if="unsplashResults.length > 0 && unsplashPage < unsplashTotalPages" class="mt-6 flex justify-center">
                        <Button variant="outline" :disabled="unsplashLoading" @click="loadMoreUnsplash">
                            {{ $t('assets.unsplash.load_more') }}
                        </Button>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>

    <ConfirmDeleteModal
        ref="deleteModal"
        :title="$t('assets.delete.title')"
        :description="$t('assets.delete.description')"
        :action="$t('assets.delete.confirm')"
        :cancel="$t('assets.delete.cancel')"
    />
</template>
