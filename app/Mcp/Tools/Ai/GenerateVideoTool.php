<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Ai;

use App\Actions\Ai\GenerateVideo;
use App\Enums\Ai\Orientation;
use App\Exceptions\Ai\QuotaExhaustedException;
use App\Http\Resources\App\MediaResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Generate a short AI video (Veo 3.1) and store it in the current workspace media library. Returns the created Media record.')]
class GenerateVideoTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $workspace = $request->user()->currentWorkspace;

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'orientation' => ['required', 'string', 'in:vertical,horizontal'],
        ]);

        $orientation = Orientation::tryFrom((string) data_get($validated, 'orientation')) ?? Orientation::Vertical;

        try {
            $media = GenerateVideo::execute(
                workspace: $workspace,
                prompt: (string) data_get($validated, 'prompt'),
                orientation: $orientation,
                userId: $request->user()->id,
            );
        } catch (QuotaExhaustedException $e) {
            return Response::error($e->getMessage());
        }

        return Response::structured([
            ...(new MediaResource($media))->resolve(),
            'orientation' => $orientation->value,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'prompt' => $schema->string()
                ->description('Detailed visual description of the video: subject, motion, style, mood, key moments.')
                ->required(),
            'orientation' => $schema->string()
                ->enum(['vertical', 'horizontal'])
                ->description('"vertical" (9:16) for TikTok, Reels, Shorts, Stories. "horizontal" (16:9) for X, LinkedIn, Facebook.')
                ->required(),
        ];
    }
}
