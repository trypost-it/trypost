<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Features\AiImagesLimit;
use App\Features\AiVideosLimit;
use App\Models\AiMessage;
use App\Models\AiUsageLog;
use App\Models\Post;
use App\Services\Ai\AudioGenerationService;
use App\Services\Ai\ImageGenerationService;
use App\Services\Ai\IntentDetector;
use App\Services\Ai\TextGenerationService;
use App\Services\Ai\VideoGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

class PostAssistantController extends Controller
{
    public function index(Request $request, Post $post): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if ($post->workspace_id !== $workspace->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $messages = $post->aiMessages()
            ->with('user')
            ->oldest()
            ->get();

        return response()->json(['messages' => $messages]);
    }

    public function store(
        Request $request,
        Post $post,
        IntentDetector $intentDetector,
        TextGenerationService $textService,
        ImageGenerationService $imageService,
        AudioGenerationService $audioService,
        VideoGenerationService $videoService,
    ): JsonResponse {
        $workspace = $request->user()->currentWorkspace;

        if ($post->workspace_id !== $workspace->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $prompt = data_get($validated, 'body');

        $userMessage = $post->aiMessages()->create([
            'user_id' => $request->user()->id,
            'role' => 'user',
            'content' => $prompt,
        ]);

        $userMessage->load('user');

        $intent = $intentDetector->detect($prompt);

        try {
            if ($intent === 'image') {
                $limit = Feature::for($workspace->account)->value(AiImagesLimit::class);
                $used = AiUsageLog::monthlyCount($workspace->account_id, 'image');

                if ($used >= $limit) {
                    $assistantMessage = $post->aiMessages()->create([
                        'role' => 'assistant',
                        'content' => __('assistant.limit_reached_images'),
                        'metadata' => ['intent' => $intent, 'limit_reached' => true],
                    ]);

                    return response()->json(['user_message' => $userMessage, 'assistant_message' => $assistantMessage], Response::HTTP_CREATED);
                }
            }

            if ($intent === 'video') {
                $limit = Feature::for($workspace->account)->value(AiVideosLimit::class);
                $used = AiUsageLog::monthlyCount($workspace->account_id, 'video');

                if ($used >= $limit) {
                    $assistantMessage = $post->aiMessages()->create([
                        'role' => 'assistant',
                        'content' => __('assistant.limit_reached_videos'),
                        'metadata' => ['intent' => $intent, 'limit_reached' => true],
                    ]);

                    return response()->json(['user_message' => $userMessage, 'assistant_message' => $assistantMessage], Response::HTTP_CREATED);
                }
            }

            $responseContent = '';
            $attachments = [];

            if ($intent === 'image') {
                $result = $imageService->generate($prompt, $workspace, $request->user()->id, $post->id);
                $responseContent = __('assistant.image_generated');
                $attachments = [$result];
            } elseif ($intent === 'video') {
                $result = $videoService->generate($prompt, $workspace, $request->user()->id, $post->id);
                $responseContent = __('assistant.video_generated');
                $attachments = [$result];
            } elseif ($intent === 'audio') {
                $result = $audioService->generate($prompt, $workspace, $request->user()->id, $post->id);
                $responseContent = __('assistant.audio_generated');
                $attachments = [$result];
            } else {
                $history = $post->aiMessages()
                    ->whereIn('role', ['user', 'assistant'])
                    ->where('id', '!=', $userMessage->id)
                    ->oldest()
                    ->limit(20)
                    ->get()
                    ->map(fn (AiMessage $m) => ['role' => $m->role, 'content' => $m->content])
                    ->all();

                $responseContent = $textService->generate($prompt, $history, $workspace);
            }

            $assistantMessage = $post->aiMessages()->create([
                'role' => 'assistant',
                'content' => $responseContent,
                'attachments' => $attachments,
                'metadata' => ['intent' => $intent],
            ]);

            return response()->json([
                'user_message' => $userMessage,
                'assistant_message' => $assistantMessage,
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            Log::error('PostAssistantController error', ['error' => $e->getMessage()]);

            $errorMessage = $e instanceof \RuntimeException ? $e->getMessage() : __('assistant.error');

            $assistantMessage = $post->aiMessages()->create([
                'role' => 'assistant',
                'content' => $errorMessage,
                'metadata' => ['intent' => $intent, 'error' => true],
            ]);

            return response()->json([
                'user_message' => $userMessage,
                'assistant_message' => $assistantMessage,
            ], Response::HTTP_CREATED);
        }
    }
}
