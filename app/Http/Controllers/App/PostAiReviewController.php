<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Ai\Agents\PostContentReviewer;
use App\Enums\Ai\UsageType;
use App\Http\Requests\App\Ai\ReviewPostContentRequest;
use App\Models\Post;
use App\Services\Ai\RecordAiUsage;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PostAiReviewController extends Controller
{
    public function review(ReviewPostContentRequest $request, Post $post): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if ($post->workspace_id !== $workspace->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $agent = new PostContentReviewer(workspace: $workspace);
        $result = $agent->prompt($request->string('content')->toString());

        RecordAiUsage::record(
            workspace: $workspace,
            type: UsageType::Text,
            provider: (string) config('ai.default'),
            userId: $request->user()->id,
            postId: $post->id,
            metadata: ['agent' => 'post_reviewer'],
        );

        return response()->json([
            'suggestions' => data_get($result, 'suggestions', []),
        ]);
    }
}
