<?php

declare(strict_types=1);

namespace App\Enums\AiMessage;

enum Status: string
{
    case Pending = 'pending';
    case Generating = 'generating';
    case Completed = 'completed';
    case Failed = 'failed';
}
