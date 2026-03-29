<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Hashtag;

use App\Actions\Hashtag\DeleteHashtag;
use App\Models\WorkspaceHashtag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a hashtag group by ID.')]
class DeleteHashtagTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $hashtag = WorkspaceHashtag::where('workspace_id', $request->user()->current_workspace_id)
            ->findOrFail(data_get($request->validated(), 'hashtag_id'));

        DeleteHashtag::execute($hashtag);

        return Response::structured(['deleted' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'hashtag_id' => $schema->string()->required()->description('The hashtag group ID to delete.'),
        ];
    }
}
