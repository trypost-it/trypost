<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Events\PostCommentCreated;
use App\Models\Post;
use App\Models\PostComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PostCommentController extends Controller
{
    public function index(Request $request, Post $post): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if ($post->workspace_id !== $workspace->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $comments = $post->comments()
            ->whereNull('parent_id')
            ->with(['user', 'replies.user'])
            ->latest()
            ->paginate(config('app.pagination.default'));

        return response()->json($comments);
    }

    public function store(Request $request, Post $post): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if ($post->workspace_id !== $workspace->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'uuid', 'exists:post_comments,id'],
        ]);

        if (data_get($validated, 'parent_id')) {
            $parent = PostComment::find(data_get($validated, 'parent_id'));
            if ($parent && $parent->parent_id !== null) {
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Cannot reply to a reply.');
            }
        }

        $comment = $post->comments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => data_get($validated, 'parent_id'),
            'body' => data_get($validated, 'body'),
        ]);

        $comment->load('user');

        PostCommentCreated::dispatch($comment);

        return response()->json($comment, Response::HTTP_CREATED);
    }

    public function update(Request $request, Post $post, PostComment $comment): JsonResponse
    {
        if ($comment->user_id !== $request->user()->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $comment->update(['body' => data_get($validated, 'body')]);

        return response()->json($comment);
    }

    public function destroy(Request $request, Post $post, PostComment $comment): JsonResponse
    {
        if ($comment->user_id !== $request->user()->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $comment->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function react(Request $request, Post $post, PostComment $comment): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if ($post->workspace_id !== $workspace->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'emoji' => ['required', 'string', 'max:10'],
        ]);

        $comment->addReaction($request->user()->id, data_get($validated, 'emoji'));

        return response()->json($comment->fresh());
    }
}
