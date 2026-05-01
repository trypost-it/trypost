<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Ai\GenerateImage as GenerateImageAction;
use App\Enums\Ai\Orientation;
use App\Enums\Media\Type as MediaType;
use App\Exceptions\Ai\QuotaExhaustedException;
use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GenerateImage implements Tool
{
    public function __construct(
        public Workspace $workspace,
        public ?Post $post = null,
        public ?string $userId = null,
        public ?AttachmentCollector $collector = null,
    ) {
        $this->collector ??= app(AttachmentCollector::class);
    }

    public function description(): Stringable|string
    {
        return <<<'TXT'
Generate an AI image and attach it to the current post.

Call this when the user asks for an image, photo, carousel slide, or any visual content.
Pass a detailed visual prompt describing what to generate, and an orientation:
- "square" (1:1) for LinkedIn, Facebook, or when user wants square
- "portrait" (4:5) for Instagram Feed, Threads
- "vertical" (9:16) for Instagram Reel/Story, Pinterest Pin, TikTok
- "horizontal" (16:9) for X/Twitter, YouTube thumbnail

Choose the orientation that best matches the target platform.

The image is generated, stored, registered in the workspace's media library,
logged in monthly usage tracking, and attached to the assistant's response message.
TXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $prompt = (string) data_get($request, 'prompt', '');
        $orientationString = (string) data_get($request, 'orientation', 'vertical');
        $orientation = Orientation::tryFrom($orientationString) ?? Orientation::Portrait;

        try {
            $media = GenerateImageAction::execute(
                workspace: $this->workspace,
                prompt: $prompt,
                orientation: $orientation,
                userId: $this->userId,
                postId: $this->post?->id,
            );
        } catch (QuotaExhaustedException $e) {
            return "Image quota exhausted this month ({$e->used} of {$e->limit} used). Ask the user to upgrade their plan or wait until next month.";
        }

        $this->collector->push([
            'id' => $media->id,
            'path' => $media->path,
            'url' => $media->url,
            'mime_type' => 'image/png',
            'type' => MediaType::Image->value,
        ]);

        return "Generated a {$orientationString} image (id: {$media->id}) and attached it to the post.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'prompt' => $schema->string()
                ->description('A detailed visual description of the image to generate. Include subject, style, composition, mood, and any text that should appear in the image.')
                ->required(),
            'orientation' => $schema->string()
                ->enum(['square', 'portrait', 'vertical', 'horizontal'])
                ->description('"square" (1:1) for LinkedIn, Facebook. "portrait" (4:5) for Instagram Feed, Threads. "vertical" (9:16) for Instagram Reel/Story, Pinterest, TikTok. "horizontal" (16:9) for X/Twitter.')
                ->required(),
        ];
    }
}
