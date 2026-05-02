export const ContentType = {
    InstagramFeed: 'instagram_feed',
    InstagramReel: 'instagram_reel',
    InstagramStory: 'instagram_story',
    LinkedInPost: 'linkedin_post',
    LinkedInCarousel: 'linkedin_carousel',
    LinkedInPagePost: 'linkedin_page_post',
    LinkedInPageCarousel: 'linkedin_page_carousel',
    FacebookPost: 'facebook_post',
    FacebookReel: 'facebook_reel',
    FacebookStory: 'facebook_story',
    TikTokVideo: 'tiktok_video',
    YouTubeShort: 'youtube_short',
    XPost: 'x_post',
    ThreadsPost: 'threads_post',
    PinterestPin: 'pinterest_pin',
    PinterestVideoPin: 'pinterest_video_pin',
    PinterestCarousel: 'pinterest_carousel',
    BlueskyPost: 'bluesky_post',
    MastodonPost: 'mastodon_post',
} as const;

export type ContentTypeValue = (typeof ContentType)[keyof typeof ContentType];
