<?php

declare(strict_types=1);

namespace App\Jobs\Ai;

use App\Ai\Agents\PostContentGenerator;
use App\Ai\Agents\PostContentHumanizer;
use App\Enums\Ai\UsageType;
use App\Enums\PostPlatform\ContentType;
use App\Events\Ai\PostCreationReady;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Ai\RecordAiUsage;
use App\Services\Image\BrandColorMapper;
use App\Services\Image\TemplateImageGenerator;
use App\Services\Unsplash\UnsplashClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StreamPostCreation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $userId,
        public string $creationId,
        public string $workspaceId,
        public string $format,
        public ?string $socialAccountId,
        public int $imageCount,
        public string $prompt,
    ) {
        $this->onQueue('ai');
    }

    public function handle(): void
    {
        $workspace = Workspace::findOrFail($this->workspaceId);
        $socialAccount = $this->socialAccountId ? SocialAccount::find($this->socialAccountId) : null;

        $isCarousel = $this->format === 'instagram_carousel';
        $agentFormat = $isCarousel ? 'carousel' : 'single';
        $slideCount = $isCarousel && $this->imageCount > 0 ? $this->imageCount : 1;

        $agent = new PostContentGenerator(
            workspace: $workspace,
            format: $agentFormat,
            slideCount: $slideCount,
            platformContext: $this->format,
        );

        try {
            $response = $agent->prompt($this->prompt);

            RecordAiUsage::record(
                workspace: $workspace,
                type: UsageType::Text,
                provider: (string) config('ai.default'),
                userId: $this->userId,
                metadata: ['agent' => 'post_generator', 'format' => $this->format],
            );

            // StructuredAgentResponse implements ArrayAccess: access via $response['key']
            $structured = $response->structured ?? [];

            // Second pass: rewrite human-readable text to remove AI-tells. Image
            // keywords pass through untouched (they need to stay in English).
            $structured = $this->humanize($workspace, $structured, $isCarousel ? 'carousel' : 'single');

            if ($isCarousel) {
                $this->handleCarousel($workspace, $socialAccount, $structured);
            } else {
                $this->handleSingle($workspace, $socialAccount, $structured);
            }
        } catch (\Throwable $e) {
            Log::error('StreamPostCreation failed', [
                'creation_id' => $this->creationId,
                'error' => $e->getMessage(),
            ]);

            PostCreationReady::dispatch($this->userId, $this->creationId, null, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Run the structured generator output through the humanizer pass and merge
     * the humanized text fields back over the original structure (preserving
     * image_keywords and slide order/count). Failures are logged and the
     * original structure is returned so generation never breaks because of the
     * polish step.
     *
     * @param  array<string, mixed>  $structured
     * @return array<string, mixed>
     */
    /**
     * Look up the AI image dimensions for the current format. Falls back to
     * the generator's defaults (4:5 portrait) if the format string isn't a
     * known ContentType case.
     *
     * @return array{width: int, height: int}
     */
    private function dimensionsForFormat(): array
    {
        $type = ContentType::tryFrom($this->format);

        return $type
            ? $type->aiImageDimensions()
            : ['width' => TemplateImageGenerator::DEFAULT_WIDTH, 'height' => TemplateImageGenerator::DEFAULT_HEIGHT];
    }

    private function humanize(Workspace $workspace, array $structured, string $format): array
    {
        try {
            $input = $format === 'carousel'
                ? [
                    'caption' => data_get($structured, 'caption', ''),
                    'slides' => array_map(
                        fn ($s) => [
                            'title' => data_get($s, 'title', ''),
                            'body' => data_get($s, 'body', ''),
                        ],
                        data_get($structured, 'slides', []),
                    ),
                ]
                : [
                    'content' => data_get($structured, 'content', ''),
                    'image_title' => data_get($structured, 'image_title', ''),
                    'image_body' => data_get($structured, 'image_body', ''),
                ];

            $humanizer = new PostContentHumanizer($workspace, $format);
            $response = $humanizer->prompt(json_encode($input, JSON_UNESCAPED_UNICODE));
            $humanized = $response->structured ?? [];

            RecordAiUsage::record(
                workspace: $workspace,
                type: UsageType::Text,
                provider: (string) config('ai.default'),
                userId: $this->userId,
                metadata: ['agent' => 'post_humanizer', 'format' => $format],
            );
        } catch (\Throwable $e) {
            Log::warning('PostContentHumanizer failed, using generator output as-is', [
                'creation_id' => $this->creationId,
                'error' => $e->getMessage(),
            ]);

            return $structured;
        }

        if ($format === 'carousel') {
            $structured['caption'] = data_get($humanized, 'caption', $structured['caption'] ?? '');
            $originalSlides = $structured['slides'] ?? [];
            $humanizedSlides = data_get($humanized, 'slides', []);

            foreach ($originalSlides as $i => $slide) {
                if (isset($humanizedSlides[$i])) {
                    $originalSlides[$i]['title'] = data_get($humanizedSlides[$i], 'title', $slide['title'] ?? '');
                    $originalSlides[$i]['body'] = data_get($humanizedSlides[$i], 'body', $slide['body'] ?? '');
                }
            }

            $structured['slides'] = $originalSlides;
        } else {
            $structured['content'] = data_get($humanized, 'content', $structured['content'] ?? '');
            $structured['image_title'] = data_get($humanized, 'image_title', $structured['image_title'] ?? '');
            $structured['image_body'] = data_get($humanized, 'image_body', $structured['image_body'] ?? '');
        }

        return $structured;
    }

    /**
     * @param  array<string, mixed>  $structured
     */
    private function handleCarousel(Workspace $workspace, ?SocialAccount $socialAccount, array $structured): void
    {
        $caption = data_get($structured, 'caption', '');
        $slides = data_get($structured, 'slides', []);

        $renderedSlides = [];

        if ($socialAccount) {
            $generator = new TemplateImageGenerator(new UnsplashClient, new BrandColorMapper);
            ['width' => $width, 'height' => $height] = $this->dimensionsForFormat();

            foreach ($slides as $i => $slide) {
                // First slide is always Template A (full-bleed cover); subsequent slides
                // alternate so even-indexed are A and odd-indexed are B.
                $template = $i === 0 ? 'A' : ($i % 2 === 0 ? 'A' : 'B');

                $path = $generator->render(
                    template: $template,
                    workspace: $workspace,
                    socialAccount: $socialAccount,
                    title: data_get($slide, 'title', ''),
                    body: data_get($slide, 'body', ''),
                    imageKeywords: data_get($slide, 'image_keywords', []),
                    width: $width,
                    height: $height,
                );

                $renderedSlides[] = [
                    'title' => data_get($slide, 'title', ''),
                    'body' => data_get($slide, 'body', ''),
                    'image_path' => $path,
                ];
            }

            // Auto-append closing slide (Template C) at the end of the carousel.
            $closingPath = $generator->renderClosing(
                workspace: $workspace,
                socialAccount: $socialAccount,
                width: $width,
                height: $height,
            );

            if ($closingPath) {
                $renderedSlides[] = [
                    'title' => null,
                    'body' => null,
                    'image_path' => $closingPath,
                    'is_closing' => true,
                ];
            }
        } else {
            foreach ($slides as $slide) {
                $renderedSlides[] = [
                    'title' => data_get($slide, 'title', ''),
                    'body' => data_get($slide, 'body', ''),
                    'image_path' => null,
                ];
            }
        }

        Cache::put("ai-creation:{$this->creationId}", [
            'workspace_id' => $this->workspaceId,
            'user_id' => $this->userId,
            'format' => $this->format,
            'social_account_id' => $this->socialAccountId,
            'content' => $caption,
            'slides' => $renderedSlides,
            'created_at' => now()->toIso8601String(),
        ], now()->addMinutes(30));

        PostCreationReady::dispatch($this->userId, $this->creationId, $caption);
    }

    /**
     * @param  array<string, mixed>  $structured
     */
    private function handleSingle(Workspace $workspace, ?SocialAccount $socialAccount, array $structured): void
    {
        $content = data_get($structured, 'content', data_get($structured, 'text', ''));
        $imageTitle = data_get($structured, 'image_title', '');
        $imageBody = data_get($structured, 'image_body', '');
        $keywords = data_get($structured, 'image_keywords', []);

        $imagePath = null;

        if ($this->imageCount > 0 && $socialAccount) {
            $generator = new TemplateImageGenerator(new UnsplashClient, new BrandColorMapper);
            ['width' => $width, 'height' => $height] = $this->dimensionsForFormat();

            $imagePath = $generator->render(
                template: 'A',
                workspace: $workspace,
                socialAccount: $socialAccount,
                title: $imageTitle,
                body: $imageBody,
                imageKeywords: $keywords,
                width: $width,
                height: $height,
            );
        }

        Cache::put("ai-creation:{$this->creationId}", [
            'workspace_id' => $this->workspaceId,
            'user_id' => $this->userId,
            'format' => $this->format,
            'social_account_id' => $this->socialAccountId,
            'image_count' => $this->imageCount,
            'content' => $content,
            'image_title' => $imageTitle,
            'image_body' => $imageBody,
            'image_keywords' => $keywords,
            'image_path' => $imagePath,
            'created_at' => now()->toIso8601String(),
        ], now()->addMinutes(30));

        // Broadcast all three fields — the frontend renders caption (when the
        // format supports it) or title+body separately (for caption-less story
        // formats). No string parsing on the way back.
        PostCreationReady::dispatch(
            userId: $this->userId,
            creationId: $this->creationId,
            content: $content,
            imageTitle: $imageTitle,
            imageBody: $imageBody,
        );
    }
}
