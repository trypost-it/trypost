<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TextGenerationService
{
    private string $apiKey;

    private string $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->apiKey = config('ai.providers.openai.api_key', '');
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function generate(string $prompt, array $history = []): string
    {
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a social media content expert. Help users write engaging captions, hashtags, and post content. Be creative, concise, and on-brand. Respond in the same language the user writes in.',
            ],
            ...$history,
            ['role' => 'user', 'content' => $prompt],
        ];

        $response = Http::timeout(60)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => 'gpt-4o',
                'messages' => $messages,
                'max_tokens' => 2048,
                'temperature' => 0.7,
            ]);

        if ($response->failed()) {
            Log::error('TextGenerationService failed', ['body' => $response->body()]);

            throw new \RuntimeException('Failed to generate text. Please try again.');
        }

        return data_get($response->json(), 'choices.0.message.content', '');
    }
}
