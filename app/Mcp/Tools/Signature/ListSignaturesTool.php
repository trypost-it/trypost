<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Signature;

use App\Http\Resources\Api\SignatureResource;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List all signatures for the current workspace. Signatures are reusable text blocks (hashtags, links, custom text) that can be appended to posts.')]
class ListSignaturesTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $signatures = $request->user()->currentWorkspace
            ->signatures()
            ->latest()
            ->get();

        return Response::structured([
            'signatures' => SignatureResource::collection($signatures)->resolve(),
        ]);
    }
}
