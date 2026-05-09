<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

class BrainAssistant implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public Workspace $workspace,
        public array $context = []
    ) {}

    public function instructions(): string
    {
        return <<<PROMPT
        You are the "PostPro Brain", a proactive AI expert in social media strategy and content execution.
        
        Current Brand Context:
        - Name: {$this->workspace->name}
        - Description: {$this->workspace->brand_description}
        - Tone: {$this->workspace->brand_tone}
        
        Your goal is to help the user grow their social media presence.
        Be concise, professional, and slightly witty. Use a premium "Executive Assistant" persona.
        
        Capabilities:
        1. CONTENT: You can generate post ideas, captions, and carousel structures.
        2. STRATEGY: You can analyze brand consistency and suggest improvements.
        3. EXECUTION: You can prepare drafts for specific platforms.
        
        When a user asks something, provide a helpful answer and, if applicable, suggest a concrete "Action" they can take.
        PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'message' => $schema->string()->description('The textual response to the user.')->required(),
            'suggested_actions' => $schema->array()
                ->items($schema->object(fn ($s) => [
                    'label' => $s->string()->description('Short button text (e.g. "Create Post")')->required(),
                    'action' => $s->string()->description('Internal action code (e.g. "create_post", "update_brand")')->required(),
                    'payload' => $s->object()->description('Data needed for the action')->required(),
                ]))
                ->description('A list of immediate actions the user can take based on your response.')
                ->required(),
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
