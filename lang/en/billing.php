<?php

return [
    'title' => 'Subscription',
    'description' => 'Manage your subscription and payment method',

    'subscribe' => [
        'page_title' => 'Start your free trial',
        'title' => 'Start your free trial',
        'description' => ':days days free. No credit card required to start.',
        'start_trial' => 'Start :days-day free trial',
        'cancel_anytime' => 'Cancel anytime. No questions asked.',
        'switch_workspace' => 'Switch workspace',
        'features' => [
            'calendar' => 'Visual drag-and-drop calendar',
            'scheduling' => 'Unlimited post scheduling',
            'media' => 'Images, carousels & stories',
            'video' => 'Video publishing across platforms',
            'team' => 'Team collaboration & workspaces',
            'hashtags' => 'Hashtag groups & labels',
        ],
    ],

    'trial' => [
        'title' => 'Trial period active',
        'description' => 'Your trial ends on :date. After that, your subscription will be charged automatically.',
    ],

    'subscription' => [
        'title' => 'Your Subscription',
        'workspaces' => 'Workspaces',
        'quantity' => 'Subscription quantity',
        'expires' => 'Expires :date',
        'canceled_on' => 'Your subscription will be canceled on :date',
        'manage' => 'Manage on Stripe',
    ],

    'invoices' => [
        'title' => 'Invoices',
        'description' => 'Payment history',
        'empty' => 'No invoices found',
        'paid' => 'Paid',
    ],

    'status' => [
        'active' => 'Active',
        'canceled' => 'Canceled',
        'incomplete' => 'Incomplete',
        'incomplete_expired' => 'Expired',
        'past_due' => 'Past due',
        'trialing' => 'Trial',
        'unpaid' => 'Unpaid',
    ],
];
