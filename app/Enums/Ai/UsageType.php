<?php

declare(strict_types=1);

namespace App\Enums\Ai;

enum UsageType: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
}
