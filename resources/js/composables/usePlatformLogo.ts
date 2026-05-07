const PLATFORM_LOGOS: Record<string, string> = {
    linkedin: '/images/accounts/linkedin.png',
    'linkedin-page': '/images/accounts/linkedin.png',
    x: '/images/accounts/x.png',
    tiktok: '/images/accounts/tiktok.png',
    instagram: '/images/accounts/instagram.png',
    'instagram-facebook': '/images/accounts/instagram.png',
    facebook: '/images/accounts/facebook.png',
    youtube: '/images/accounts/youtube.png',
    threads: '/images/accounts/threads.png',
    bluesky: '/images/accounts/bluesky.png',
    pinterest: '/images/accounts/pinterest.png',
    mastodon: '/images/accounts/mastodon.png',
};

const PLATFORM_LABELS: Record<string, string> = {
    linkedin: 'LinkedIn',
    'linkedin-page': 'LinkedIn Page',
    x: 'X',
    tiktok: 'TikTok',
    instagram: 'Instagram',
    'instagram-facebook': 'Instagram',
    facebook: 'Facebook',
    youtube: 'YouTube',
    threads: 'Threads',
    bluesky: 'Bluesky',
    pinterest: 'Pinterest',
    mastodon: 'Mastodon',
};

const PLATFORM_CONTENT_TYPES: Record<string, string[]> = {
    instagram: ['instagram_feed', 'instagram_reel', 'instagram_story'],
    'instagram-facebook': [
        'instagram_feed',
        'instagram_reel',
        'instagram_story',
    ],
    linkedin: ['linkedin_post', 'linkedin_carousel'],
    'linkedin-page': ['linkedin_page_post', 'linkedin_page_carousel'],
    facebook: ['facebook_post', 'facebook_reel', 'facebook_story'],
    tiktok: ['tiktok_video'],
    youtube: ['youtube_short'],
    x: ['x_post'],
    threads: ['threads_post'],
    pinterest: ['pinterest_pin', 'pinterest_video_pin', 'pinterest_carousel'],
    bluesky: ['bluesky_post'],
    mastodon: ['mastodon_post'],
};

export interface ContentTypeOption {
    value: string;
    labelKey: string;
}

export const getPlatformLogo = (platform: string): string =>
    PLATFORM_LOGOS[platform] ?? PLATFORM_LOGOS.linkedin;

export const getPlatformLabel = (platform: string): string =>
    PLATFORM_LABELS[platform] ?? platform;

export const getContentTypeOptions = (platform: string): ContentTypeOption[] =>
    (PLATFORM_CONTENT_TYPES[platform] ?? []).map((value) => ({
        value,
        labelKey: `posts.content_types.${value}.label`,
    }));
