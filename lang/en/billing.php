<?php

return [
    'title' => 'Subscription',
    'description' => 'Manage your subscription and payment method',

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
