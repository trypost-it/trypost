<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\Ai\Intent;
use App\Enums\AiMessage\Status;
use App\Http\Requests\App\Assistant\StoreAssistantMessageRequest;
use App\Jobs\Ai\GenerateAssistantResponse;
use App\Models\Post;
use App\Services\Ai\IntentDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            ->orderBy('id')
            ->get();

        return response()->json(['messages' => $messages]);
    }

    public function store(
        StoreAssistantMessageRequest $request,
        Post $post,
        IntentDetector $intentDetector,
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
            'status' => Status::Completed,
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
                'status' => Status::Completed,
                'metadata' => ['intent' => $intent->value, 'error' => true],
            ]);

            return response()->json([
                'user_message' => $userMessage,
                'assistant_message' => $assistantMessage,
            ], Response::HTTP_CREATED);
        }

        $assistantMessage = $post->aiMessages()->create([
            'role' => 'assistant',
            'content' => '',
            'status' => Status::Pending,
            'metadata' => ['intent' => $intent->value],
        ]);

        GenerateAssistantResponse::dispatch(
            assistantMessage: $assistantMessage,
            prompt: $prompt,
            intent: $intent->value,
        );

        return response()->json([
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
        ], Response::HTTP_ACCEPTED);
    }
}
