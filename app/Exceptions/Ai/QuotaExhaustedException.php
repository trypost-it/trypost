<?php

declare(strict_types=1);

namespace App\Exceptions\Ai;

use App\Enums\Ai\UsageType;
use RuntimeException;

class QuotaExhaustedException extends RuntimeException
{
    public function __construct(
        public readonly UsageType $type,
        public readonly int $used,
        public readonly int $limit,
    ) {
        parent::__construct(
            sprintf('%s quota exhausted this month (%d of %d used).', ucfirst($type->value), $used, $limit),
        );
    }
}
