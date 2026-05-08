<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Enums\PostPlatform\ContentType;
use App\Models\Workspace;
use App\Services\Ai\TemplateContextResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Temperature(0.7)]
class PostContentGenerator implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public Workspace $workspace,
        public ?string $currentContent = null,
        public string $format = 'single',
        public int $slideCount = 1,
        public ?string $platformContext = null,
    ) {}

    public function instructions(): string
    {
        $examples = [];

        if ($this->platformContext !== null) {
            $resolver = app(TemplateContextResolver::class);
            $examples = $resolver->relevantFor($this->platformContext, 2)
                ->map(fn ($t) => [
                    'name' => $t->name,
                    'description' => $t->description,
                    'content' => $t->content,
                    'slides' => $t->slides,
                ])
                ->all();
        }

        // Two budgets: the platform's HARD cap (must never exceed — would break
        // publishing) and the recommended SWEET SPOT length (what well-performing
        // posts actually look like, way below the hard cap on most platforms).
        $hardMaxChars = null;
        $targetChars = null;
        $platformLabel = null;
        $contentType = $this->platformContext ? ContentType::tryFrom($this->platformContext) : null;
        if ($contentType) {
            $platform = $contentType->platform();
            $hardMaxChars = $platform->maxContentLength();
            $targetChars = $platform->recommendedAiContentLength();
            $platformLabel = $platform->label();
        }

        return view('prompts.post_content.generator', [
            'brand_name' => $this->workspace->name ?? '',
            'brand_description' => $this->workspace->brand_description ?? '',
            'brand_tone' => $this->workspace->brand_tone ?? '',
            'brand_voice_notes' => $this->workspace->brand_voice_notes ?? '',
            'content_language' => $this->workspace->content_language,
            'current_content' => $this->currentContent,
            'format' => $this->format,
            'slide_count' => $this->slideCount,
            'examples' => $examples,
            'hard_max_chars' => $hardMaxChars,
            'target_chars' => $targetChars,
            'platform_label' => $platformLabel,
        ])->render();
    }

    public function schema(JsonSchema $schema): array
    {
        if ($this->format === 'carousel') {
            return [
                'caption' => $schema->string()->description('The Instagram caption for the carousel post.')->required(),
                'slides' => $schema->array()
                    ->items($schema->object(fn ($s) => [
                        'title' => $s->string()->description('Headline of the slide. Short, impactful.')->required(),
                        'body' => $s->string()->description('Supporting text below the headline. 1-3 sentences.')->required(),
                        'image_keywords' => $s->array()->items($schema->string())->description('2-4 search keywords for Unsplash.')->required(),
                    ]))
                    ->min($this->slideCount)
                    ->max($this->slideCount)
                    ->description("Exactly {$this->slideCount} slides for the carousel, in order.")
                    ->required(),
            ];
        }

        return [
            'content' => $schema->string()->description('The full post caption text that will be published on the platform.')->required(),
            'image_title' => $schema->string()->description('Short headline (5-12 words) overlaid on the image. The hook — should make a scroller stop. Distinct from content.')->required(),
            'image_body' => $schema->string()->description('1-2 short sentences (max 25 words) overlaid below the image_title. Expands the hook just enough to compel reading the caption.')->required(),
            'image_keywords' => $schema->array()->items($schema->string())->description('2-4 search keywords for Unsplash for the single image.')->required(),
        ];
    }

    public function provider(): Lab
    {
        return match (config('ai.default')) {
            'openai' => Lab::OpenAI,
            'anthropic' => Lab::Anthropic,
            default => Lab::Gemini,
        };
    }

    public function model(): string
    {
        return config('ai.default_text_model');
    }
}
