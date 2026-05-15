<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Post;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Issue a one-shot signed POST URL that lets the user upload a local file (image up to 10 MB, video up to 50 MB hard cap) directly to this workspace. Returns an upload_token and upload_url. Hand the URL to the user (e.g. as a curl command with `-F media=@path/to/file`) or to the MCP client. After upload, call AttachMediaFromUploadTool(post_id, upload_token) to attach the result to a post.')]
class RequestMediaUploadTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        $workspaceId = $user->current_workspace_id;

        $token = (string) Str::uuid();
        $expiresAt = CarbonImmutable::now()->addMinutes(15);

        $uploadUrl = URL::temporarySignedRoute(
            'api.uploads.store',
            $expiresAt,
            ['token' => $token, 'ws' => $workspaceId],
        );

        return Response::structured([
            'upload_token' => $token,
            'upload_url' => $uploadUrl,
            'expires_at' => $expiresAt->toIso8601String(),
            'max_bytes' => 52428800,
            'field_name' => 'media',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
