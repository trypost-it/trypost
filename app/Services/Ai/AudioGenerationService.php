<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\AiUsageLog;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AudioGenerationService
{
    private string $apiKey;

    private string $baseUrl = 'https://api.elevenlabs.io/v1';

    public function __construct()
    {
        $this->apiKey = config('services.elevenlabs.api_key', '');
    }

    /**
     * @return array{id: string, path: string, url: string, mime_type: string, type: string}
     */
    public function generate(string $text, Workspace $workspace, ?string $userId = null, ?string $postId = null, string $voiceId = 'EXAVITQu4vr4xnSDxMaL'): array
    {
        $response = Http::timeout(120)
            ->withHeaders([
                'xi-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'audio/mpeg',
            ])
            ->post("{$this->baseUrl}/text-to-speech/{$voiceId}", [
                'text' => $text,
                'model_id' => 'eleven_multilingual_v2',
                'voice_settings' => [
                    'stability' => 0.5,
                    'similarity_boost' => 0.75,
                ],
            ]);

        if ($response->failed()) {
            Log::error('AudioGenerationService failed', ['body' => $response->body()]);

            throw new \RuntimeException('Failed to generate audio. Please try again.');
        }

        $filename = Str::uuid().'.mp3';
        $path = 'medias/'.$filename;

        Storage::put($path, $response->body());

        $media = $workspace->media()->create([
            'group_id' => Str::uuid()->toString(),
            'collection' => 'assets',
            'type' => 'document',
            'path' => $path,
            'original_filename' => 'ai-generated.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => strlen($response->body()),
            'order' => 0,
            'meta' => ['ai_generated' => true, 'text' => Str::limit($text, 200)],
        ]);

        AiUsageLog::create([
            'account_id' => $workspace->account_id,
            'workspace_id' => $workspace->id,
            'user_id' => $userId,
            'post_id' => $postId,
            'type' => 'audio',
            'provider' => 'elevenlabs',
        ]);

        return [
            'id' => $media->id,
            'path' => $media->path,
            'url' => $media->url,
            'mime_type' => 'audio/mpeg',
            'type' => 'audio',
        ];
    }
}
