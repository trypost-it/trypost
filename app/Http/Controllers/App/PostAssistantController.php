<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Ai\Agents\SocialMediaAssistant;
use App\Ai\Tools\AttachmentCollector;
use App\Enums\Ai\Intent;
use App\Enums\Ai\UsageType;
use App\Features\AiImagesLimit;
use App\Features\AiVideosLimit;
use App\Http\Requests\App\Assistant\StoreAssistantMessageRequest;
use App\Models\AiUsageLog;
use App\Models\Post;
use App\Services\Ai\IntentDetector;
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
        StoreAssistantMessageRequest $request,
        Post $post,
        IntentDetector $intentDetector,
        AttachmentCollector $collector,
    ): JsonResponse {
        $workspace = $request->user()->currentWorkspace;

        if ($post->workspace_id !== $workspace->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validated();

        $prompt = data_get($validated, 'body');

        $userMessage = $post->aiMessages()->create([
            'user_id' => $request->user()->id,
            'role' => 'user',
            'content' => $prompt,
        ]);

        if ($request->hasFile('image')) {
            $media = $workspace->addMedia($request->file('image'), 'assets');

            $userMessage->update([
                'attachments' => [['id' => $media->id, 'path' => $media->path, 'url' => $media->url, 'type' => 'image', 'mime_type' => $media->mime_type]],
            ]);
        }

        $userMessage->load('user');

        $intent = $intentDetector->detect($prompt);

        if ($intent === Intent::Blocked) {
            $assistantMessage = $post->aiMessages()->create([
                'role' => 'assistant',
                'content' => __('assistant.content_blocked'),
                'metadata' => ['intent' => $intent->value, 'error' => true],
            ]);

            return response()->json([
                'user_message' => $userMessage,
                'assistant_message' => $assistantMessage,
            ], Response::HTTP_CREATED);
        }

        try {
            $imagesInThread = $post->aiMessages()
                ->where('role', 'assistant')
                ->get()
                ->sum(fn ($m) => collect($m->attachments ?? [])->where('type', 'image')->count());

            $videosInThread = $post->aiMessages()
                ->where('role', 'assistant')
                ->get()
                ->sum(fn ($m) => collect($m->attachments ?? [])->where('type', 'video')->count());

            $imageLimit = (int) Feature::for($workspace->account)->value(AiImagesLimit::class);
            $imageUsed = AiUsageLog::monthlyCount($workspace->account_id, UsageType::Image);
            $imageRemaining = max(0, $imageLimit - $imageUsed);

            $videoLimit = (int) Feature::for($workspace->account)->value(AiVideosLimit::class);
            $videoUsed = AiUsageLog::monthlyCount($workspace->account_id, UsageType::Video);
            $videoRemaining = max(0, $videoLimit - $videoUsed);

            $stateContext = sprintf(
                "[Session state — use this to track progress and respect quotas]\n".
                "- Images already generated in this conversation: %d\n".
                "- Videos already generated in this conversation: %d\n".
                "- Monthly quota remaining: %d images, %d videos\n",
                $imagesInThread,
                $videosInThread,
                $imageRemaining,
                $videoRemaining,
            );

            $promptWithState = "{$stateContext}\n{$prompt}";

            $collector->clear();

            $response = (new SocialMediaAssistant(
                workspace: $workspace,
                post: $post,
                userId: $request->user()->id,
            ))->prompt($promptWithState);

            $responseContent = $response->text;
            $attachments = $collector->all();

            $generatedIntent = $intent->value;
            foreach ($attachments as $attachment) {
                if (isset($attachment['type'])) {
                    $generatedIntent = $attachment['type'];
                    break;
                }
            }

            $assistantMessage = $post->aiMessages()->create([
                'role' => 'assistant',
                'content' => $responseContent,
                'attachments' => $attachments,
                'metadata' => ['intent' => $generatedIntent],
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
                'metadata' => ['intent' => $intent->value, 'error' => true],
            ]);

            return response()->json([
                'user_message' => $userMessage,
                'assistant_message' => $assistantMessage,
            ], Response::HTTP_CREATED);
        }
    }
}
