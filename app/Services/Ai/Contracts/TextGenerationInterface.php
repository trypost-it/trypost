<?php

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use App\Models\Workspace;

interface TextGenerationInterface
{
    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function generate(string $prompt, array $history = [], ?Workspace $workspace = null, ?string $imageUrl = null): string;
}
