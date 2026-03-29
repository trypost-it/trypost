<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Label;

use App\Actions\Label\DeleteLabel;
use App\Models\WorkspaceLabel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a label by ID.')]
class DeleteLabelTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $label = WorkspaceLabel::where('workspace_id', $request->user()->current_workspace_id)
            ->findOrFail(data_get($request->validated(), 'label_id'));

        DeleteLabel::execute($label);

        return Response::structured(['deleted' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'label_id' => $schema->string()->required()->description('The label ID to delete.'),
        ];
    }
}
