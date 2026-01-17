<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMediaRequest;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function store(StoreMediaRequest $request): JsonResponse
    {
        $modelClass = $request->input('model');
        $modelId = $request->input('model_id');
        $collection = $request->input('collection', 'default');

        $model = $modelClass::findOrFail($modelId);

        $media = $model->addMedia(
            $request->file('media'),
            $collection
        );

        return response()->json([
            'id' => $media->id,
            'url' => $media->url,
            'type' => $media->type->value,
            'original_filename' => $media->original_filename,
        ]);
    }

    public function destroy(string $modelId, Media $media): JsonResponse
    {
        if ($media->mediable_id !== $modelId) {
            abort(403);
        }

        $media->delete();

        return response()->json(['success' => true]);
    }

    public function duplicate(Media $media, Request $request): JsonResponse
    {
        $targets = $request->input('targets', []);

        $duplicates = [];

        foreach ($targets as $target) {
            $modelClass = $target['model'];
            $modelId = $target['model_id'];
            $collection = $target['collection'] ?? $media->collection;

            $model = $modelClass::findOrFail($modelId);

            $duplicate = $model->media()->create([
                'collection' => $collection,
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
                'mediable_id' => $duplicate->mediable_id,
                'mediable_type' => $duplicate->mediable_type,
                'url' => $duplicate->url,
                'type' => $duplicate->type->value,
                'original_filename' => $duplicate->original_filename,
            ];
        }

        return response()->json($duplicates);
    }
}
