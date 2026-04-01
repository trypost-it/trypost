<?php

declare(strict_types=1);

namespace App\Enums\Notification;

enum Channel: string
{
    case Email = 'email';
    case InApp = 'in_app';
    case Both = 'both';
}
