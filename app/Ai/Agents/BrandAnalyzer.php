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
        return match (config('trypost.ai.text_provider')) {
            'openai' => Lab::OpenAI,
            default => Lab::Gemini,
        };
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
        ];
    }
}
