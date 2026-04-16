<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\AiUsageLog;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoGenerationService
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
        $response = Http::timeout(30)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/veo-3.0-generate-preview:predictLongRunning?key={$this->apiKey}", [
                'instances' => [['prompt' => $prompt]],
                'parameters' => [
                    'aspectRatio' => '9:16',
                    'sampleCount' => 1,
                    'durationSeconds' => 8,
                    'generateAudio' => true,
                ],
            ]);

        if ($response->failed()) {
            Log::error('VideoGenerationService start failed', ['body' => $response->body()]);

            throw new \RuntimeException('Failed to start video generation. Please try again.');
        }

        $operationName = data_get($response->json(), 'name');

        if (! $operationName) {
            throw new \RuntimeException('No operation returned from video generation API.');
        }

        $maxAttempts = 30;
        $videoData = null;

        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep(10);

            $statusResponse = Http::timeout(30)
                ->get("https://generativelanguage.googleapis.com/v1beta/{$operationName}?key={$this->apiKey}");

            if ($statusResponse->failed()) {
                continue;
            }

            $status = $statusResponse->json();

            if (data_get($status, 'done')) {
                $videoData = data_get($status, 'response.predictions.0.bytesBase64Encoded');
                break;
            }
        }

        if (! $videoData) {
            throw new \RuntimeException('Video generation timed out. Please try again.');
        }

        $decoded = base64_decode($videoData);
        $filename = Str::uuid().'.mp4';
        $path = 'medias/'.$filename;

        Storage::put($path, $decoded);

        $media = $workspace->media()->create([
            'group_id' => Str::uuid()->toString(),
            'collection' => 'assets',
            'type' => 'video',
            'path' => $path,
            'original_filename' => 'ai-generated.mp4',
            'mime_type' => 'video/mp4',
            'size' => strlen($decoded),
            'order' => 0,
            'meta' => ['ai_generated' => true, 'prompt' => Str::limit($prompt, 200)],
        ]);

        AiUsageLog::create([
            'account_id' => $workspace->account_id,
            'workspace_id' => $workspace->id,
            'user_id' => $userId,
            'post_id' => $postId,
            'type' => 'video',
            'provider' => 'veo',
        ]);

        return [
            'id' => $media->id,
            'path' => $media->path,
            'url' => $media->url,
            'mime_type' => 'video/mp4',
            'type' => 'video',
        ];
    }
}
