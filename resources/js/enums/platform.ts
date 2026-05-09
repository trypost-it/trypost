export const Platform = {
    LinkedIn: 'linkedin',
    LinkedInPage: 'linkedin-page',
    X: 'x',
    TikTok: 'tiktok',
    YouTube: 'youtube',
    Facebook: 'facebook',
    Instagram: 'instagram',
    InstagramFacebook: 'instagram-facebook',
    Threads: 'threads',
    Pinterest: 'pinterest',
    Bluesky: 'bluesky',
    Mastodon: 'mastodon',
} as const;

export type PlatformValue = (typeof Platform)[keyof typeof Platform];
