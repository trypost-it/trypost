<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Services\PostTemplate\PostTemplateData;
use App\Services\PostTemplate\Registry;
use Illuminate\Support\Collection;

class TemplateContextResolver
{
    public function __construct(private readonly Registry $registry) {}

    /**
     * Pick up to N templates relevant to the platform, randomized for AI variety.
     *
     * @return Collection<int, PostTemplateData>
     */
    public function relevantFor(?string $platform, int $limit = 3): Collection
    {
        return $this->registry
            ->all(Registry::DEFAULT_LOCALE, $platform)
            ->shuffle()
            ->take($limit)
            ->values();
    }
}
