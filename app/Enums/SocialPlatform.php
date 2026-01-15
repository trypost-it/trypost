<?php

namespace App\Enums;

enum SocialPlatform: string
{
    case LinkedIn = 'linkedin';
    case LinkedInPage = 'linkedin-page';
    case X = 'x';
    case TikTok = 'tiktok';

    public function label(): string
    {
        return match ($this) {
            self::LinkedIn => 'LinkedIn',
            self::LinkedInPage => 'LinkedIn Page',
            self::X => 'X',
            self::TikTok => 'TikTok',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LinkedIn, self::LinkedInPage => '#0A66C2',
            self::X => '#000000',
            self::TikTok => '#000000',
        };
    }

    public function allowedMediaTypes(): array
    {
        return match ($this) {
            self::LinkedIn, self::LinkedInPage => [MediaType::Image, MediaType::Video, MediaType::Document],
            self::X => [MediaType::Image, MediaType::Video],
            self::TikTok => [MediaType::Video],
        };
    }

    public function maxImages(): int
    {
        return match ($this) {
            self::LinkedIn, self::LinkedInPage => 1,
            self::X => 4,
            self::TikTok => 0,
        };
    }

    public function maxContentLength(): int
    {
        return match ($this) {
            self::LinkedIn, self::LinkedInPage => 3000,
            self::X => 280,
            self::TikTok => 2200,
        };
    }

    public function supportsTextOnly(): bool
    {
        return match ($this) {
            self::LinkedIn, self::LinkedInPage => true,
            self::X => true,
            self::TikTok => false,
        };
    }
}
