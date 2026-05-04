<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Signature;

use App\Actions\Signature\CreateSignature;
use App\Http\Resources\Api\SignatureResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new signature with a name and content (hashtags, links, custom text, etc.).')]
class CreateSignatureTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        $signature = CreateSignature::execute($request->user()->currentWorkspace, $validated);

        return Response::structured((new SignatureResource($signature))->resolve());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('The signature name.'),
            'content' => $schema->string()->required()->description('The signature content (hashtags, links, custom text — anything you want to append to posts).'),
        ];
    }
}
