<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Hashtag;

use App\Actions\Hashtag\UpdateHashtag;
use App\Models\WorkspaceHashtag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update a hashtag group name or hashtags.')]
class UpdateHashtagTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'hashtag_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'hashtags' => ['required', 'string'],
        ]);

        $hashtag = WorkspaceHashtag::where('workspace_id', $request->user()->current_workspace_id)
            ->findOrFail(data_get($validated, 'hashtag_id'));

        $hashtag = UpdateHashtag::execute($hashtag, $validated);

        return Response::structured($hashtag->toArray());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'hashtag_id' => $schema->string()->required()->description('The hashtag group ID.'),
            'name' => $schema->string()->required()->description('The new name.'),
            'hashtags' => $schema->string()->required()->description('The new hashtags string.'),
        ];
    }
}
