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

export const formatBytes = (bytes: number): string => {
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
 * Returns a short reason key when a single media item doesn't fit a content
 * type's per-item rules (type, size, duration, aspect ratio). Set-level rules
 * (count, requires_media) are NOT checked here — use getMediaValidationWarning
 * for those.
 */
export const getMediaItemIssue = (item: MediaItem, contentType: string): string | null => {
    if (! contentType) return null;

    const rules = getMediaRulesForContentType(contentType);
    const isVideo = item.type === 'video' || Boolean(item.mime_type?.startsWith('video/'));
    const isGif = item.mime_type === 'image/gif';

    if (isVideo && ! rules.acceptVideos) return 'no_video_allowed';
    if (! isVideo && ! rules.acceptImages) return 'no_image_allowed';
    if (isGif && ! rules.acceptsGif) return 'gif_not_allowed';

    const size = item.size ?? 0;
    if (isVideo && rules.maxVideoBytes && size > rules.maxVideoBytes) return 'video_too_large';
    if (! isVideo && rules.maxImageBytes && size > rules.maxImageBytes) return 'image_too_large';

    const duration = item.meta?.duration ?? 0;
    if (isVideo && rules.maxVideoDurationSec && duration > rules.maxVideoDurationSec) {
        return 'video_too_long';
    }

    const width = item.meta?.width ?? 0;
    const height = item.meta?.height ?? 0;
    if (width > 0 && height > 0) {
        const ratio = width / height;
        if (rules.aspectRatioMin && ratio < rules.aspectRatioMin) return 'aspect_ratio_too_narrow';
        if (rules.aspectRatioMax && ratio > rules.aspectRatioMax) return 'aspect_ratio_too_wide';
    }

    return null;
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
