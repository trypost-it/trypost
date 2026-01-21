import { computed, type Ref, type ComputedRef } from 'vue';

export interface MediaRules {
    maxFiles: number;
    minFiles?: number;
    acceptImages: boolean;
    acceptVideos: boolean;
    requiresMedia: boolean;
}

const CONTENT_TYPE_RULES: Record<string, MediaRules> = {
    // Instagram
    instagram_feed: { maxFiles: 10, acceptImages: true, acceptVideos: true, requiresMedia: true },
    instagram_reel: { maxFiles: 1, acceptImages: false, acceptVideos: true, requiresMedia: true },
    instagram_story: { maxFiles: 1, acceptImages: true, acceptVideos: true, requiresMedia: true },

    // Facebook
    facebook_post: { maxFiles: 10, acceptImages: true, acceptVideos: true, requiresMedia: false },
    facebook_reel: { maxFiles: 1, acceptImages: false, acceptVideos: true, requiresMedia: true },
    facebook_story: { maxFiles: 1, acceptImages: true, acceptVideos: true, requiresMedia: true },

    // LinkedIn
    linkedin_post: { maxFiles: 1, acceptImages: true, acceptVideos: true, requiresMedia: false },
    linkedin_carousel: { maxFiles: 20, acceptImages: true, acceptVideos: false, requiresMedia: true },
    linkedin_page_post: { maxFiles: 1, acceptImages: true, acceptVideos: true, requiresMedia: false },
    linkedin_page_carousel: { maxFiles: 20, acceptImages: true, acceptVideos: false, requiresMedia: true },

    // TikTok
    tiktok_video: { maxFiles: 1, acceptImages: false, acceptVideos: true, requiresMedia: true },

    // YouTube
    youtube_short: { maxFiles: 1, acceptImages: false, acceptVideos: true, requiresMedia: true },

    // Pinterest
    pinterest_pin: { maxFiles: 1, acceptImages: true, acceptVideos: false, requiresMedia: true },
    pinterest_video_pin: { maxFiles: 1, acceptImages: false, acceptVideos: true, requiresMedia: true },
    pinterest_carousel: { maxFiles: 5, minFiles: 2, acceptImages: true, acceptVideos: false, requiresMedia: true },

    // X (Twitter)
    x_post: { maxFiles: 4, acceptImages: true, acceptVideos: true, requiresMedia: false },

    // Threads
    threads_post: { maxFiles: 10, acceptImages: true, acceptVideos: true, requiresMedia: false },

    // Bluesky
    bluesky_post: { maxFiles: 4, acceptImages: true, acceptVideos: true, requiresMedia: false },

    // Mastodon
    mastodon_post: { maxFiles: 4, acceptImages: true, acceptVideos: true, requiresMedia: false },
};

const DEFAULT_RULES: MediaRules = {
    maxFiles: 10,
    acceptImages: true,
    acceptVideos: true,
    requiresMedia: false,
};

export function useMediaRules(contentType: Ref<string> | ComputedRef<string>) {
    const rules = computed<MediaRules>(() => {
        return CONTENT_TYPE_RULES[contentType.value] || DEFAULT_RULES;
    });

    const acceptMimeTypes = computed<string>(() => {
        const types: string[] = [];
        if (rules.value.acceptImages) {
            types.push('image/*');
        }
        if (rules.value.acceptVideos) {
            types.push('video/*');
        }
        return types.join(',');
    });

    const canAddMore = computed(() => {
        return (currentCount: number) => currentCount < rules.value.maxFiles;
    });

    const isValidFileType = computed(() => {
        return (file: File): boolean => {
            const isImage = file.type.startsWith('image/');
            const isVideo = file.type.startsWith('video/');

            if (isImage && !rules.value.acceptImages) {
                return false;
            }
            if (isVideo && !rules.value.acceptVideos) {
                return false;
            }
            return isImage || isVideo;
        };
    });

    const getAcceptDescription = computed<string>(() => {
        if (rules.value.acceptImages && rules.value.acceptVideos) {
            return 'Images or videos';
        }
        if (rules.value.acceptImages) {
            return 'Images only';
        }
        if (rules.value.acceptVideos) {
            return 'Videos only';
        }
        return 'No media';
    });

    return {
        rules,
        acceptMimeTypes,
        canAddMore,
        isValidFileType,
        getAcceptDescription,
    };
}

export function getMediaRulesForContentType(contentType: string): MediaRules {
    return CONTENT_TYPE_RULES[contentType] || DEFAULT_RULES;
}
