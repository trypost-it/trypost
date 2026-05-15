<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreUploadRequest;
use App\Models\Media;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class UploadController extends Controller
{
    public function store(StoreUploadRequest $request, string $token): JsonResponse
    {
        $expiresAt = (int) $request->query('expires');
        $ttl = max(60, $expiresAt - now()->timestamp + 60);

        if (! Cache::add("mcp_upload:{$token}", true, $ttl)) {
            abort(Response::HTTP_CONFLICT, 'Upload token already used.');
        }

        if (Media::where('upload_token', $token)->exists()) {
            abort(Response::HTTP_CONFLICT, 'Upload token already consumed.');
        }

        $workspace = Workspace::findOrFail((string) $request->query('ws'));

        $media = $workspace->addMedia($request->file('media'), 'assets');
        $media->upload_token = $token;
        $media->save();

        return response()->json([
            'upload_token' => $token,
            'media_id' => $media->id,
            'type' => $media->type,
            'mime_type' => $media->mime_type,
            'original_filename' => $media->original_filename,
        ], Response::HTTP_CREATED);
    }
}
