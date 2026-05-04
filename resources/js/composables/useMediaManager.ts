import { ref, type Ref } from 'vue';

import { getMediaRulesForContentType } from '@/composables/useMediaRules';
import { store as storeAsset, storeChunked as storeAssetChunked } from '@/routes/app/assets';
import { uploadChunked, shouldUseChunkedUpload } from '@/utils/chunkedUpload';

export interface MediaItem {
    id: string;
    path: string;
    url: string;
    type?: string;
    mime_type?: string;
    original_filename?: string;
}

interface PostPlatform {
    id: string;
    platform: string;
    content_type: string | null;
    media: MediaItem[];
}

interface UseMediaManagerOptions {
    synced: Ref<boolean>;
    selectedPlatformIds: Ref<string[]>;
    platformContentTypes: Ref<Record<string, string>>;
    postPlatforms: Ref<PostPlatform[]>;
}

export const useMediaManager = (options: UseMediaManagerOptions) => {
    const { synced, selectedPlatformIds, platformContentTypes, postPlatforms } = options;

    const platformMedia = ref<Record<string, MediaItem[]>>(
        Object.fromEntries(postPlatforms.value.map((pp) => [pp.id, pp.media || []])),
    );
    const isUploading = ref<Record<string, boolean>>({});

    const isSingleMediaContentType = (platformId: string): boolean => {
        const platform = postPlatforms.value.find((pp) => pp.id === platformId);
        if (!platform) return false;

        const contentType = platformContentTypes.value[platformId] || platform.content_type;
        if (!contentType) return false;

        const rules = getMediaRulesForContentType(contentType);
        return rules.maxFiles === 1;
    };

    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

    const uploadToAssets = async (file: File): Promise<MediaItem | null> => {
        try {
            if (shouldUseChunkedUpload(file)) {
                const data = await uploadChunked({
                    file,
                    url: storeAssetChunked.url(),
                    model: 'workspace',
                    modelId: '',
                    collection: 'assets',
                });
                return {
                    id: data.id,
                    path: data.path ?? '',
                    url: data.url,
                    type: data.type,
                    mime_type: data.mime_type,
                    original_filename: data.original_filename,
                };
            }

            const formData = new FormData();
            formData.append('media', file);

            const response = await fetch(storeAsset.url(), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            if (!response.ok) return null;

            const data = await response.json();
            return {
                id: data.id,
                path: data.path ?? '',
                url: data.url,
                type: data.type,
                mime_type: data.mime_type,
                original_filename: data.original_filename,
            };
        } catch {
            return null;
        }
    };

    const upload = async (files: File[], postPlatformId: string) => {
        if (!files || files.length === 0) return;

        const platform = postPlatforms.value.find((pp) => pp.id === postPlatformId);
        const contentType = platformContentTypes.value[postPlatformId] || platform?.content_type || '';
        const rules = getMediaRulesForContentType(contentType);

        const targetPlatformIds = synced.value ? selectedPlatformIds.value : [postPlatformId];

        for (const id of targetPlatformIds) {
            isUploading.value[id] = true;
        }

        if (rules.maxFiles === 1) {
            platformMedia.value[postPlatformId] = [];
        }

        const currentMedia = platformMedia.value[postPlatformId] || [];
        const remainingSlots = rules.maxFiles - currentMedia.length;
        const filesToUpload = Array.from(files).slice(0, remainingSlots);

        if (filesToUpload.length === 0) {
            for (const id of targetPlatformIds) {
                isUploading.value[id] = false;
            }
            return;
        }

        for (const file of filesToUpload) {
            const mediaItem = await uploadToAssets(file);
            if (!mediaItem) continue;

            for (const targetId of targetPlatformIds) {
                if (isSingleMediaContentType(targetId)) {
                    platformMedia.value[targetId] = [mediaItem];
                } else {
                    platformMedia.value[targetId] = [...(platformMedia.value[targetId] || []), mediaItem];
                }
            }
        }

        for (const id of targetPlatformIds) {
            isUploading.value[id] = false;
        }
    };

    const remove = (postPlatformId: string, mediaId: string) => {
        const targetPlatformIds = synced.value ? selectedPlatformIds.value : [postPlatformId];

        for (const targetId of targetPlatformIds) {
            platformMedia.value[targetId] = (platformMedia.value[targetId] || []).filter((m) => m.id !== mediaId);
        }
    };

    const reorder = (postPlatformId: string, mediaIds: string[]) => {
        const currentMedia = platformMedia.value[postPlatformId] || [];
        const reorderedMedia = mediaIds
            .map((id) => currentMedia.find((m) => m.id === id))
            .filter(Boolean) as MediaItem[];

        const targetPlatformIds = synced.value ? selectedPlatformIds.value : [postPlatformId];

        for (const targetId of targetPlatformIds) {
            if (isSingleMediaContentType(targetId)) {
                platformMedia.value[targetId] = reorderedMedia.length > 0 ? [reorderedMedia[0]] : [];
            } else {
                const targetMedia = platformMedia.value[targetId] || [];
                const reorderedIds = reorderedMedia.map((m) => m.id);
                platformMedia.value[targetId] = reorderedIds
                    .map((id) => targetMedia.find((m) => m.id === id) || reorderedMedia.find((m) => m.id === id))
                    .filter(Boolean) as MediaItem[];
            }
        }
    };

    const getMedia = (platformId: string): MediaItem[] => {
        return platformMedia.value[platformId] || [];
    };

    const isUploadingFor = (platformId: string): boolean => {
        return isUploading.value[platformId] || false;
    };

    return {
        platformMedia,
        isUploading,
        upload,
        remove,
        reorder,
        getMedia,
        isUploadingFor,
        isSingleMediaContentType,
    };
};
