<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\Account;

class AiImagesLimit
{
    public string $name = 'ai-images-limit';

    public function resolve(Account $scope): int
    {
        return $scope->plan?->ai_images_limit ?? 50;
    }
}
