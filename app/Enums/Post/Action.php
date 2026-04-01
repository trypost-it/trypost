<?php

declare(strict_types=1);

namespace App\Enums\Post;

enum Action: string
{
    case AlreadyPublished = 'already_published';
    case Publishing = 'publishing';
    case Scheduled = 'scheduled';
}
