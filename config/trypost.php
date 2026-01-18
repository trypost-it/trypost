<?php

return [

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
            'enabled' => env('TRYPOST_LINKEDIN_ENABLED', true),
        ],
        'linkedin-page' => [
            'enabled' => env('TRYPOST_LINKEDIN_PAGE_ENABLED', true),
        ],
        'x' => [
            'enabled' => env('TRYPOST_X_ENABLED', true),
        ],
        'tiktok' => [
            'enabled' => env('TRYPOST_TIKTOK_ENABLED', true),
        ],
        'youtube' => [
            'enabled' => env('TRYPOST_YOUTUBE_ENABLED', true),
        ],
        'facebook' => [
            'enabled' => env('TRYPOST_FACEBOOK_ENABLED', false),
        ],
        'instagram' => [
            'enabled' => env('TRYPOST_INSTAGRAM_ENABLED', false),
        ],
        'threads' => [
            'enabled' => env('TRYPOST_THREADS_ENABLED', true),
        ],
        'pinterest' => [
            'enabled' => env('TRYPOST_PINTEREST_ENABLED', true),
        ],
    ],

];
