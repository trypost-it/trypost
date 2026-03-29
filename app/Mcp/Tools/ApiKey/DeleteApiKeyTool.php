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
    public function handle(Request $request): ResponseFactory
    {
        $apiToken = ApiToken::where('workspace_id', $request->user()->current_workspace_id)
            ->findOrFail(data_get($request->validated(), 'api_key_id'));

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
