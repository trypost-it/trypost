<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ApiKey\StoreApiKeyRequest;
use App\Http\Resources\Api\ApiKeyResource;
use App\Models\AccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tokens = AccessToken::where('user_id', $request->user()->id)
            ->where('workspace_id', $request->user()->currentWorkspace->id)
            ->where('revoked', false)
            ->latest()
            ->get();

        return ApiKeyResource::collection($tokens);
    }

    public function store(StoreApiKeyRequest $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $validated = $request->validated();

        $result = $request->user()->createToken($validated['name']);

        $token = AccessToken::find($result->token->id);
        $token->forceFill([
            'workspace_id' => $workspace->id,
            'expires_at' => $validated['expires_at'] ?? null,
        ])->saveQuietly();

        return response()->json([
            'token' => new ApiKeyResource($token->refresh()),
            'plain_token' => $result->accessToken,
        ], Response::HTTP_CREATED);
    }

    public function destroy(Request $request, string $tokenId): JsonResponse
    {
        $token = AccessToken::where('id', $tokenId)
            ->where('user_id', $request->user()->id)
            ->where('workspace_id', $request->user()->currentWorkspace->id)
            ->first();

        if (! $token) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $token->forceFill(['revoked' => true])->saveQuietly();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
