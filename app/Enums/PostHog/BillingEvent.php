<?php

declare(strict_types=1);

namespace App\Enums\PostHog;

enum BillingEvent: string
{
    case Created = 'subscription.created';
    case Updated = 'subscription.updated';
    case Cancelled = 'subscription.cancelled';
}
