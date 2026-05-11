<?php

declare(strict_types=1);

namespace App\Enums\SocialAccount;

use App\Enums\Media\Type as MediaType;

enum Platform: string
{
    case LinkedIn = 'linkedin';
    case LinkedInPage = 'linkedin-page';
    case X = 'x';
    case TikTok = 'tiktok';
    case YouTube = 'youtube';
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case InstagramFacebook = 'instagram-facebook';
    case Threads = 'threads';
    case Pinterest = 'pinterest';
    case Bluesky = 'bluesky';
    case Mastodon = 'mastodon';

    public function label(): string
    {
        return match ($this) {
            self::LinkedIn => 'LinkedIn',
            self::LinkedInPage => 'LinkedIn Page',
            self::X => 'X',
            self::TikTok => 'TikTok',
            self::YouTube => 'YouTube Shorts',
            self::Facebook => 'Facebook Page',
            self::Instagram => 'Instagram (Standalone)',
            self::InstagramFacebook => 'Instagram (Facebook Business)',
            self::Threads => 'Threads',
            self::Pinterest => 'Pinterest',
            self::Bluesky => 'Bluesky',
            self::Mastodon => 'Mastodon',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LinkedIn, self::LinkedInPage => '#0A66C2',
            self::X => '#000000',
            self::TikTok => '#000000',
            self::YouTube => '#FF0000',
            self::Facebook => '#1877F2',
            self::Instagram => '#E4405F',
            self::InstagramFacebook => '#E4405F',
            self::Threads => '#000000',
            self::Pinterest => '#E60023',
            self::Bluesky => '#0085FF',
            self::Mastodon => '#6364FF',
        };
    }

    public function allowedMediaTypes(): array
    {
        return match ($this) {
            self::LinkedIn, self::LinkedInPage => [MediaType::Image, MediaType::Video],
            self::X => [MediaType::Image, MediaType::Video],
            self::TikTok => [MediaType::Video],
            self::YouTube => [MediaType::Video],
            self::Facebook => [MediaType::Image, MediaType::Video],
            self::Instagram, self::InstagramFacebook => [MediaType::Image, MediaType::Video],
            self::Threads => [MediaType::Image, MediaType::Video],
            self::Pinterest => [MediaType::Image, MediaType::Video],
            self::Bluesky => [MediaType::Image, MediaType::Video],
            self::Mastodon => [MediaType::Image, MediaType::Video],
        };
    }

    public function maxImages(): int
    {
        return match ($this) {
            self::LinkedIn, self::LinkedInPage => 1,
            self::X => 4,
            self::TikTok => 0,
            self::YouTube => 0,
            self::Facebook => 10,
            self::Instagram, self::InstagramFacebook => 10,
            self::Threads => 10,
            self::Pinterest => 5,
            self::Bluesky => 4,
            self::Mastodon => 4,
        };
    }

    /**
     * Hard cap (in characters) the platform's API will accept. Going over this
     * means the post can't be published. Values are the documented API maxes:
     *
     *  - LinkedIn UGC: 3000 (`commentary` field)
     *  - X standard tweet: 280 (X Premium accepts 25K — ignored, conservative)
     *  - TikTok caption: 2200
     *  - YouTube Shorts: title=100, description=5000. We feed `content` to both
     *    (publisher derives title from the first line via `buildTitle`), and
     *    Shorts UX only shows ~100 chars before "more" — capping at 100 keeps
     *    posts appropriate for the format.
     *  - Facebook text status: 63206
     *  - Instagram feed caption: 2200
     *  - Threads: 500
     *  - Pinterest pin description: 800 (title is 100, not modeled here)
     *  - Bluesky: 300 graphemes
     *  - Mastodon: 500 default; instances may be higher (we stay conservative)
     */
    public function maxContentLength(): int
    {
        return match ($this) {
            self::LinkedIn, self::LinkedInPage => 3000,
            self::X => 280,
            self::TikTok => 2200,
            self::YouTube => 100,
            self::Facebook => 63206,
            self::Instagram, self::InstagramFacebook => 2200,
            self::Threads => 500,
            self::Pinterest => 800,
            self::Bluesky => 300,
            self::Mastodon => 500,
        };
    }

    /**
     * Number of characters the given content exceeds this platform's hard cap,
     * or null when it fits. Single source of truth for content-length checks —
     * used both at schedule/publish-validation time and at publish time itself
     * so the two paths can never drift apart.
     */
    public function contentOverflow(string $content): ?int
    {
        $over = mb_strlen($content) - $this->maxContentLength();

        return $over > 0 ? $over : null;
    }

    /**
     * Recommended target length (in characters) for AI-generated posts. This
     * is the engagement sweet spot — much shorter than the platform's hard
     * `maxContentLength()`. Use this to instruct the LLM at generation time;
     * use `maxContentLength()` for publish-time validation.
     */
    public function recommendedAiContentLength(): int
    {
        return match ($this) {
            // Microblogging — 70-200 char tweets perform best, leave hashtag room
            self::X, self::Bluesky => 220,
            // Threads/Mastodon — similar feel, slightly more relaxed
            self::Threads, self::Mastodon => 300,
            // LinkedIn — readable long-form sweet spot is ~1200-1500
            self::LinkedIn, self::LinkedInPage => 1200,
            // Instagram captions — most viewers expand only when interested,
            // 100-150 words performs best
            self::Instagram, self::InstagramFacebook => 600,
            // Facebook — short posts dominate the algorithm
            self::Facebook => 280,
            // Pinterest pin description — image does the work, keep it tight
            self::Pinterest => 200,
            // TikTok caption — the video carries the story
            self::TikTok => 150,
            // YouTube Shorts — fits within the 100-char title (with " #Shorts"
            // suffix taking 8 chars) so the same string works as title + desc
            self::YouTube => 80,
        };
    }

    /**
     * @return array<string>
     */
    public function requiredPublishScopes(): array
    {
        return match ($this) {
            self::Instagram => ['instagram_business_content_publish'],
            self::InstagramFacebook => ['instagram_content_publish'],
            self::Facebook => ['pages_manage_posts'],
            self::TikTok => ['video.publish'],
            self::YouTube => ['https://www.googleapis.com/auth/youtube.upload'],
            self::LinkedIn => ['w_member_social'],
            self::LinkedInPage => ['w_organization_social'],
            self::X => ['tweet.write'],
            self::Threads => ['threads_content_publish'],
            self::Pinterest => ['pins:write'],
            self::Bluesky => [],
            self::Mastodon => ['write:statuses'],
        };
    }

    public function supportsTextOnly(): bool
    {
        return match ($this) {
            self::LinkedIn, self::LinkedInPage => true,
            self::X => true,
            self::TikTok => false,
            self::YouTube => false,
            self::Facebook => true,
            self::Instagram, self::InstagramFacebook => false,
            self::Threads => true,
            self::Pinterest => false,
            self::Bluesky => true,
            self::Mastodon => true,
        };
    }

    public function requiresContent(): bool
    {
        return match ($this) {
            self::YouTube => true,
            default => false,
        };
    }

    public function queue(): string
    {
        return 'social-'.$this->value;
    }

    /**
     * @return array<string>
     */
    public static function allQueues(): array
    {
        return array_map(fn (self $platform) => $platform->queue(), self::cases());
    }

    public function instagramGraphBaseUrl(): string
    {
        return match ($this) {
            self::InstagramFacebook => (string) config('trypost.platforms.instagram-facebook.graph_api'),
            default => (string) config('trypost.platforms.instagram.graph_api'),
        };
    }

    public function isEnabled(): bool
    {
        return config("trypost.platforms.{$this->value}.enabled", true);
    }

    /**
     * Static, platform-specific data exposed to the frontend (e.g. TikTok privacy options,
     * compliance URLs). Returns an empty array for platforms with no extra config.
     *
     * @return array<string, mixed>
     */
    public function publishConfig(): array
    {
        return match ($this) {
            self::TikTok => [
                'privacyLevelOptions' => [
                    'PUBLIC_TO_EVERYONE',
                    'MUTUAL_FOLLOW_FRIENDS',
                    'FOLLOWER_OF_CREATOR',
                    'SELF_ONLY',
                ],
                'musicUsageConfirmationUrl' => 'https://www.tiktok.com/legal/page/global/music-usage-confirmation/en',
                'brandedContentPolicyUrl' => 'https://www.tiktok.com/legal/page/global/bc-policy/en',
            ],
            default => [],
        };
    }

    /**
     * Get all enabled platforms.
     *
     * @return array<self>
     */
    public static function enabled(): array
    {
        return array_filter(self::cases(), fn (self $platform) => $platform->isEnabled());
    }
}
