<?php

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
            self::Instagram => 'Instagram',
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
            self::Threads => '#000000',
            self::Pinterest => '#E60023',
            self::Bluesky => '#0085FF',
            self::Mastodon => '#6364FF',
        };
    }

    public function allowedMediaTypes(): array
    {
        return match ($this) {
            self::LinkedIn, self::LinkedInPage => [MediaType::Image, MediaType::Video, MediaType::Document],
            self::X => [MediaType::Image, MediaType::Video],
            self::TikTok => [MediaType::Video],
            self::YouTube => [MediaType::Video],
            self::Facebook => [MediaType::Image, MediaType::Video],
            self::Instagram => [MediaType::Image, MediaType::Video],
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
            self::Instagram => 10,
            self::Threads => 10,
            self::Pinterest => 5,
            self::Bluesky => 4,
            self::Mastodon => 4,
        };
    }

    public function maxContentLength(): int
    {
        return match ($this) {
            self::LinkedIn, self::LinkedInPage => 3000,
            self::X => 280,
            self::TikTok => 2200,
            self::YouTube => 5000,
            self::Facebook => 63206,
            self::Instagram => 2200,
            self::Threads => 500,
            self::Pinterest => 800,
            self::Bluesky => 300,
            self::Mastodon => 500,
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
            self::Instagram => false,
            self::Threads => true,
            self::Pinterest => false,
            self::Bluesky => true,
            self::Mastodon => true,
        };
    }

    public function isEnabled(): bool
    {
        return config("trypost.platforms.{$this->value}.enabled", true);
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
