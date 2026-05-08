<?php

declare(strict_types=1);

namespace App\Enums\Ai;

enum UsageType: string
{
    case Template = 'template';
    case Text = 'text';
    case Image = 'image';
}
