<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[UseCheapestModel]
class Humanizer implements Agent
{
    use Promptable;

    public function __construct(public string $instructions) {}

    public function instructions(): string
    {
        return $this->instructions;
    }

    public function provider(): Lab
    {
        return match (config('trypost.ai.text_provider')) {
            'openai' => Lab::OpenAI,
            default => Lab::Gemini,
        };
    }
}
