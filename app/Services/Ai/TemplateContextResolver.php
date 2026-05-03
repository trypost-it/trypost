<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\PostTemplate;
use Illuminate\Database\Eloquent\Collection;

class TemplateContextResolver
{
    /**
     * Pick up to N templates relevant to the platform.
     *
     * @return Collection<int, PostTemplate>
     */
    public function relevantFor(?string $platform, int $limit = 3): Collection
    {
        return PostTemplate::query()
            ->when($platform, fn ($q, $p) => $q->where('platform', $p))
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }
}
