<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Hashtag\CreateHashtag;
use App\Actions\Hashtag\DeleteHashtag;
use App\Actions\Hashtag\UpdateHashtag;
use App\Http\Requests\Api\Hashtag\StoreHashtagRequest;
use App\Http\Requests\Api\Hashtag\UpdateHashtagRequest;
use App\Http\Resources\Api\HashtagResource;
use App\Models\WorkspaceHashtag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class HashtagController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $hashtags = $request->workspace->hashtags()->latest()->get();

        return HashtagResource::collection($hashtags);
    }

    public function store(StoreHashtagRequest $request): JsonResponse
    {
        $hashtag = CreateHashtag::execute($request->workspace, $request->validated());

        return (new HashtagResource($hashtag))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateHashtagRequest $request, WorkspaceHashtag $hashtag): HashtagResource
    {
        if ($hashtag->workspace_id !== $request->workspace->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $hashtag = UpdateHashtag::execute($hashtag, $request->validated());

        return new HashtagResource($hashtag);
    }

    public function destroy(Request $request, WorkspaceHashtag $hashtag): JsonResponse
    {
        if ($hashtag->workspace_id !== $request->workspace->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        DeleteHashtag::execute($hashtag);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
