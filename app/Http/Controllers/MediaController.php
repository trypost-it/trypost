<?php

namespace App\Http\Controllers;

use App\Enums\MediaType;
use App\Http\Requests\StoreMediaRequest;
use App\Models\PostMedia;
use Illuminate\Http\JsonResponse;

class MediaController extends Controller
{
    public function store(StoreMediaRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $mimeType = $file->getMimeType();

        $type = $this->getMediaType($mimeType);

        $path = $file->store('media/'.now()->format('Y-m'));

        $media = PostMedia::create([
            'post_platform_id' => $request->input('post_platform_id'),
            'type' => $type,
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'order' => 0,
            'meta' => $this->getMediaMeta($file, $type),
        ]);

        return response()->json([
            'id' => $media->id,
            'url' => $media->url,
            'type' => $media->type->value,
            'original_filename' => $media->original_filename,
        ]);
    }

    public function destroy(PostMedia $media): JsonResponse
    {
        $media->delete();

        return response()->json(['success' => true]);
    }

    public function duplicate(PostMedia $media): JsonResponse
    {
        $postPlatformIds = request()->input('post_platform_ids', []);

        $duplicates = [];

        foreach ($postPlatformIds as $postPlatformId) {
            $duplicate = PostMedia::create([
                'post_platform_id' => $postPlatformId,
                'type' => $media->type,
                'path' => $media->path,
                'original_filename' => $media->original_filename,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'order' => $media->order,
                'meta' => $media->meta,
            ]);

            $duplicates[] = [
                'id' => $duplicate->id,
                'post_platform_id' => $duplicate->post_platform_id,
                'url' => $duplicate->url,
                'type' => $duplicate->type->value,
                'original_filename' => $duplicate->original_filename,
            ];
        }

        return response()->json($duplicates);
    }

    private function getMediaType(string $mimeType): MediaType
    {
        if (str_starts_with($mimeType, 'image/')) {
            return MediaType::Image;
        }

        if (str_starts_with($mimeType, 'video/')) {
            return MediaType::Video;
        }

        return MediaType::Document;
    }

    private function getMediaMeta($file, MediaType $type): array
    {
        $meta = [];

        if ($type === MediaType::Image) {
            $imageInfo = @getimagesize($file->getPathname());
            if ($imageInfo) {
                $meta['width'] = $imageInfo[0];
                $meta['height'] = $imageInfo[1];
            }
        }

        return $meta;
    }
}
