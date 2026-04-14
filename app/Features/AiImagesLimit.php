<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\Workspace;

class AiImagesLimit
{
    public function resolve(Workspace $scope): int
    {
        return $scope->plan?->ai_images_limit ?? 50;
    }
}
