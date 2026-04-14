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
        ],
        'linkedin-page' => [
            'enabled' => env('LINKEDIN_PAGE_ENABLED', true),
        ],
        'x' => [
            'enabled' => env('X_ENABLED', true),
        ],
        'tiktok' => [
            'enabled' => env('TIKTOK_ENABLED', true),
        ],
        'youtube' => [
            'enabled' => env('YOUTUBE_ENABLED', true),
        ],
        'facebook' => [
            'enabled' => env('FACEBOOK_ENABLED', true),
        ],
        'instagram' => [
            'enabled' => env('INSTAGRAM_ENABLED', true),
        ],
        'instagram-facebook' => [
            'enabled' => env('INSTAGRAM_FACEBOOK_ENABLED', true),
        ],
        'threads' => [
            'enabled' => env('THREADS_ENABLED', true),
        ],
        'pinterest' => [
            'enabled' => env('PINTEREST_ENABLED', true),
        ],
        'bluesky' => [
            'enabled' => env('BLUESKY_ENABLED', true),
        ],
        'mastodon' => [
            'enabled' => env('MASTODON_ENABLED', true),
        ],
    ],

];
