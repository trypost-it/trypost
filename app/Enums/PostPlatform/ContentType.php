<?php

namespace App\Enums\PostPlatform;

use App\Enums\SocialAccount\Platform as SocialPlatform;

enum ContentType: string
{
    // Instagram
    case InstagramFeed = 'instagram_feed';
    case InstagramReel = 'instagram_reel';
    case InstagramStory = 'instagram_story';

    // LinkedIn
    case LinkedInPost = 'linkedin_post';
    case LinkedInCarousel = 'linkedin_carousel';

    // LinkedIn Page
    case LinkedInPagePost = 'linkedin_page_post';
    case LinkedInPageCarousel = 'linkedin_page_carousel';

    // Facebook
    case FacebookPost = 'facebook_post';
    case FacebookReel = 'facebook_reel';
    case FacebookStory = 'facebook_story';

    // TikTok
    case TikTokVideo = 'tiktok_video';

    // YouTube
    case YouTubeShort = 'youtube_short';

    // X (Twitter)
    case XPost = 'x_post';

    // Threads
    case ThreadsPost = 'threads_post';

    // Pinterest
    case PinterestPin = 'pinterest_pin';
    case PinterestVideoPin = 'pinterest_video_pin';
    case PinterestCarousel = 'pinterest_carousel';

    // Bluesky
    case BlueskyPost = 'bluesky_post';

    // Mastodon
    case MastodonPost = 'mastodon_post';

    public function label(): string
    {
        return match ($this) {
            self::InstagramFeed => 'Feed Post',
            self::InstagramReel => 'Reel',
            self::InstagramStory => 'Story',
            self::LinkedInPost, self::LinkedInPagePost => 'Post',
            self::LinkedInCarousel, self::LinkedInPageCarousel => 'Carousel',
            self::FacebookPost => 'Post',
            self::FacebookReel => 'Reel',
            self::FacebookStory => 'Story',
            self::TikTokVideo => 'Video',
            self::YouTubeShort => 'Short',
            self::XPost => 'Post',
            self::ThreadsPost => 'Post',
            self::PinterestPin => 'Pin',
            self::PinterestVideoPin => 'Video Pin',
            self::PinterestCarousel => 'Carousel',
            self::BlueskyPost => 'Post',
            self::MastodonPost => 'Post',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::InstagramFeed => 'Appears in your feed and profile',
            self::InstagramReel => 'Short video up to 90 seconds',
            self::InstagramStory => 'Disappears after 24 hours',
            self::LinkedInPost, self::LinkedInPagePost => 'Standard post with text and media',
            self::LinkedInCarousel, self::LinkedInPageCarousel => 'Swipeable document or images',
            self::FacebookPost => 'Standard post on your page',
            self::FacebookReel => 'Short video up to 90 seconds',
            self::FacebookStory => 'Disappears after 24 hours',
            self::TikTokVideo => 'Short-form video content',
            self::YouTubeShort => 'Vertical video up to 60 seconds',
            self::XPost => 'Tweet with text and media',
            self::ThreadsPost => 'Text post with optional media',
            self::PinterestPin => 'Standard image pin',
            self::PinterestVideoPin => 'Video pin (4s - 15min)',
            self::PinterestCarousel => 'Multi-image carousel (2-5 images)',
            self::BlueskyPost => 'Text post with optional images',
            self::MastodonPost => 'Text post with optional media',
        };
    }

    public function platform(): SocialPlatform
    {
        return match ($this) {
            self::InstagramFeed, self::InstagramReel, self::InstagramStory => SocialPlatform::Instagram,
            self::LinkedInPost, self::LinkedInCarousel => SocialPlatform::LinkedIn,
            self::LinkedInPagePost, self::LinkedInPageCarousel => SocialPlatform::LinkedInPage,
            self::FacebookPost, self::FacebookReel, self::FacebookStory => SocialPlatform::Facebook,
            self::TikTokVideo => SocialPlatform::TikTok,
            self::YouTubeShort => SocialPlatform::YouTube,
            self::XPost => SocialPlatform::X,
            self::ThreadsPost => SocialPlatform::Threads,
            self::PinterestPin, self::PinterestVideoPin, self::PinterestCarousel => SocialPlatform::Pinterest,
            self::BlueskyPost => SocialPlatform::Bluesky,
            self::MastodonPost => SocialPlatform::Mastodon,
        };
    }

    public function aspectRatio(): ?string
    {
        return match ($this) {
            self::InstagramFeed => '1:1',
            self::InstagramReel, self::InstagramStory => '9:16',
            self::FacebookReel, self::FacebookStory => '9:16',
            self::TikTokVideo, self::YouTubeShort => '9:16',
            self::PinterestPin, self::PinterestCarousel => '2:3',
            self::PinterestVideoPin => '9:16',
            default => null,
        };
    }

    public function maxMediaCount(): int
    {
        return match ($this) {
            self::InstagramFeed => 10,
            self::InstagramReel, self::InstagramStory => 1,
            self::LinkedInPost, self::LinkedInPagePost => 1,
            self::LinkedInCarousel, self::LinkedInPageCarousel => 20,
            self::FacebookPost => 10,
            self::FacebookReel, self::FacebookStory => 1,
            self::TikTokVideo => 1,
            self::YouTubeShort => 1,
            self::XPost => 4,
            self::ThreadsPost => 10,
            self::PinterestPin, self::PinterestVideoPin => 1,
            self::PinterestCarousel => 5,
            self::BlueskyPost => 4,
            self::MastodonPost => 4,
        };
    }

    public function supportsVideo(): bool
    {
        return match ($this) {
            self::InstagramFeed, self::InstagramReel, self::InstagramStory => true,
            self::LinkedInPost, self::LinkedInPagePost => true,
            self::LinkedInCarousel, self::LinkedInPageCarousel => false,
            self::FacebookPost, self::FacebookReel, self::FacebookStory => true,
            self::TikTokVideo => true,
            self::YouTubeShort => true,
            self::XPost => true,
            self::ThreadsPost => true,
            self::PinterestVideoPin => true,
            self::PinterestPin, self::PinterestCarousel => false,
            self::BlueskyPost => true,
            self::MastodonPost => true,
        };
    }

    public function supportsImage(): bool
    {
        return match ($this) {
            self::InstagramReel => false,
            self::TikTokVideo => false,
            self::YouTubeShort => false,
            self::PinterestVideoPin => false,
            default => true,
        };
    }

    public function requiresMedia(): bool
    {
        return match ($this) {
            self::LinkedInPost, self::LinkedInPagePost => false,
            self::XPost => false,
            self::ThreadsPost => false,
            self::BlueskyPost => false,
            self::MastodonPost => false,
            default => true,
        };
    }

    /**
     * Get all content types for a specific platform.
     *
     * @return array<self>
     */
    public static function forPlatform(SocialPlatform $platform): array
    {
        return array_filter(
            self::cases(),
            fn (self $type) => $type->platform() === $platform
        );
    }

    /**
     * Get the default content type for a platform.
     */
    public static function defaultFor(SocialPlatform $platform): self
    {
        return match ($platform) {
            SocialPlatform::Instagram => self::InstagramFeed,
            SocialPlatform::LinkedIn => self::LinkedInPost,
            SocialPlatform::LinkedInPage => self::LinkedInPagePost,
            SocialPlatform::Facebook => self::FacebookPost,
            SocialPlatform::TikTok => self::TikTokVideo,
            SocialPlatform::YouTube => self::YouTubeShort,
            SocialPlatform::X => self::XPost,
            SocialPlatform::Threads => self::ThreadsPost,
            SocialPlatform::Pinterest => self::PinterestPin,
            SocialPlatform::Bluesky => self::BlueskyPost,
            SocialPlatform::Mastodon => self::MastodonPost,
        };
    }
}
