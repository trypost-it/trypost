<?php

declare(strict_types=1);

namespace App\Mcp\Tools\ApiKey;

use App\Actions\ApiKey\DeleteApiKey;
use App\Models\ApiToken;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete an API key by ID.')]
class DeleteApiKeyTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $apiToken = ApiToken::where('workspace_id', $request->user()->current_workspace_id)
            ->find(data_get($request->validate(['api_key_id' => ['required', 'string']]), 'api_key_id'));

        if (! $apiToken) {
            return Response::error('API key not found.');
        }

        DeleteApiKey::execute($apiToken);

        return Response::structured(['deleted' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'api_key_id' => $schema->string()->required()->description('The API key ID to delete.'),
        ];
    }
}
