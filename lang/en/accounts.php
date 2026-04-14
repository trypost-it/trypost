<?php

return [
    'title' => 'Connections',
    'page_title' => 'Social Accounts',
    'description' => 'Overview of all your connected social accounts',
    'add_social' => 'Add Social',
    'add_social_title' => 'Connect a Social Account',
    'add_social_description' => 'Choose a platform to connect',
    'no_accounts' => 'No accounts connected yet',
    'no_accounts_description' => 'Connect your social networks to start scheduling and publishing posts',
    'added' => 'Added :date',

    'limit_reached' => 'You have reached your plan limit for social accounts.',

    'not_connected' => 'Not connected',
    'connect' => 'Connect',
    'connection_lost' => 'Connection lost',
    'reconnect_account' => 'Reconnect account',
    'view_profile' => 'View profile',
    'disconnect' => 'Disconnect',

    'tooltips' => [
        'instagram_facebook' => 'Connects via your Facebook Page. Recommended for business accounts linked to a Facebook Page.',
        'instagram_direct' => 'Connects directly through Instagram. For professional/creator accounts without a Facebook Page.',
        'bluesky' => "We don't currently support two-factor authentication. If it's enabled on Bluesky, you'll need to disable it.",
    ],

    'disconnect_modal' => [
        'title' => 'Disconnect Account',
        'description' => 'Are you sure you want to disconnect this account? You can reconnect it at any time.',
        'confirm' => 'Disconnect',
        'cancel' => 'Cancel',
    ],

    'bluesky' => [
        'title' => 'Connect Bluesky',
        'description' => 'Enter your credentials to connect',
        'email' => 'Email',
        'email_placeholder' => 'yourhandle.bsky.social',
        'app_password' => 'App Password',
        'app_password_placeholder' => 'xxxx-xxxx-xxxx-xxxx',
        'app_password_hint' => 'Use an <strong>App Password</strong> for security. Create one at <a href="https://bsky.app/settings/app-passwords" target="_blank" class="underline">bsky.app/settings</a>.',
        'submit' => 'Connect Bluesky',
        'submitting' => 'Connecting...',
    ],

    'mastodon' => [
        'title' => 'Connect Mastodon',
        'description' => 'Enter your Mastodon instance',
        'instance_url' => 'Instance URL',
        'instance_placeholder' => 'https://mastodon.social',
        'instance_hint' => 'Enter your Mastodon instance URL (e.g., mastodon.social, techhub.social)',
        'submit' => 'Continue with Mastodon',
        'submitting' => 'Connecting...',
    ],

    'facebook' => [
        'title' => 'Select Facebook Page',
        'description' => 'Choose which page you want to connect',
        'no_pages' => 'No pages found',
        'no_pages_description' => 'You are not an admin of any Facebook page.',
        'page_label' => 'Facebook Page',
    ],

    'instagram_facebook' => [
        'title' => 'Select Instagram Account',
        'description' => 'Choose which Instagram account you want to connect',
        'no_pages' => 'No Instagram accounts found',
        'no_pages_description' => 'No Facebook Pages with linked Instagram Business accounts were found.',
    ],

    'linkedin' => [
        'title' => 'Select LinkedIn Page',
        'description' => 'Choose which page you want to connect',
        'no_pages' => 'No pages found',
        'no_pages_description' => 'You are not an administrator of any LinkedIn page.',
        'page_label' => 'LinkedIn Page',
    ],

    'flash' => [
        'disconnected' => 'Account disconnected successfully!',
        'connected' => 'Account connected successfully!',
        'session_expired' => 'Session expired. Please try again.',
        'workspace_not_found' => 'Workspace not found.',
        'activated' => 'Account activated!',
        'deactivated' => 'Account deactivated!',
        'already_connected' => 'This platform is already connected.',
        'no_youtube_channels' => 'No YouTube channels found. Please create a channel first.',
    ],
];
