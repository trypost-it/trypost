<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Signature;

use App\Actions\Signature\DeleteSignature;
use App\Models\WorkspaceSignature;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a signature by ID.')]
class DeleteSignatureTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $signature = WorkspaceSignature::where('workspace_id', $request->user()->current_workspace_id)
            ->find(data_get($request->validate(['signature_id' => ['required', 'string']]), 'signature_id'));

        if (! $signature) {
            return Response::error('Signature not found.');
        }

        DeleteSignature::execute($signature);

        return Response::structured(['deleted' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'signature_id' => $schema->string()->required()->description('The signature ID to delete.'),
        ];
    }
}
