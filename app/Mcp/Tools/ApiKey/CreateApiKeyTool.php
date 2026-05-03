<?php

declare(strict_types=1);

namespace App\Mcp\Tools\ApiKey;

use App\Models\AccessToken;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new API key. Returns the plain text token which is only shown once.')]
class CreateApiKeyTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $user = $request->user();
        $workspace = $user->currentWorkspace;

        $result = $user->createToken($validated['name']);

        $token = AccessToken::find($result->token->id);
        $token->forceFill([
            'workspace_id' => $workspace->id,
            'expires_at' => $validated['expires_at'] ?? null,
        ])->saveQuietly();

        return Response::structured([
            'id' => $token->id,
            'name' => $token->name,
            'workspace_id' => $token->workspace_id,
            'expires_at' => $token->expires_at?->toIso8601String(),
            'token' => $result->accessToken,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('The API key name.'),
            'expires_at' => $schema->string()->description('Optional expiration date.'),
        ];
    }
}
