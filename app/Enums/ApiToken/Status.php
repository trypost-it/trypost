<?php

declare(strict_types=1);

namespace App\Enums\ApiToken;

enum Status: string
{
    case Active = 'active';
    case Expired = 'expired';
}
