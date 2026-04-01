<?php

declare(strict_types=1);

namespace App\Mcp\Tools\ApiKey;

use App\Actions\ApiKey\CreateApiKey;
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

        $result = CreateApiKey::execute($request->user()->currentWorkspace, $validated);

        return Response::structured([
            ...data_get($result, 'token')->toArray(),
            'token' => data_get($result, 'plain_token'),
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
