<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Enums\Ai\Orientation;
use App\Models\Post;
use App\Models\Workspace;
use App\Services\Ai\VideoGenerationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GenerateVideo implements Tool
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
Generate a short AI video and attach it to the current post.

Call this when the user asks for a video, Reel, TikTok, YouTube Short,
Facebook Reel, or any motion content. Pass a detailed visual description
and an orientation:
- "vertical" (9:16) for TikTok, Instagram Reel, YouTube Shorts, Facebook Reel, Pinterest Video Pin
- "horizontal" (16:9) for X/Twitter video, LinkedIn video

Videos are generated via Veo 3.1 (not yet supported by the SDK's Image/Audio
entry points), which this tool wraps internally.
TXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $prompt = (string) $request['prompt'];
        $orientationString = (string) ($request['orientation'] ?? 'vertical');
        $orientationEnum = Orientation::tryFrom($orientationString) ?? Orientation::Vertical;

        /** @var VideoGenerationService $service */
        $service = app(VideoGenerationService::class);

        $attachment = $service->generate(
            prompt: $prompt,
            workspace: $this->workspace,
            userId: $this->userId,
            postId: $this->post?->id,
            orientation: $orientationEnum,
        );

        $this->collector->push($attachment);

        return "Generated a {$orientationString} video (id: {$attachment['id']}) and attached it to the post.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'prompt' => $schema->string()
                ->description('A detailed visual description of the video to generate. Include subject, motion, style, mood, and any key moments.')
                ->required(),
            'orientation' => $schema->string()
                ->enum(['vertical', 'horizontal'])
                ->description('"vertical" for 9:16 (TikTok, Reels, Shorts, Stories). "horizontal" for 16:9 (X, LinkedIn, Facebook).')
                ->required(),
        ];
    }
}
