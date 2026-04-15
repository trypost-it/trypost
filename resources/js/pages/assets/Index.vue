<script setup lang="ts">
import { Head, InfiniteScroll, router, useHttp } from '@inertiajs/vue3';
import { IconCloudUpload, IconPencilPlus, IconPhoto, IconPlus, IconSearch, IconTrash } from '@tabler/icons-vue';
import { computed, onUnmounted, ref } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import debounce from '@/debounce';
import AppLayout from '@/layouts/AppLayout.vue';
import { destroy as assetsDestroy, store as assetsStore, storeFromUrl } from '@/routes/app/assets';
import { search as giphySearch, trending as giphyTrending } from '@/routes/app/assets/giphy';
import { search as unsplashSearch, trending as unsplashTrending } from '@/routes/app/assets/unsplash';
import { store as storePost } from '@/routes/app/posts';
interface AssetMedia {
    id: string;
    path: string;
    url: string;
    type: string;
    mime_type: string;
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

interface GiphyGif {
    id: string;
    title: string;
    url_preview: string;
    url_original: string;
    url_downsized: string;
    width: number;
    height: number;
    size: number;
}

const props = defineProps<{
    assets: ScrollAssets;
}>();

const httpGet = useHttp({});
const httpUpload = useHttp<{ media: File | null }>({ media: null });

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
        try {
            httpUpload.media = file;
            await httpUpload.post(assetsStore.url());
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
const trendingPhotos = ref<UnsplashPhoto[]>([]);
const trendingPage = ref(1);
const trendingHasMore = ref(true);
const scrollSentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

const displayedPhotos = computed(() => {
    if (unsplashQuery.value && unsplashResults.value.length > 0) {
        return unsplashResults.value;
    }
    if (!unsplashQuery.value) {
        return trendingPhotos.value;
    }
    return [];
});

const hasMorePhotos = computed(() => {
    if (unsplashQuery.value) {
        return unsplashPage.value < unsplashTotalPages.value;
    }
    return trendingHasMore.value;
});

const loadTrending = async (page = 1) => {
    if (unsplashLoading.value) return;

    unsplashLoading.value = true;

    try {
        const response = await httpGet.get(unsplashTrending.url({ query: { page } }));
        const results = response.results ?? [];

        if (page === 1) {
            trendingPhotos.value = results;
        } else {
            trendingPhotos.value.push(...results);
        }

        trendingPage.value = page;
        trendingHasMore.value = results.length >= 25;
    } catch {
        // ignore
    } finally {
        unsplashLoading.value = false;
    }
};

const loadMorePhotos = async () => {
    if (unsplashLoading.value) return;

    if (unsplashQuery.value) {
        await loadMoreUnsplash();
    } else {
        await loadTrending(trendingPage.value + 1);
    }
};

const setupScrollObserver = () => {
    if (observer) observer.disconnect();

    observer = new IntersectionObserver(
        (entries) => {
            if (entries[0]?.isIntersecting && hasMorePhotos.value && !unsplashLoading.value) {
                loadMorePhotos();
            }
        },
        { rootMargin: '200px' },
    );

    if (scrollSentinel.value) {
        observer.observe(scrollSentinel.value);
    }
};

onUnmounted(() => {
    observer?.disconnect();
    giphyObserver?.disconnect();
});

const searchUnsplash = debounce(async () => {
    if (!unsplashQuery.value.trim()) {
        unsplashResults.value = [];
        return;
    }

    unsplashLoading.value = true;
    unsplashPage.value = 1;

    try {
        const response = await httpGet.get(unsplashSearch.url({ query: { query: unsplashQuery.value, page: 1 } }));
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
        const response = await httpGet.get(unsplashSearch.url({ query: { query: unsplashQuery.value, page: unsplashPage.value } }));
        unsplashResults.value.push(...response.results);
    } catch {
        // ignore
    } finally {
        unsplashLoading.value = false;
    }
};

const saveFromUnsplash = (photo: UnsplashPhoto) => {
    savingPhotoId.value = photo.id;

    router.post(storeFromUrl.url(), {
        url: photo.url_regular,
        filename: `unsplash-${photo.id}.jpg`,
        download_location: photo.download_location,
    }, {
        preserveScroll: true,
        onFinish: () => { savingPhotoId.value = null; },
    });
};

// Giphy
const giphyQuery = ref('');
const giphyResults = ref<GiphyGif[]>([]);
const giphyPage = ref(1);
const giphyTotalPages = ref(0);
const giphyLoading = ref(false);
const giphyTrendingPhotos = ref<GiphyGif[]>([]);
const giphyTrendingPage = ref(1);
const giphyTrendingHasMore = ref(true);
const savingGifId = ref<string | null>(null);
const giphyScrollSentinel = ref<HTMLElement | null>(null);
let giphyObserver: IntersectionObserver | null = null;

const displayedGifs = computed(() => {
    if (giphyQuery.value && giphyResults.value.length > 0) {
        return giphyResults.value;
    }
    if (!giphyQuery.value) {
        return giphyTrendingPhotos.value;
    }
    return [];
});

const hasMoreGifs = computed(() => {
    if (giphyQuery.value) {
        return giphyPage.value < giphyTotalPages.value;
    }
    return giphyTrendingHasMore.value;
});

const loadGiphyTrending = async (page = 1) => {
    if (giphyLoading.value) return;

    giphyLoading.value = true;

    try {
        const response = await httpGet.get(giphyTrending.url({ query: { page } }));
        const results = response.results ?? [];

        if (page === 1) {
            giphyTrendingPhotos.value = results;
        } else {
            giphyTrendingPhotos.value.push(...results);
        }

        giphyTrendingPage.value = page;
        giphyTrendingHasMore.value = results.length >= 25;
    } catch {
        // ignore
    } finally {
        giphyLoading.value = false;
    }
};

const searchGiphy = debounce(async () => {
    if (!giphyQuery.value.trim()) {
        giphyResults.value = [];
        return;
    }

    giphyLoading.value = true;
    giphyPage.value = 1;

    try {
        const response = await httpGet.get(giphySearch.url({ query: { query: giphyQuery.value, page: 1 } }));
        giphyResults.value = response.results;
        giphyTotalPages.value = response.total_pages;
    } catch {
        giphyResults.value = [];
    } finally {
        giphyLoading.value = false;
    }
}, 400);

const loadMoreGiphy = async () => {
    if (giphyPage.value >= giphyTotalPages.value || giphyLoading.value) return;

    giphyLoading.value = true;
    giphyPage.value++;

    try {
        const response = await httpGet.get(giphySearch.url({ query: { query: giphyQuery.value, page: giphyPage.value } }));
        giphyResults.value.push(...response.results);
    } catch {
        // ignore
    } finally {
        giphyLoading.value = false;
    }
};

const loadMoreGifs = async () => {
    if (giphyLoading.value) return;

    if (giphyQuery.value) {
        await loadMoreGiphy();
    } else {
        await loadGiphyTrending(giphyTrendingPage.value + 1);
    }
};

const setupGiphyScrollObserver = () => {
    if (giphyObserver) giphyObserver.disconnect();

    giphyObserver = new IntersectionObserver(
        (entries) => {
            if (entries[0]?.isIntersecting && hasMoreGifs.value && !giphyLoading.value) {
                loadMoreGifs();
            }
        },
        { rootMargin: '200px' },
    );

    if (giphyScrollSentinel.value) {
        giphyObserver.observe(giphyScrollSentinel.value);
    }
};

const saveFromGiphy = (gif: GiphyGif) => {
    savingGifId.value = gif.id;

    router.post(storeFromUrl.url(), {
        url: gif.url_downsized,
        filename: `giphy-${gif.id}.gif`,
    }, {
        preserveScroll: true,
        onFinish: () => { savingGifId.value = null; },
    });
};

const createPostFromAsset = (asset: AssetMedia) => {
    router.post(storePost.url(), {
        media: [{ id: asset.id, path: asset.path, url: asset.url, type: asset.type, mime_type: asset.mime_type }],
    });
};

const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
};
</script>

<template>
    <Head :title="$t('assets.title')" />

    <AppLayout :title="$t('assets.title')">
        <div class="flex flex-col gap-6 p-6">
            <Tabs default-value="uploads">
                <TabsList>
                    <TabsTrigger value="uploads">{{ $t('assets.tabs.my_uploads') }}</TabsTrigger>
                    <TabsTrigger value="stock">{{ $t('assets.tabs.stock_photos') }}</TabsTrigger>
                    <TabsTrigger value="gifs">{{ $t('assets.tabs.gifs') }}</TabsTrigger>
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
                    <div v-if="assets.data.length > 0" class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
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
                                <div class="flex justify-end gap-1">
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button
                                                    variant="secondary"
                                                    size="icon"
                                                    class="size-7"
                                                    @click="createPostFromAsset(asset)"
                                                >
                                                    <IconPencilPlus class="size-3.5" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>{{ $t('assets.create_post') }}</TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
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
                <TabsContent value="stock" class="mt-6" @vue:mounted="() => { loadTrending(); setupScrollObserver(); }">
                    <div class="relative mb-6">
                        <IconSearch class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="unsplashQuery"
                            :placeholder="$t('assets.unsplash.search_placeholder')"
                            class="pl-9"
                            @input="searchUnsplash"
                        />
                    </div>

                    <!-- Unsplash Results Grid (search or trending) -->
                    <div v-if="displayedPhotos.length > 0" class="space-y-3">
                        <p v-if="!unsplashQuery && trendingPhotos.length > 0" class="text-sm font-medium text-muted-foreground">
                            {{ $t('assets.unsplash.trending') }}
                        </p>

                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                            <div
                                v-for="photo in displayedPhotos"
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
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        variant="secondary"
                                                        size="icon"
                                                        class="size-7"
                                                        :disabled="savingPhotoId === photo.id"
                                                        @click="saveFromUnsplash(photo)"
                                                    >
                                                        <div v-if="savingPhotoId === photo.id" class="size-3.5 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                                                        <IconPlus v-else class="size-3.5" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>{{ $t('assets.save_to_assets') }}</TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </div>
                                    <p class="text-xs text-white/80">
                                        <a
                                            :href="photo.author.url + '?utm_source=trypost&utm_medium=referral'"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="hover:text-white"
                                        >{{ photo.author.name }}</a>
                                        <span class="text-white/50"> / </span>
                                        <a
                                            href="https://unsplash.com/?utm_source=trypost&utm_medium=referral"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="hover:text-white"
                                        >Unsplash</a>
                                    </p>
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

                    <!-- Loading skeletons -->
                    <div v-if="unsplashLoading" class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                        <Skeleton v-for="i in 8" :key="i" class="aspect-[4/3] rounded-lg" />
                    </div>

                    <!-- Scroll sentinel for infinite scroll -->
                    <div v-if="hasMorePhotos" ref="scrollSentinel" class="h-1" @vue:mounted="setupScrollObserver" />
                </TabsContent>

                <!-- GIFs (Giphy) -->
                <TabsContent value="gifs" class="mt-6" @vue:mounted="() => { loadGiphyTrending(); setupGiphyScrollObserver(); }">
                    <div class="relative mb-6">
                        <IconSearch class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="giphyQuery"
                            :placeholder="$t('assets.giphy.search_placeholder')"
                            class="pl-9"
                            @input="searchGiphy"
                        />
                    </div>

                    <!-- Giphy Results Grid (search or trending) -->
                    <div v-if="displayedGifs.length > 0" class="space-y-3">
                        <p v-if="!giphyQuery && giphyTrendingPhotos.length > 0" class="text-sm font-medium text-muted-foreground">
                            {{ $t('assets.giphy.trending') }}
                        </p>

                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                            <div
                                v-for="gif in displayedGifs"
                                :key="gif.id"
                                class="group relative overflow-hidden rounded-lg bg-muted"
                            >
                                <div class="aspect-[4/3]">
                                    <img
                                        :src="gif.url_preview"
                                        :alt="gif.title || 'GIF'"
                                        class="size-full object-cover"
                                        loading="lazy"
                                    />
                                </div>

                                <!-- Hover overlay -->
                                <div class="absolute inset-0 flex flex-col justify-between bg-black/60 p-2 opacity-0 transition-opacity group-hover:opacity-100">
                                    <div class="flex justify-end">
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        variant="secondary"
                                                        size="icon"
                                                        class="size-7"
                                                        :disabled="savingGifId === gif.id"
                                                        @click="saveFromGiphy(gif)"
                                                    >
                                                        <div v-if="savingGifId === gif.id" class="size-3.5 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                                                        <IconPlus v-else class="size-3.5" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>{{ $t('assets.save_to_assets') }}</TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </div>
                                    <p v-if="gif.title" class="truncate text-xs text-white/80">{{ gif.title }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <EmptyState
                        v-else-if="giphyQuery && !giphyLoading"
                        :icon="IconSearch"
                        :title="$t('assets.giphy.no_results')"
                        :description="$t('assets.giphy.no_results_description')"
                    />

                    <!-- Loading skeletons -->
                    <div v-if="giphyLoading" class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                        <Skeleton v-for="i in 8" :key="i" class="aspect-[4/3] rounded-lg" />
                    </div>

                    <!-- Scroll sentinel for infinite scroll -->
                    <div v-if="hasMoreGifs" ref="giphyScrollSentinel" class="h-1" @vue:mounted="setupGiphyScrollObserver" />

                    <!-- Giphy attribution (required by API terms) -->
                    <div v-if="displayedGifs.length > 0" class="mt-4 text-center">
                        <a href="https://giphy.com" target="_blank" rel="noopener noreferrer" class="text-xs text-muted-foreground hover:text-foreground">
                            {{ $t('assets.giphy.powered_by') }}
                        </a>
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
