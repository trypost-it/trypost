<?php

return [
    'title' => 'Connections',
    'page_title' => 'Connected Accounts',
    'description' => 'Connect your social networks to schedule and publish posts',

    'not_connected' => 'Not connected',
    'connect' => 'Connect',
    'connection_lost' => 'Connection lost',
    'reconnect_account' => 'Reconnect account',
    'view_profile' => 'View profile',
    'disconnect' => 'Disconnect',

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
        'already_connected' => 'This platform is already connected.',
        'no_youtube_channels' => 'No YouTube channels found. Please create a channel first.',
    ],
];
