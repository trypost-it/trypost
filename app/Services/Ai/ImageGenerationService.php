<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\AiUsageLog;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageGenerationService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('ai.providers.gemini.api_key', '');
    }

    /**
     * @return array{id: string, path: string, url: string, mime_type: string, type: string}
     */
    public function generate(string $prompt, Workspace $workspace, ?string $userId = null, ?string $postId = null): array
    {
        $response = Http::timeout(120)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key={$this->apiKey}", [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['responseModalities' => ['TEXT', 'IMAGE']],
            ]);

        if ($response->failed()) {
            Log::error('ImageGenerationService failed', ['body' => $response->body()]);

            throw new \RuntimeException('Failed to generate image. Please try again.');
        }

        $parts = data_get($response->json(), 'candidates.0.content.parts', []);

        foreach ($parts as $part) {
            if (data_get($part, 'inlineData')) {
                $imageData = base64_decode(data_get($part, 'inlineData.data'));
                $mimeType = data_get($part, 'inlineData.mimeType', 'image/png');
                $extension = match ($mimeType) {
                    'image/jpeg' => 'jpg',
                    'image/webp' => 'webp',
                    default => 'png',
                };

                $filename = Str::uuid().'.'.$extension;
                $path = 'medias/'.$filename;

                Storage::put($path, $imageData);

                $media = $workspace->media()->create([
                    'group_id' => Str::uuid()->toString(),
                    'collection' => 'assets',
                    'type' => 'image',
                    'path' => $path,
                    'original_filename' => 'ai-generated.'.$extension,
                    'mime_type' => $mimeType,
                    'size' => strlen($imageData),
                    'order' => 0,
                    'meta' => ['ai_generated' => true, 'prompt' => Str::limit($prompt, 200)],
                ]);

                AiUsageLog::create([
                    'account_id' => $workspace->account_id,
                    'workspace_id' => $workspace->id,
                    'user_id' => $userId,
                    'post_id' => $postId,
                    'type' => 'image',
                    'provider' => 'gemini',
                ]);

                return [
                    'id' => $media->id,
                    'path' => $media->path,
                    'url' => $media->url,
                    'mime_type' => $mimeType,
                    'type' => 'image',
                ];
            }
        }

        throw new \RuntimeException('No image was generated. Try a different prompt.');
    }
}
