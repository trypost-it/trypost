<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Second-pass agent that rewrites AI-generated post text to remove AI-tells.
 * Operates on the same structured shape as PostContentGenerator (single or
 * carousel) but only touches human-readable text fields — image_keywords pass
 * through untouched (those need to stay in English for Unsplash regardless).
 */
#[Temperature(0.4)]
class PostContentHumanizer implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public Workspace $workspace,
        public string $format = 'single',
    ) {}

    public function instructions(): string
    {
        return view('prompts.post_content.humanizer', [
            'brand_name' => $this->workspace->name ?? '',
            'brand_tone' => $this->workspace->brand_tone ?? '',
            'brand_voice_notes' => $this->workspace->brand_voice_notes ?? '',
            'content_language' => $this->workspace->content_language,
            'format' => $this->format,
        ])->render();
    }

    public function schema(JsonSchema $schema): array
    {
        if ($this->format === 'carousel') {
            return [
                'caption' => $schema->string()->description('The humanized Instagram caption.')->required(),
                'slides' => $schema->array()
                    ->items($schema->object(fn ($s) => [
                        'title' => $s->string()->description('Humanized slide headline.')->required(),
                        'body' => $s->string()->description('Humanized slide body.')->required(),
                    ]))
                    ->description('The same number of slides as the input, in the same order, with humanized text.')
                    ->required(),
            ];
        }

        return [
            'content' => $schema->string()->description('The humanized post caption.')->required(),
            'image_title' => $schema->string()->description('The humanized image overlay title.')->required(),
            'image_body' => $schema->string()->description('The humanized image overlay body.')->required(),
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
