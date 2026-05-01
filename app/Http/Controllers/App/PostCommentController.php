<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\PostComment\NotifyMentions;
use App\Events\PostCommentCreated;
use App\Http\Requests\App\PostComment\ReactPostCommentRequest;
use App\Http\Requests\App\PostComment\StorePostCommentRequest;
use App\Http\Requests\App\PostComment\UpdatePostCommentRequest;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Support\MentionParser;
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

        $mentionedIds = $comments->getCollection()
            ->flatMap(function ($comment) {
                $ids = MentionParser::extractUserIds($comment->body ?? '');
                foreach ($comment->replies as $reply) {
                    $ids = array_merge($ids, MentionParser::extractUserIds($reply->body ?? ''));
                }

                return $ids;
            })
            ->unique()
            ->values()
            ->all();

        $mentionedUsers = empty($mentionedIds)
            ? []
            : User::query()
                ->whereIn('id', $mentionedIds)
                ->get(['id', 'name'])
                ->mapWithKeys(fn ($u) => [$u->id => $u->name])
                ->all();

        return response()->json([
            ...$comments->toArray(),
            'mentioned_users' => $mentionedUsers,
        ]);
    }

    public function store(StorePostCommentRequest $request, Post $post): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if ($post->workspace_id !== $workspace->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validated();

        if (data_get($validated, 'parent_id')) {
            $parent = PostComment::where('id', data_get($validated, 'parent_id'))
                ->where('post_id', $post->id)
                ->first();

            if (! $parent) {
                abort(Response::HTTP_NOT_FOUND);
            }

            if ($parent->parent_id !== null) {
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Cannot reply to a reply.');
            }
        }

        $comment = $post->comments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => data_get($validated, 'parent_id'),
            'body' => data_get($validated, 'body'),
        ]);

        $comment->load('user');

        NotifyMentions::execute($comment);
        PostCommentCreated::dispatch($comment);

        return response()->json($comment, Response::HTTP_CREATED);
    }

    public function update(UpdatePostCommentRequest $request, Post $post, PostComment $comment): JsonResponse
    {
        if ($comment->post_id !== $post->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if ($comment->user_id !== $request->user()->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $workspace = $request->user()->currentWorkspace;
        if ($comment->post->workspace_id !== $workspace->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validated();

        $previousBody = $comment->body;
        $comment->update(['body' => data_get($validated, 'body')]);

        NotifyMentions::execute($comment, $previousBody);

        return response()->json($comment);
    }

    public function destroy(Request $request, Post $post, PostComment $comment): JsonResponse
    {
        if ($comment->post_id !== $post->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if ($comment->user_id !== $request->user()->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $workspace = $request->user()->currentWorkspace;
        if ($comment->post->workspace_id !== $workspace->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $comment->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function react(ReactPostCommentRequest $request, Post $post, PostComment $comment): JsonResponse
    {
        if ($comment->post_id !== $post->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $workspace = $request->user()->currentWorkspace;

        if ($post->workspace_id !== $workspace->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validated();

        $comment->addReaction($request->user()->id, data_get($validated, 'emoji'));

        return response()->json($comment->fresh());
    }
}
