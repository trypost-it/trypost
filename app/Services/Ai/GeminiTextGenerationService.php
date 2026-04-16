<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Workspace;
use App\Services\Ai\Contracts\TextGenerationInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiTextGenerationService implements TextGenerationInterface
{
    private string $apiKey;

    private string $model = 'gemini-2.5-flash-lite';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key') ?? '';
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function generate(string $prompt, array $history = [], ?Workspace $workspace = null, ?string $imageUrl = null): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Gemini API key is not configured. Please set GEMINI_API_KEY in your .env file.');
        }

        $systemPrompt = view('prompts.assistant.system', [
            'brand_name' => $workspace?->name ?? '',
            'brand_description' => $workspace?->brand_description ?? '',
            'brand_website' => $workspace?->brand_website ?? '',
            'tone' => $workspace?->brand_tone ?? 'professional',
            'voice_notes' => $workspace?->brand_voice_notes ?? '',
            'locale' => app()->getLocale(),
        ])->render();

        $contents = [];

        // Add history
        foreach ($history as $message) {
            $contents[] = [
                'role' => data_get($message, 'role') === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => data_get($message, 'content')]],
            ];
        }

        // Add current prompt
        $parts = [['text' => $prompt]];

        if ($imageUrl) {
            $imageResponse = Http::timeout(30)->get($imageUrl);
            if ($imageResponse->successful()) {
                $parts[] = [
                    'inlineData' => [
                        'mimeType' => $imageResponse->header('Content-Type', 'image/jpeg'),
                        'data' => base64_encode($imageResponse->body()),
                    ],
                ];
            }
        }

        $contents[] = ['role' => 'user', 'parts' => $parts];

        $response = Http::timeout(60)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
                'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents' => $contents,
                'generationConfig' => [
                    'maxOutputTokens' => 2048,
                    'temperature' => 0.7,
                ],
            ]);

        if ($response->failed()) {
            Log::error('GeminiTextGenerationService failed', ['body' => $response->body()]);

            throw new \RuntimeException('Failed to generate text. Please try again.');
        }

        return data_get($response->json(), 'candidates.0.content.parts.0.text', '');
    }
}
