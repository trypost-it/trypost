import axios from 'axios';
import { ref, type Ref } from 'vue';

import {
    store as storeMedia,
    storeChunked as storeMediaChunked,
    destroy as destroyMedia,
    duplicate as duplicateMedia,
    reorder as reorderMedia,
} from '@/actions/App/Http/Controllers/MediaController';
import { getMediaRulesForContentType } from '@/composables/useMediaRules';
import { uploadChunked, shouldUseChunkedUpload } from '@/utils/chunkedUpload';

export interface MediaItem {
    id: string;
    group_id: string | null;
    url: string;
    type: string;
    original_filename: string;
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

export function useMediaManager(options: UseMediaManagerOptions) {
    const { synced, selectedPlatformIds, platformContentTypes, postPlatforms } = options;

    // State
    const platformMedia = ref<Record<string, MediaItem[]>>(
        Object.fromEntries(postPlatforms.value.map(pp => [pp.id, pp.media || []]))
    );
    const isUploading = ref<Record<string, boolean>>({});

    // Helper: check if platform's content type only allows single media
    const isSingleMediaContentType = (platformId: string): boolean => {
        const platform = postPlatforms.value.find(pp => pp.id === platformId);
        if (!platform) return false;

        const contentType = platformContentTypes.value[platformId] || platform.content_type;
        if (!contentType) return false;

        const rules = getMediaRulesForContentType(contentType);
        return rules.maxFiles === 1;
    };

    // Helper: clear all media from a platform
    const clearPlatformMedia = async (platformId: string) => {
        const media = platformMedia.value[platformId] || [];

        for (const m of media) {
            await axios.delete(destroyMedia.url({ modelId: platformId, media: m.id }));
        }

        platformMedia.value[platformId] = [];
    };

    // Upload files to a platform (with sync support)
    const upload = async (files: File[], postPlatformId: string) => {
        if (!files || files.length === 0) return;

        // Get content type rules to check max files
        const platform = postPlatforms.value.find(pp => pp.id === postPlatformId);
        const contentType = platformContentTypes.value[postPlatformId] || platform?.content_type || '';
        const rules = getMediaRulesForContentType(contentType);

        // Get other platforms to duplicate to (if synced)
        const otherPlatformIds = synced.value
            ? selectedPlatformIds.value.filter(id => id !== postPlatformId)
            : [];

        // Mark all as uploading
        isUploading.value[postPlatformId] = true;
        for (const id of otherPlatformIds) {
            isUploading.value[id] = true;
        }

        // For single-media content types, clear existing media first
        if (rules.maxFiles === 1) {
            await clearPlatformMedia(postPlatformId);
        }

        // Calculate how many files we can still add
        const currentMedia = platformMedia.value[postPlatformId] || [];
        const remainingSlots = rules.maxFiles - currentMedia.length;
        const filesToUpload = Array.from(files).slice(0, remainingSlots);

        if (filesToUpload.length === 0) {
            // No slots available
            isUploading.value[postPlatformId] = false;
            for (const id of otherPlatformIds) {
                isUploading.value[id] = false;
            }
            return;
        }

        for (const file of filesToUpload) {
            try {
                let data;

                // Use chunked upload for large files (> 10MB)
                if (shouldUseChunkedUpload(file)) {
                    data = await uploadChunked({
                        file,
                        url: storeMediaChunked.url(),
                        model: 'postPlatform',
                        modelId: postPlatformId,
                        collection: 'default',
                        onProgress: (progress) => {
                            console.log(`Upload progress: ${progress}%`);
                        },
                    });
                } else {
                    // Regular upload for small files
                    const formData = new FormData();
                    formData.append('media', file);
                    formData.append('model', 'postPlatform');
                    formData.append('model_id', postPlatformId);

                    const response = await axios.post(storeMedia.url(), formData);
                    data = response.data;
                }

                // Add to current platform (use spread for reactivity)
                const currentMediaList = platformMedia.value[postPlatformId] || [];
                platformMedia.value[postPlatformId] = [...currentMediaList, data];

                // If synced, duplicate to other platforms
                if (otherPlatformIds.length > 0) {
                    const targets = otherPlatformIds.map(id => ({
                        model: 'postPlatform',
                        model_id: id,
                    }));

                    const duplicateResponse = await axios.post(
                        duplicateMedia.url({ media: data.id }),
                        { targets }
                    );

                    const duplicates = duplicateResponse.data;
                    for (const dup of duplicates) {
                        // For single-media platforms, clear first
                        if (isSingleMediaContentType(dup.mediable_id)) {
                            await clearPlatformMedia(dup.mediable_id);
                        }
                        const existingMedia = platformMedia.value[dup.mediable_id] || [];
                        platformMedia.value[dup.mediable_id] = [...existingMedia, dup];
                    }
                }
            } catch (error) {
                console.error('Upload failed:', error);
            }
        }

        // Mark all as done
        isUploading.value[postPlatformId] = false;
        for (const id of otherPlatformIds) {
            isUploading.value[id] = false;
        }
    };

    // Remove media from a platform (with sync support)
    const remove = async (postPlatformId: string, mediaId: string) => {
        // Find the media to get its group_id for synced removal
        const mediaToRemove = platformMedia.value[postPlatformId]?.find(m => m.id === mediaId);

        if (!mediaToRemove) return;

        // Get target platforms - all selected if synced, otherwise just the current one
        const targetPlatformIds = synced.value ? selectedPlatformIds.value : [postPlatformId];

        for (const targetId of targetPlatformIds) {
            // Find media with same group_id in this platform
            const mediaInPlatform = platformMedia.value[targetId]?.find(
                m => m.group_id === mediaToRemove.group_id
            );

            if (mediaInPlatform) {
                // Remove from local state
                platformMedia.value[targetId] = platformMedia.value[targetId].filter(
                    m => m.id !== mediaInPlatform.id
                );

                // Delete from server
                await axios.delete(destroyMedia.url({ modelId: targetId, media: mediaInPlatform.id }));
            }
        }
    };

    // Reorder media in a platform (with sync support)
    const reorder = async (postPlatformId: string, mediaIds: string[]) => {
        // Get the current platform's media to extract group_ids in new order
        const currentMedia = platformMedia.value[postPlatformId] || [];
        const reorderedMedia = mediaIds.map(id => currentMedia.find(m => m.id === id)).filter(Boolean) as MediaItem[];

        // Get the group_ids in new order (for syncing to other platforms)
        const groupIdsInOrder = reorderedMedia.map(m => m.group_id);
        const firstGroupId = groupIdsInOrder[0];

        // Get target platforms - all selected if synced, otherwise just the current one
        const targetPlatformIds = synced.value ? selectedPlatformIds.value : [postPlatformId];

        // Collect all media items to reorder across all platforms
        const allMediaToReorder: { id: string; order: number }[] = [];

        for (const targetId of targetPlatformIds) {
            const targetMedia = platformMedia.value[targetId] || [];
            const isSingleMedia = isSingleMediaContentType(targetId);

            if (isSingleMedia && synced.value) {
                // For single-media platforms with sync enabled:
                // Check if current media matches the first group_id
                const currentSingleMedia = targetMedia[0];

                if (currentSingleMedia && currentSingleMedia.group_id !== firstGroupId) {
                    // Need to replace: delete current and duplicate the correct one
                    // Find the source media (first of the new order from the active platform)
                    const sourceMedia = reorderedMedia[0];

                    if (sourceMedia) {
                        // Delete current media from this platform
                        await axios.delete(destroyMedia.url({ modelId: targetId, media: currentSingleMedia.id }));

                        // Duplicate the correct media to this platform
                        const duplicateResponse = await axios.post(
                            duplicateMedia.url({ media: sourceMedia.id }),
                            { targets: [{ model: 'postPlatform', model_id: targetId }] }
                        );

                        const duplicate = duplicateResponse.data[0];
                        if (duplicate) {
                            // Update local state with the new media
                            platformMedia.value[targetId] = [{
                                id: duplicate.id,
                                group_id: duplicate.group_id,
                                url: duplicate.url,
                                type: duplicate.type,
                                original_filename: duplicate.original_filename,
                            }];

                            // Add to reorder payload
                            allMediaToReorder.push({ id: duplicate.id, order: 0 });
                        }
                    }
                } else if (currentSingleMedia) {
                    // Media is already correct, just update order
                    allMediaToReorder.push({ id: currentSingleMedia.id, order: 0 });
                }
            } else {
                // For multi-media platforms: reorder based on group_id order
                const reorderedTargetMedia = groupIdsInOrder
                    .map(groupId => targetMedia.find(m => m.group_id === groupId))
                    .filter(Boolean) as MediaItem[];

                // Update local state
                platformMedia.value[targetId] = reorderedTargetMedia;

                // Add to API payload
                reorderedTargetMedia.forEach((m, index) => {
                    allMediaToReorder.push({ id: m.id, order: index });
                });
            }
        }

        // Send all reorders to API in one request
        if (allMediaToReorder.length > 0) {
            await axios.post(reorderMedia.url(), { media: allMediaToReorder });
        }
    };

    // Get media for a specific platform
    const getMedia = (platformId: string): MediaItem[] => {
        return platformMedia.value[platformId] || [];
    };

    // Check if a platform is uploading
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
}
