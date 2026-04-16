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

class VideoGenerationService
{
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    private string $model = 'veo-3.1-generate-preview';

    private int $maxPollAttempts = 30;

    private int $pollIntervalSeconds = 10;

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

        $fullPrompt = view('prompts.assistant.video', [
            'prompt' => $prompt,
            'brand_name' => $workspace->name ?? '',
            'tone' => $workspace->brand_tone ?? 'professional',
        ])->render();

        $operationName = $this->startGeneration($fullPrompt, $aspectRatio);
        $videoData = $this->pollForCompletion($operationName);

        $decoded = base64_decode($videoData);

        return DB::transaction(function () use ($decoded, $prompt, $workspace, $userId, $postId) {
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
                'type' => UsageType::Video,
                'provider' => 'veo',
            ]);

            return [
                'id' => $media->id,
                'path' => $media->path,
                'url' => $media->url,
                'mime_type' => 'video/mp4',
                'type' => 'video',
            ];
        });
    }

    private function startGeneration(string $prompt, string $aspectRatio = '9:16'): string
    {
        $response = Http::timeout(30)
            ->withHeaders(['x-goog-api-key' => $this->apiKey])
            ->post("{$this->baseUrl}/models/{$this->model}:predictLongRunning", [
                'instances' => [['prompt' => $prompt]],
                'parameters' => [
                    'aspectRatio' => $aspectRatio,
                    'durationSeconds' => 8,
                    'resolution' => '720p',
                    'personGeneration' => 'allow_all',
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

        return $operationName;
    }

    private function pollForCompletion(string $operationName): string
    {
        for ($i = 0; $i < $this->maxPollAttempts; $i++) {
            sleep($this->pollIntervalSeconds);

            $response = Http::timeout(30)
                ->withHeaders(['x-goog-api-key' => $this->apiKey])
                ->get("{$this->baseUrl}/{$operationName}");

            if ($response->failed()) {
                continue;
            }

            $status = $response->json();

            if (data_get($status, 'done')) {
                $videoData = data_get($status, 'response.predictions.0.bytesBase64Encoded');

                if ($videoData) {
                    return $videoData;
                }
            }
        }

        throw new \RuntimeException('Video generation timed out. Please try again.');
    }
}
