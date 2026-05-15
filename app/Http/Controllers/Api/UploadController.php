<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreUploadRequest;
use App\Http\Resources\Api\MediaUploadResource;
use App\Models\Media;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class UploadController extends Controller
{
    private const CACHE_TTL_BUFFER_SECONDS = 60;

    public function store(StoreUploadRequest $request, string $token): JsonResponse
    {
        $expiresAt = (int) $request->query('expires');
        $ttl = max(
            self::CACHE_TTL_BUFFER_SECONDS,
            $expiresAt - now()->timestamp + self::CACHE_TTL_BUFFER_SECONDS,
        );

        if (! Cache::add("mcp_upload:{$token}", true, $ttl)) {
            abort(Response::HTTP_CONFLICT);
        }

        if (Media::where('upload_token', $token)->exists()) {
            abort(Response::HTTP_CONFLICT);
        }

        $workspace = Workspace::findOrFail((string) $request->query('workspace_id'));

        $media = DB::transaction(function () use ($workspace, $request, $token): Media {
            $media = $workspace->addMedia($request->file('media'), 'assets');
            $media->upload_token = $token;
            $media->save();

            return $media;
        });

        return MediaUploadResource::make($media)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
