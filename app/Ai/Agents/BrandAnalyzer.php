<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

class BrandAnalyzer implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return view('prompts.brand_analyzer')->render();
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

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('The actual brand or company name (1-4 words, e.g. "Sendkit", "Acme Coffee", "Stripe"). Strip any tagline, slogan, or product descriptor — return only the brand identity itself.')
                ->required(),
            'description' => $schema->string()
                ->description('A concise 2-3 sentence brand description summarizing what the company does, who they serve, and what makes them unique. Written in the detected content language.')
                ->required(),
            'tone' => $schema->string()
                ->enum(['professional', 'casual', 'friendly', 'bold', 'inspirational', 'humorous', 'educational'])
                ->description('The tone of voice the brand uses in their content.')
                ->required(),
            'language' => $schema->string()
                ->enum(['en', 'pt-BR', 'es'])
                ->description('The primary language of the content.')
                ->required(),
            'voice_notes' => $schema->string()
                ->description('2-3 sentences of concrete writing guidelines inferred from the site style (e.g. "Use technical but approachable language", "Avoid marketing buzzwords"). Written in the detected content language.')
                ->required(),
            'brand_color' => $schema->string()
                ->description('The primary brand color as a hex string starting with # (e.g. "#0ea5e9"). Pick the most prominent accent color used in CTAs, links, or logos. Return empty string if not confidently identifiable.')
                ->required(),
            'background_color' => $schema->string()
                ->description('The dominant page background color as a hex string starting with # (e.g. "#ffffff" or "#0b0f19"). Return empty string if not confidently identifiable.')
                ->required(),
            'text_color' => $schema->string()
                ->description('The dominant body text color as a hex string starting with # (e.g. "#0f172a"). Return empty string if not confidently identifiable.')
                ->required(),
        ];
    }
}
