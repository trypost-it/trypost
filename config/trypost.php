<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Self-Hosted Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, the application runs in self-hosted mode which skips
    | payment/subscription requirements during onboarding.
    |
    */

    'self_hosted' => env('SELF_HOSTED', true),

    /*
    |--------------------------------------------------------------------------
    | Media Size Limits
    |--------------------------------------------------------------------------
    |
    | Per-type size caps in megabytes. Single source of truth — direct
    | uploads (StoreAssetRequest, AssetController::storeChunked), URL
    | fetches (MediaAttacher), and the MediaType enum all read from here.
    |
    */

    'media' => [
        'max_size_mb' => [
            'image' => (int) env('MEDIA_IMAGE_MAX_SIZE_MB', 10),
            'video' => (int) env('MEDIA_VIDEO_MAX_SIZE_MB', 1024),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Authentication
    |--------------------------------------------------------------------------
    |
    | Enable or disable "Login with Google" on the login and register pages.
    | Disable this if you don't have Google OAuth credentials configured.
    |
    */

    'google_auth_enabled' => env('GOOGLE_AUTH_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Social Platforms
    |--------------------------------------------------------------------------
    |
    | Configure which social platforms are enabled in the application.
    | Set to false to temporarily disable a platform (e.g., when credentials
    | are revoked, expired, or pending approval).
    |
    */

    'platforms' => [
        'linkedin' => [
            'enabled' => env('LINKEDIN_ENABLED', true),
            'api' => env('LINKEDIN_API', 'https://api.linkedin.com'),
        ],
        'linkedin-page' => [
            'enabled' => env('LINKEDIN_PAGE_ENABLED', true),
            'api' => env('LINKEDIN_PAGE_API', 'https://api.linkedin.com'),
        ],
        'x' => [
            'enabled' => env('X_ENABLED', true),
            'api' => env('X_API', 'https://api.x.com/2'),
        ],
        'tiktok' => [
            'enabled' => env('TIKTOK_ENABLED', true),
            'api' => env('TIKTOK_API', 'https://open.tiktokapis.com/v2'),
        ],
        'youtube' => [
            'enabled' => env('YOUTUBE_ENABLED', true),
            'data_api' => env('YOUTUBE_DATA_API', 'https://www.googleapis.com/youtube/v3'),
            'analytics_api' => env('YOUTUBE_ANALYTICS_API', 'https://youtubeanalytics.googleapis.com/v2'),
        ],
        'facebook' => [
            'enabled' => env('FACEBOOK_ENABLED', true),
            'graph_api' => env('FACEBOOK_GRAPH_API', 'https://graph.facebook.com/v25.0'),
        ],
        'instagram' => [
            'enabled' => env('INSTAGRAM_ENABLED', true),
            'graph_api' => env('INSTAGRAM_GRAPH_API', 'https://graph.instagram.com/v25.0'),
            // graph.instagram.com (no version) is the auth/refresh host.
            'auth_api' => env('INSTAGRAM_AUTH_API', 'https://graph.instagram.com'),
        ],
        'instagram-facebook' => [
            'enabled' => env('INSTAGRAM_FACEBOOK_ENABLED', true),
            'graph_api' => env('INSTAGRAM_FACEBOOK_GRAPH_API', 'https://graph.facebook.com/v25.0'),
        ],
        'threads' => [
            'enabled' => env('THREADS_ENABLED', true),
            'graph_api' => env('THREADS_GRAPH_API', 'https://graph.threads.net/v1.0'),
            // graph.threads.net (no version) is the auth/refresh host.
            'auth_api' => env('THREADS_AUTH_API', 'https://graph.threads.net'),
        ],
        'pinterest' => [
            'enabled' => env('PINTEREST_ENABLED', true),
            'api' => env('PINTEREST_API', 'https://api.pinterest.com/v5'),
        ],
        'bluesky' => [
            'enabled' => env('BLUESKY_ENABLED', true),
            'public_appview' => env('BLUESKY_PUBLIC_APPVIEW', 'https://public.api.bsky.app'),
        ],
        'mastodon' => [
            'enabled' => env('MASTODON_ENABLED', true),
        ],
    ],

];
