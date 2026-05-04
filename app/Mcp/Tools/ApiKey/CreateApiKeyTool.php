<?php

declare(strict_types=1);

namespace App\Mcp\Tools\ApiKey;

use App\Http\Resources\Api\ApiKeyResource;
use App\Models\AccessToken;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new Personal Access Token (API key) for the current workspace. The plain token value is returned ONCE — store it immediately, it cannot be retrieved later.')]
class CreateApiKeyTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $user = $request->user();

        $result = $user->createToken(data_get($validated, 'name'));

        $token = AccessToken::find($result->token->id);
        $token->forceFill([
            'workspace_id' => $user->current_workspace_id,
            'expires_at' => data_get($validated, 'expires_at'),
        ])->saveQuietly();

        return Response::structured(array_merge(
            (new ApiKeyResource($token))->resolve(),
            ['token' => $result->accessToken],
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('A human-readable name to identify the key (e.g. "My integration").'),
            'expires_at' => $schema->string()->description('Optional ISO 8601 expiration date (e.g. 2026-12-31). Must be in the future.'),
        ];
    }
}
