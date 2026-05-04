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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete a label permanently. The label is detached from all posts that referenced it. This cannot be undone.')]
class DeleteLabelTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate(['label_id' => ['required', 'string']]);

        $label = WorkspaceLabel::where('workspace_id', $request->user()->current_workspace_id)
            ->find(data_get($validated, 'label_id'));

        if (! $label) {
            return Response::error('Label not found.');
        }

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
