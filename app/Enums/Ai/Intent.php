<?php

declare(strict_types=1);

namespace App\Enums\Ai;

enum Intent: string
{
    case Text = 'text';
    case Image = 'image';
    case Audio = 'audio';
    case Video = 'video';
    case Blocked = 'blocked';
}
