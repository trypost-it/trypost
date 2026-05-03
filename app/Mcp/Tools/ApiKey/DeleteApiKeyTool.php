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

#[Description('Revoke an API key by ID.')]
class DeleteApiKeyTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate(['api_key_id' => ['required', 'string']]);

        $token = AccessToken::where('user_id', $request->user()->id)
            ->where('workspace_id', $request->user()->currentWorkspace->id)
            ->where('revoked', false)
            ->find($validated['api_key_id']);

        if (! $token) {
            return Response::error('API key not found.');
        }

        $token->forceFill(['revoked' => true])->saveQuietly();

        return Response::structured(['deleted' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'api_key_id' => $schema->string()->required()->description('The API key ID to delete.'),
        ];
    }
}
