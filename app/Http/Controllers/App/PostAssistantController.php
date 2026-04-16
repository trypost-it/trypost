<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\Ai\Intent;
use App\Enums\Ai\Orientation;
use App\Enums\Ai\UsageType;
use App\Features\AiImagesLimit;
use App\Features\AiVideosLimit;
use App\Http\Requests\App\Assistant\StoreAssistantMessageRequest;
use App\Models\AiMessage;
use App\Models\AiUsageLog;
use App\Models\Post;
use App\Services\Ai\AudioGenerationService;
use App\Services\Ai\Contracts\TextGenerationInterface;
use App\Services\Ai\ImageGenerationService;
use App\Services\Ai\IntentDetector;
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
        StoreAssistantMessageRequest $request,
        Post $post,
        IntentDetector $intentDetector,
        TextGenerationInterface $textService,
        ImageGenerationService $imageService,
        AudioGenerationService $audioService,
        VideoGenerationService $videoService,
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

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $media = $workspace->addMedia($request->file('image'), 'assets');
            $imageUrl = $media->url;

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
            $previousMessages = $post->aiMessages()
                ->whereIn('role', ['user', 'assistant'])
                ->where('id', '!=', $userMessage->id)
                ->oldest()
                ->limit(20)
                ->get();

            $imagesInThread = 0;
            $videosInThread = 0;

            $history = $previousMessages
                ->map(function (AiMessage $m) use (&$imagesInThread, &$videosInThread) {
                    $content = $m->content;

                    if ($m->role === 'assistant' && ! empty($m->attachments)) {
                        $attachmentTypes = collect($m->attachments)
                            ->groupBy('type')
                            ->map(fn ($group) => count($group));

                        $counts = [];
                        foreach ($attachmentTypes as $type => $count) {
                            $counts[] = "{$count} {$type}";
                            if ($type === 'image') {
                                $imagesInThread += $count;
                            } elseif ($type === 'video') {
                                $videosInThread += $count;
                            }
                        }

                        $content .= "\n\n[This assistant message attached: ".implode(', ', $counts).']';
                    }

                    return ['role' => $m->role, 'content' => $content];
                })
                ->all();

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

            $responseContent = $textService->generate($promptWithState, $history, $workspace, $imageUrl);

            $attachments = [];
            $generatedIntent = $intent->value;
            $limitReached = false;

            // Build rich context from conversation history for media generation
            $conversationContext = collect($history)
                ->map(fn (array $m) => "{$m['role']}: {$m['content']}")
                ->implode("\n\n");

            $buildMediaPrompt = function (string $captionText) use ($prompt, $conversationContext): string {
                $parts = [$prompt];

                if ($conversationContext) {
                    $parts[] = "Conversation context:\n{$conversationContext}";
                }

                if ($captionText) {
                    $parts[] = "Caption generated for this post:\n{$captionText}";
                }

                return implode("\n\n", $parts);
            };

            if (preg_match('/\[GENERATE_IMAGE:(vertical|horizontal)\]/', $responseContent, $matches)) {
                $orientation = Orientation::tryFrom(data_get($matches, 1, 'vertical')) ?? Orientation::Vertical;
                $limit = Feature::for($workspace->account)->value(AiImagesLimit::class);
                $used = AiUsageLog::monthlyCount($workspace->account_id, UsageType::Image);

                if ($used >= $limit) {
                    $responseContent = __('assistant.limit_reached_images');
                    $limitReached = true;
                } else {
                    $captionText = trim(preg_replace('/\[GENERATE_IMAGE:(vertical|horizontal)\]/', '', $responseContent));
                    $mediaPrompt = $buildMediaPrompt($captionText);
                    $result = $imageService->generate($mediaPrompt, $workspace, $request->user()->id, $post->id, $orientation);
                    $responseContent = $captionText ?: __('assistant.image_generated');
                    $attachments = [$result];
                    $generatedIntent = 'image';
                }
            } elseif (preg_match('/\[GENERATE_VIDEO:(vertical|horizontal)\]/', $responseContent, $matches)) {
                $orientation = Orientation::tryFrom(data_get($matches, 1, 'vertical')) ?? Orientation::Vertical;
                $limit = Feature::for($workspace->account)->value(AiVideosLimit::class);
                $used = AiUsageLog::monthlyCount($workspace->account_id, UsageType::Video);

                if ($used >= $limit) {
                    $responseContent = __('assistant.limit_reached_videos');
                    $limitReached = true;
                } else {
                    $captionText = trim(preg_replace('/\[GENERATE_VIDEO:(vertical|horizontal)\]/', '', $responseContent));
                    $mediaPrompt = $buildMediaPrompt($captionText);
                    $result = $videoService->generate($mediaPrompt, $workspace, $request->user()->id, $post->id, $orientation);
                    $responseContent = $captionText ?: __('assistant.video_generated');
                    $attachments = [$result];
                    $generatedIntent = 'video';
                }
            } elseif (str_contains($responseContent, '[GENERATE_AUDIO]')) {
                $result = $audioService->generate($prompt, $workspace, $request->user()->id, $post->id);
                $responseContent = __('assistant.audio_generated');
                $attachments = [$result];
                $generatedIntent = 'audio';
            }

            $assistantMessage = $post->aiMessages()->create([
                'role' => 'assistant',
                'content' => $responseContent,
                'attachments' => $attachments,
                'metadata' => array_filter(['intent' => $generatedIntent, 'limit_reached' => $limitReached ?: null]),
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
