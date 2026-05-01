<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Ai;

use App\Actions\Ai\GenerateImage;
use App\Enums\Ai\Orientation;
use App\Exceptions\Ai\QuotaExhaustedException;
use App\Http\Resources\App\MediaResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Generate an AI image and store it in the current workspace media library. Returns the created Media record (id, url, path).')]
class GenerateImageTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $workspace = $request->user()->currentWorkspace;

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'orientation' => ['required', 'string', 'in:square,portrait,vertical,horizontal'],
        ]);

        $orientation = Orientation::tryFrom((string) data_get($validated, 'orientation')) ?? Orientation::Portrait;

        try {
            $media = GenerateImage::execute(
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
                ->description('Detailed visual description: subject, style, composition, mood, and any text to render.')
                ->required(),
            'orientation' => $schema->string()
                ->enum(['square', 'portrait', 'vertical', 'horizontal'])
                ->description('"square" (1:1) | "portrait" (4:5) | "vertical" (9:16) | "horizontal" (16:9)')
                ->required(),
        ];
    }
}
