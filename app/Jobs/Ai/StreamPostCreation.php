<?php

declare(strict_types=1);

namespace App\Jobs\Ai;

use App\Actions\Post\CreatePost;
use App\Ai\Agents\PostContentGenerator;
use App\Ai\Agents\PostContentHumanizer;
use App\Enums\Media\Source;
use App\Enums\Media\Type as MediaType;
use App\Enums\Notification\Channel as NotificationChannel;
use App\Enums\Notification\Type as NotificationType;
use App\Enums\PostPlatform\ContentType;
use App\Events\Ai\PostCreationReady;
use App\Jobs\SendNotification;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\AiImageClient;
use App\Services\Ai\RecordAiUsage;
use App\Services\Image\BrandColorMapper;
use App\Services\Image\TemplateImageGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        public ?string $date = null,
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

            RecordAiUsage::recordText(
                workspace: $workspace,
                promptTokens: $response->usage->promptTokens,
                completionTokens: $response->usage->completionTokens,
                provider: (string) config('ai.default'),
                model: (string) config('ai.default_text_model'),
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

            RecordAiUsage::recordText(
                workspace: $workspace,
                promptTokens: $response->usage->promptTokens,
                completionTokens: $response->usage->completionTokens,
                provider: (string) config('ai.default'),
                model: (string) config('ai.default_text_model'),
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

    private function handleCarousel(Workspace $workspace, ?SocialAccount $socialAccount, array $structured): void
    {
        $caption = (string) data_get($structured, 'caption', '');
        $slides = data_get($structured, 'slides', []);

        $media = [];

        if ($socialAccount) {
            $generator = new TemplateImageGenerator(new BrandColorMapper, new AiImageClient);
            ['width' => $width, 'height' => $height] = $this->dimensionsForFormat();

            foreach ($slides as $slide) {
                $rendered = $generator->render(
                    workspace: $workspace,
                    socialAccount: $socialAccount,
                    title: data_get($slide, 'title', ''),
                    body: data_get($slide, 'body', ''),
                    imageKeywords: data_get($slide, 'image_keywords', []),
                    width: $width,
                    height: $height,
                );

                if ($rendered) {
                    $media[] = $this->buildAiMediaItem($workspace, $rendered);
                }
            }
        }

        $post = $this->createPost($workspace, $caption, $media, $socialAccount);

        $this->notifyReady($workspace, $post);
    }

    /**
     * @param  array<string, mixed>  $structured
     */
    private function handleSingle(Workspace $workspace, ?SocialAccount $socialAccount, array $structured): void
    {
        $contentType = ContentType::tryFrom($this->format);
        $supportsCaption = $contentType?->supportsCaption() ?? true;

        $rawContent = (string) data_get($structured, 'content', data_get($structured, 'text', ''));
        $imageTitle = (string) data_get($structured, 'image_title', '');
        $imageBody = (string) data_get($structured, 'image_body', '');
        $keywords = data_get($structured, 'image_keywords', []);

        $media = [];

        if ($this->imageCount > 0 && $socialAccount) {
            $generator = new TemplateImageGenerator(new BrandColorMapper, new AiImageClient);
            ['width' => $width, 'height' => $height] = $this->dimensionsForFormat();

            $rendered = $generator->render(
                workspace: $workspace,
                socialAccount: $socialAccount,
                title: $imageTitle,
                body: $imageBody,
                imageKeywords: $keywords,
                width: $width,
                height: $height,
            );

            if ($rendered) {
                $media[] = $this->buildAiMediaItem($workspace, $rendered);
            }
        }

        $caption = $supportsCaption ? $rawContent : '';
        $post = $this->createPost($workspace, $caption, $media, $socialAccount);

        $this->notifyReady($workspace, $post);
    }

    /**
     * @param  array<int, array<string, mixed>>  $media
     */
    private function createPost(Workspace $workspace, string $content, array $media, ?SocialAccount $socialAccount): Post
    {
        $user = User::findOrFail($this->userId);

        $post = CreatePost::execute($workspace, $user, [
            'content' => $content,
            'media' => $media,
            'date' => $this->date,
        ]);

        $contentType = ContentType::tryFrom($this->format);

        if ($contentType && $socialAccount) {
            $aspectRatio = $this->aspectRatioFor($contentType);
            $platformContentType = $contentType === ContentType::InstagramCarousel
                ? ContentType::InstagramFeed
                : $contentType;

            $post->postPlatforms()
                ->where('social_account_id', $socialAccount->id)
                ->each(function ($platform) use ($aspectRatio, $platformContentType): void {
                    $meta = $platform->meta ?? [];
                    if ($aspectRatio !== null) {
                        $meta['aspect_ratio'] = $aspectRatio;
                    }
                    $platform->meta = $meta;
                    $platform->content_type = $platformContentType->value;
                    $platform->enabled = true;
                    $platform->save();
                });
        }

        return $post;
    }

    private function notifyReady(Workspace $workspace, Post $post): void
    {
        PostCreationReady::dispatch(
            userId: $this->userId,
            creationId: $this->creationId,
            postId: $post->id,
        );

        $user = User::findOrFail($this->userId);

        SendNotification::dispatch(
            user: $user,
            workspaceId: $workspace->id,
            type: NotificationType::PostReady,
            channel: NotificationChannel::InApp,
            title: trans('notifications.post_ready.title', [], $workspace->content_language),
            body: trans('notifications.post_ready.body', [], $workspace->content_language),
            data: ['post_id' => $post->id],
        );
    }

    private function aspectRatioFor(ContentType $type): ?string
    {
        $dims = $type->aiImageDimensions();
        $ratio = $dims['width'] / $dims['height'];

        return match (true) {
            abs($ratio - 1.0) < 0.01 => '1:1',
            abs($ratio - 4 / 5) < 0.01 => '4:5',
            abs($ratio - 16 / 9) < 0.01 => '16:9',
            default => null,
        };
    }

    /**
     * @param  array{path: string, source_meta: array<string, mixed>}  $rendered
     * @return array<string, mixed>
     */
    private function buildAiMediaItem(Workspace $workspace, array $rendered): array
    {
        $media = $workspace->media()->create([
            'collection' => 'ai-generated',
            'type' => MediaType::Image,
            'path' => $rendered['path'],
            'original_filename' => basename($rendered['path']),
            'mime_type' => 'image/webp',
            'size' => Storage::size($rendered['path']),
            'order' => 0,
        ]);

        return [
            'id' => $media->id,
            'path' => $media->path,
            'url' => $media->url,
            'type' => 'image',
            'mime_type' => 'image/webp',
            'source' => Source::Ai->value,
            'source_meta' => $rendered['source_meta'],
        ];
    }
}
