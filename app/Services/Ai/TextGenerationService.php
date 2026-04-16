<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Workspace;
use App\Services\Ai\Contracts\TextGenerationInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TextGenerationService implements TextGenerationInterface
{
    private string $apiKey;

    private string $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key') ?? '';
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function generate(string $prompt, array $history = [], ?Workspace $workspace = null, ?string $imageUrl = null): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OpenAI API key is not configured. Please set OPENAI_API_KEY in your .env file.');
        }

        $systemPrompt = view('prompts.assistant.system', [
            'brand_name' => $workspace?->name ?? '',
            'brand_description' => $workspace?->brand_description ?? '',
            'brand_website' => $workspace?->brand_website ?? '',
            'tone' => $workspace?->brand_tone ?? 'professional',
            'voice_notes' => $workspace?->brand_voice_notes ?? '',
            'locale' => app()->getLocale(),
        ])->render();

        if ($imageUrl) {
            $userContent = [
                ['type' => 'text', 'text' => $prompt],
                ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
            ];
        } else {
            $userContent = $prompt;
        }

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            ...$history,
            ['role' => 'user', 'content' => $userContent],
        ];

        $response = Http::timeout(60)
            ->withToken($this->apiKey)
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
