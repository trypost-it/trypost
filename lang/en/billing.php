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
        'unavailable' => 'Unavailable',
        'reasons' => [
            'workspace_limit' => 'You\'ve reached the workspace limit on your current plan. Upgrade to create more workspaces.',
            'social_account_limit' => 'You\'ve reached the social account limit on your current plan. Upgrade to connect more accounts.',
            'member_limit' => 'You\'ve reached the team member limit on your current plan. Upgrade to invite more people.',
        ],
    ],

    'subscribe' => [
        'page_title' => 'Choose your plan',
        'eyebrow' => 'Pricing',
        'title' => 'Choose the right plan for you',
        'description' => 'Pick the plan that fits you. Billed monthly or annually.',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
        'per_month' => 'monthly',
        'per_year' => 'yearly',
        'billed_monthly' => 'Billed monthly',
        'billed_yearly' => 'Billed annually',
        'features_included' => "What's included:",
        'everything_in' => 'Everything in :plan, plus:',
        'save_months' => '2 months free',
        'popular' => 'Most popular',
        'subscribe_cta' => 'Subscribe',
        'prices' => [
            'starter' => ['monthly' => '$19', 'yearly_per_month' => '$16', 'yearly' => '$190'],
            'plus' => ['monthly' => '$29', 'yearly_per_month' => '$24', 'yearly' => '$290'],
            'pro' => ['monthly' => '$49', 'yearly_per_month' => '$41', 'yearly' => '$490'],
            'max' => ['monthly' => '$99', 'yearly_per_month' => '$83', 'yearly' => '$990'],
        ],
        'features' => [
            'social_accounts' => ':count social accounts',
            'workspaces' => ':count workspaces',
            'members' => ':count team members',
            'credits' => ':count AI credits/mo',
        ],
        'credit_tooltips' => [
            'starter' => 'Roughly 150 medium-length posts plus 5 AI images per month.',
            'plus' => 'Roughly 300 medium-length posts plus 10 AI images per month.',
            'pro' => 'Roughly 700 medium-length posts plus 30 AI images per month.',
            'max' => 'Roughly 2,000 medium-length posts plus 100 AI images per month.',
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
        'no_payment_method' => 'No payment method on file yet.',
        'expires_on' => 'Expires :month/:year',
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
        'cannot_manage' => 'Only the account owner can manage billing.',
        'cannot_downgrade' => [
            'workspaces' => 'Cannot switch to :plan: you have :count workspaces but the plan only allows :limit.',
            'social_accounts' => 'Cannot switch to :plan: you have :count social accounts but the plan only allows :limit.',
            'members' => 'Cannot switch to :plan: you have :count team members (including invites) but the plan only allows :limit.',
        ],
        'credits_exhausted' => 'Out of AI credits — your monthly :limit allowance has been used. Upgrade your plan or wait until next month.',
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
