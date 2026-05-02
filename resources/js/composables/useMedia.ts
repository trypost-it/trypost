import { getMediaRulesForContentType } from '@/composables/useMediaRules';

export interface MediaItem {
    id: string;
    url: string;
    type?: string;
    mime_type?: string;
    original_filename?: string;
    size?: number;
    meta?: {
        width?: number;
        height?: number;
        duration?: number;
    };
}

export interface MediaValidationWarning {
    key: string; // short key, e.g. 'gif_not_allowed'
    params: Record<string, string>;
}

const formatBytes = (bytes: number): string => {
    if (bytes >= 1024 * 1024 * 1024) return (bytes / (1024 * 1024 * 1024)).toFixed(1) + ' GB';
    if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return bytes + ' B';
};

const formatDuration = (seconds: number): string => {
    const s = Math.round(seconds);
    if (s < 60) return `${s}s`;
    const m = Math.floor(s / 60);
    const rem = s % 60;
    return rem === 0 ? `${m}min` : `${m}min ${rem}s`;
};

const formatAspect = (ratio: number): string => ratio.toFixed(2);

/**
 * Return the first violation found for a given content_type + media list.
 * Returns null when everything is valid.
 * Checks are prioritized: presence → counts → format → per-item constraints.
 */
export const getMediaValidationWarning = (
    contentType: string,
    media: MediaItem[],
): MediaValidationWarning | null => {
    if (! contentType) return { key: 'no_variant', params: {} };

    const rules = getMediaRulesForContentType(contentType);
    const videos = media.filter((m) => m.type === 'video' || m.mime_type?.startsWith('video/'));
    const images = media.filter((m) => m.type === 'image' || m.mime_type?.startsWith('image/'));
    const gifs = media.filter((m) => m.mime_type === 'image/gif');
    const total = media.length;

    if (rules.requiresMedia && total === 0) {
        return { key: 'requires_media', params: {} };
    }
    if (total > rules.maxFiles) {
        return { key: 'max_files_exceeded', params: { max: String(rules.maxFiles), current: String(total) } };
    }
    if (rules.minFiles && total < rules.minFiles) {
        return { key: 'min_files_required', params: { min: String(rules.minFiles), current: String(total) } };
    }
    if (! rules.acceptVideos && videos.length > 0) {
        return { key: 'no_video_allowed', params: {} };
    }
    if (! rules.acceptImages && images.length > 0) {
        return { key: 'no_image_allowed', params: {} };
    }
    if (! rules.acceptsGif && gifs.length > 0) {
        return { key: 'gif_not_allowed', params: {} };
    }

    for (const m of media) {
        const isVideo = m.type === 'video' || m.mime_type?.startsWith('video/');
        const size = m.size ?? 0;
        const width = m.meta?.width ?? 0;
        const height = m.meta?.height ?? 0;
        const duration = m.meta?.duration ?? 0;

        if (isVideo) {
            if (rules.maxVideoBytes && size > rules.maxVideoBytes) {
                return {
                    key: 'video_too_large',
                    params: { max: formatBytes(rules.maxVideoBytes), current: formatBytes(size) },
                };
            }
            if (rules.maxVideoDurationSec && duration > rules.maxVideoDurationSec) {
                return {
                    key: 'video_too_long',
                    params: { max: formatDuration(rules.maxVideoDurationSec), current: formatDuration(duration) },
                };
            }
        } else if (rules.maxImageBytes && size > rules.maxImageBytes) {
            return {
                key: 'image_too_large',
                params: { max: formatBytes(rules.maxImageBytes), current: formatBytes(size) },
            };
        }

        if (width > 0 && height > 0) {
            const ratio = width / height;
            if (rules.aspectRatioMin && ratio < rules.aspectRatioMin) {
                return {
                    key: 'aspect_ratio_too_narrow',
                    params: { current: formatAspect(ratio), min: formatAspect(rules.aspectRatioMin) },
                };
            }
            if (rules.aspectRatioMax && ratio > rules.aspectRatioMax) {
                return {
                    key: 'aspect_ratio_too_wide',
                    params: { current: formatAspect(ratio), max: formatAspect(rules.aspectRatioMax) },
                };
            }
        }
    }

    return null;
};

/**
 * Read metadata from a File in the browser before uploading.
 * Returns width/height for images, width/height/duration for videos.
 */
export const readFileMetadata = async (file: File): Promise<{ width?: number; height?: number; duration?: number }> => {
    if (file.type.startsWith('image/')) {
        return new Promise((resolve) => {
            const img = new Image();
            const url = URL.createObjectURL(file);
            img.onload = () => {
                URL.revokeObjectURL(url);
                resolve({ width: img.naturalWidth, height: img.naturalHeight });
            };
            img.onerror = () => {
                URL.revokeObjectURL(url);
                resolve({});
            };
            img.src = url;
        });
    }

    if (file.type.startsWith('video/')) {
        return new Promise((resolve) => {
            const video = document.createElement('video');
            const url = URL.createObjectURL(file);
            video.preload = 'metadata';
            video.onloadedmetadata = () => {
                URL.revokeObjectURL(url);
                resolve({
                    width: video.videoWidth,
                    height: video.videoHeight,
                    duration: video.duration,
                });
            };
            video.onerror = () => {
                URL.revokeObjectURL(url);
                resolve({});
            };
            video.src = url;
        });
    }

    return {};
};

export const isVideoMedia = (item: MediaItem | null | undefined): boolean => {
    if (! item) return false;
    return item.type === 'video' || Boolean(item.mime_type?.startsWith('video/'));
};

export const isImageMedia = (item: MediaItem | null | undefined): boolean => {
    if (! item) return false;
    if (isVideoMedia(item)) return false;
    return true;
};
