<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Hashtag;

use App\Actions\Hashtag\CreateHashtag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new hashtag group with a name and hashtag string.')]
class CreateHashtagTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'hashtags' => ['required', 'string'],
        ]);

        $hashtag = CreateHashtag::execute($request->user()->currentWorkspace, $validated);

        return Response::structured($hashtag->toArray());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('The hashtag group name.'),
            'hashtags' => $schema->string()->required()->description('The hashtags string (e.g. "#tech #ai #startup").'),
        ];
    }
}
