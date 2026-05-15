<?php

declare(strict_types=1);

namespace App\Enums\Post;

enum Action: string
{
    case AlreadyPublished = 'already_published';
    case Finalized = 'finalized';
    case Publishing = 'publishing';
    case Scheduled = 'scheduled';
}
