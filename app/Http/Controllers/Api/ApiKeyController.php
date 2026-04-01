<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\ApiKey\CreateApiKey;
use App\Actions\ApiKey\DeleteApiKey;
use App\Http\Requests\Api\ApiKey\StoreApiKeyRequest;
use App\Http\Resources\Api\ApiKeyResource;
use App\Models\ApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $apiKeys = $request->workspace->apiTokens()->latest()->get();

        return ApiKeyResource::collection($apiKeys);
    }

    public function store(StoreApiKeyRequest $request): JsonResponse
    {
        $result = CreateApiKey::execute($request->workspace, $request->validated());

        return response()->json([
            'token' => new ApiKeyResource(data_get($result, 'token')),
            'plain_token' => data_get($result, 'plain_token'),
        ], Response::HTTP_CREATED);
    }

    public function destroy(Request $request, ApiToken $apiToken): JsonResponse
    {
        if ($apiToken->workspace_id !== $request->workspace->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        DeleteApiKey::execute($apiToken);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
