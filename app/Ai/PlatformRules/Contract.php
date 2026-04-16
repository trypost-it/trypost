<?php

declare(strict_types=1);

namespace App\Ai\PlatformRules;

use App\Enums\SocialAccount\Platform;

interface Contract
{
    public function platform(): Platform;

    /**
     * @return array<string, mixed>
     */
    public function specs(): array;

    public function summary(): string;
}
