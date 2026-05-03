<?php

return [
    'title' => 'Billing',

    'upgrade_dialog' => [
        'title' => 'Upgrade your plan',
        'description' => 'Pick a plan that fits your needs.',
        'current_plan' => 'Current plan',
        'current_short' => 'Current',
        'current_badge' => 'Current',
        'subscribe' => 'Subscribe',
        'switch' => 'Switch to this plan',
        'switch_short' => 'Switch',
        'switch_to_yearly' => 'Switch to yearly',
        'switch_to_monthly' => 'Switch to monthly',
        'reasons' => [
            'workspace_limit' => 'You\'ve reached the workspace limit on your current plan. Upgrade to create more workspaces.',
            'social_account_limit' => 'You\'ve reached the social account limit on your current plan. Upgrade to connect more accounts.',
            'member_limit' => 'You\'ve reached the team member limit on your current plan. Upgrade to invite more people.',
        ],
    ],

    'subscribe' => [
        'page_title' => 'Choose your plan',
        'title' => 'Choose the right plan for you',
        'description' => 'Start with a 7-day free trial. No charge until your trial ends.',
        'trial_info' => '7-day free trial, then billed automatically',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
        'per_month' => 'mo',
        'per_year' => 'yr',
        'billed_monthly' => 'billed monthly',
        'billed_yearly' => 'billed yearly',
        'save_months' => '2 months free',
        'popular' => 'Most popular',
        'start_trial' => 'Start 7-day free trial',
        'card_required' => 'Credit card required to start your trial.',
        'cancel_anytime' => 'No charges will be made before the trial period ends.',
        'prices' => [
            'starter' => ['monthly' => '$19', 'yearly' => '$190'],
            'plus' => ['monthly' => '$29', 'yearly' => '$290'],
            'pro' => ['monthly' => '$49', 'yearly' => '$490'],
            'max' => ['monthly' => '$99', 'yearly' => '$990'],
        ],
        'features' => [
            'social_accounts' => ':count social accounts',
            'workspaces' => ':count workspaces',
            'members' => ':count team members',
            'ai_images' => ':count AI images/mo',
        ],
    ],

    'plan' => [
        'title' => 'Plan',
        'description' => 'Manage your subscription plan.',
        'change' => 'Change plan',
        'label' => 'Plan',
        'price' => 'Price',
        'month' => 'month',
        'trial' => 'Trial',
        'active' => 'Active',
        'past_due' => 'Past due',
        'cancelling' => 'Cancelling',
        'trial_ends' => 'Trial ends',
    ],

    'subscription' => [
        'title' => 'Subscription',
        'description' => 'Manage your payment method, billing details, and subscription.',
        'payment_method' => 'Payment method',
        'manage_label' => 'Subscription',
        'manage_stripe' => 'Manage on Stripe',
    ],

    'invoices' => [
        'title' => 'Invoices',
        'description' => 'Download your past invoices.',
        'empty' => 'No invoices found',
        'paid' => 'Paid',
    ],

    'flash' => [
        'plan_changed' => 'You are now on the :plan plan.',
    ],

    'processing' => [
        'page_title' => 'Processing...',
        'title' => 'Processing your subscription',
        'description' => 'Please wait while we set up your account. This will only take a moment.',
        'success_title' => 'You\'re all set!',
        'success_description' => 'Your subscription is active. Redirecting you to your workspaces...',
        'cancelled_title' => 'Checkout cancelled',
        'cancelled_description' => 'Your checkout was cancelled. No charges were made.',
        'retry' => 'Try again',
    ],
];
