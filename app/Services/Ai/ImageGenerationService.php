<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\Ai\Orientation;
use App\Enums\Ai\UsageType;
use App\Models\AiUsageLog;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageGenerationService
{
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    private string $model = 'gemini-2.5-flash-image';

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key') ?? '';
    }

    /**
     * @return array{id: string, path: string, url: string, mime_type: string, type: string}
     */
    public function generate(string $prompt, Workspace $workspace, ?string $userId = null, ?string $postId = null, Orientation $orientation = Orientation::Vertical): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Gemini API key is not configured. Please set GEMINI_API_KEY in your .env file.');
        }

        $aspectRatio = $orientation->aspectRatio();

        $fullPrompt = view('prompts.assistant.image', [
            'prompt' => $prompt,
            'brand_name' => $workspace->name ?? '',
            'tone' => $workspace->brand_tone ?? 'professional',
            'aspect_ratio' => $aspectRatio,
        ])->render();

        $response = Http::timeout(120)
            ->post("{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}", [
                'contents' => [['parts' => [['text' => $fullPrompt]]]],
                'generationConfig' => ['responseModalities' => ['TEXT', 'IMAGE']],
            ]);

        if ($response->failed()) {
            Log::error('ImageGenerationService failed', ['body' => $response->body()]);

            throw new \RuntimeException('Failed to generate image. Please try again.');
        }

        $imageData = $this->extractImageFromResponse($response->json());

        if (! $imageData) {
            Log::warning('ImageGenerationService: no image in response', [
                'response' => $response->json(),
            ]);

            throw new \RuntimeException('No image was generated. Try a different prompt.');
        }

        return DB::transaction(function () use ($imageData, $prompt, $workspace, $userId, $postId) {
            $extension = $this->getExtension(data_get($imageData, 'mimeType', 'image/png'));
            $mimeType = data_get($imageData, 'mimeType', 'image/png');
            $decoded = base64_decode(data_get($imageData, 'data'));

            $filename = Str::uuid().'.'.$extension;
            $path = 'medias/'.$filename;

            Storage::put($path, $decoded);

            $media = $workspace->media()->create([
                'group_id' => Str::uuid()->toString(),
                'collection' => 'assets',
                'type' => 'image',
                'path' => $path,
                'original_filename' => 'ai-generated.'.$extension,
                'mime_type' => $mimeType,
                'size' => strlen($decoded),
                'order' => 0,
                'meta' => ['ai_generated' => true, 'prompt' => Str::limit($prompt, 200)],
            ]);

            AiUsageLog::create([
                'account_id' => $workspace->account_id,
                'workspace_id' => $workspace->id,
                'user_id' => $userId,
                'post_id' => $postId,
                'type' => UsageType::Image,
                'provider' => 'gemini',
            ]);

            return [
                'id' => $media->id,
                'path' => $media->path,
                'url' => $media->url,
                'mime_type' => $mimeType,
                'type' => 'image',
            ];
        });
    }

    private function extractImageFromResponse(array $response): ?array
    {
        $parts = data_get($response, 'candidates.0.content.parts', []);

        foreach ($parts as $part) {
            if (data_get($part, 'inlineData')) {
                return data_get($part, 'inlineData');
            }
        }

        return null;
    }

    private function getExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'png',
        };
    }
}
