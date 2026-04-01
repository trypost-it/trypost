<?php

declare(strict_types=1);

namespace App\Exceptions\Social;

enum ErrorCategory: string
{
    case MediaFormat = 'media_format';
    case RateLimit = 'rate_limit';
    case Permission = 'permission';
    case ContentPolicy = 'content_policy';
    case ServerError = 'server_error';
    case Unknown = 'unknown';
}
