<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Enums\Ai\Orientation;
use App\Enums\Ai\UsageType;
use App\Features\AiImagesLimit;
use App\Models\AiUsageLog;
use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Image;
use Laravel\Ai\Tools\Request;
use Laravel\Pennant\Feature;
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
- "vertical" (9:16) for Instagram Reel/Story, Pinterest, TikTok, YouTube Shorts
- "horizontal" (16:9) for X/Twitter, LinkedIn, Facebook

The image is generated, stored on the public disk, registered in the workspace's
media library, logged in monthly usage tracking, and attached to the assistant's
response message.
TXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $limit = (int) Feature::for($this->workspace->account)->value(AiImagesLimit::class);
        $used = AiUsageLog::monthlyCount($this->workspace->account_id, UsageType::Image);

        if ($used >= $limit) {
            return "Image quota exhausted this month ({$used} of {$limit} used). Ask the user to upgrade their plan or wait until next month.";
        }

        $prompt = (string) data_get($request, 'prompt', '');
        $orientationString = (string) data_get($request, 'orientation', 'vertical');
        $orientationEnum = Orientation::tryFrom($orientationString) ?? Orientation::Vertical;
        $aspectRatio = $orientationEnum->aspectRatio();

        $renderedPrompt = view('prompts.assistant.image', [
            'prompt' => $prompt,
            'brand_name' => $this->workspace->name ?? '',
            'tone' => $this->workspace->brand_tone ?? 'professional',
            'aspect_ratio' => $aspectRatio,
            'content_language' => $this->workspace->content_language ?? 'en',
        ])->render();

        $response = Image::of($renderedPrompt)
            ->size($aspectRatio)
            ->quality('high')
            ->generate();

        $storedPath = $response->store('medias', 'public');

        $media = $this->workspace->media()->create([
            'group_id' => Str::uuid()->toString(),
            'collection' => 'assets',
            'type' => 'image',
            'path' => $storedPath,
            'original_filename' => 'ai-generated.png',
            'mime_type' => 'image/png',
            'size' => Storage::disk('public')->size($storedPath),
            'order' => 0,
            'meta' => ['ai_generated' => true, 'prompt' => Str::limit($prompt, 200)],
        ]);

        AiUsageLog::create([
            'account_id' => $this->workspace->account_id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->userId,
            'post_id' => $this->post?->id,
            'type' => UsageType::Image,
            'provider' => 'gemini',
        ]);

        $this->collector->push([
            'id' => $media->id,
            'path' => $media->path,
            'url' => $media->url,
            'mime_type' => 'image/png',
            'type' => 'image',
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
                ->enum(['vertical', 'horizontal'])
                ->description('"vertical" for 9:16 (Instagram Reel/Story, TikTok, YouTube Shorts, Pinterest). "horizontal" for 16:9 (X/Twitter, LinkedIn, Facebook).')
                ->required(),
        ];
    }
}
