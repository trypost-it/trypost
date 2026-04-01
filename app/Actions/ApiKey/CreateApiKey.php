<?php

declare(strict_types=1);

namespace App\Actions\ApiKey;

use App\Models\ApiToken;
use App\Models\Workspace;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateApiKey
{
    /**
     * @param  array{name: string, expires_at?: string|null}  $data
     * @return array{token: ApiToken, plain_token: string}
     */
    public static function execute(Workspace $workspace, array $data): array
    {
        $plainToken = 'tp_'.Str::random(48);

        $apiToken = ApiToken::create([
            'workspace_id' => $workspace->id,
            'name' => data_get($data, 'name'),
            'token_lookup' => substr($plainToken, 3, 16),
            'token_hash' => Hash::make($plainToken),
            'expires_at' => data_get($data, 'expires_at')
                ? now()->parse(data_get($data, 'expires_at'))->endOfDay()
                : null,
        ]);

        return ['token' => $apiToken, 'plain_token' => $plainToken];
    }
}
