<?php

declare(strict_types=1);

namespace App\Actions\ApiKey;

use App\Models\ApiToken;

class DeleteApiKey
{
    public static function execute(ApiToken $apiToken): void
    {
        $apiToken->delete();
    }
}
