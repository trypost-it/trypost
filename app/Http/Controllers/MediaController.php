<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMediaRequest;
use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Pion\Laravel\ChunkUpload\Handler\ContentRangeUploadHandler;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;

class MediaController extends Controller
{
    public function store(StoreMediaRequest $request): JsonResponse
    {
        $modelAlias = $request->input('model');
        $modelId = $request->input('model_id');
        $collection = $request->input('collection', 'default');

        $modelClass = Relation::getMorphedModel($modelAlias) ?? $modelAlias;
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

    public function storeChunked(Request $request): JsonResponse
    {
        $receiver = new FileReceiver(
            UploadedFile::fake()->createWithContent('file', $request->getContent()),
            $request,
            ContentRangeUploadHandler::class
        );

        if (! $receiver->isUploaded()) {
            return response()->json(['error' => 'File not uploaded'], 400);
        }

        $save = $receiver->receive();

        if ($save->isFinished()) {
            $file = $save->getFile();

            $modelAlias = $request->header('X-Model');
            $modelId = $request->header('X-Model-Id');
            $collection = $request->header('X-Collection', 'default');

            $modelClass = Relation::getMorphedModel($modelAlias) ?? $modelAlias;
            $model = $modelClass::findOrFail($modelId);

            $media = $model->addMediaFromPath(
                $file->getRealPath(),
                $request->header('X-File-Name', $file->getClientOriginalName()),
                $collection
            );

            // Clean up temp file
            unlink($file->getRealPath());

            return response()->json([
                'done' => true,
                'id' => $media->id,
                'url' => $media->url,
                'type' => $media->type->value,
                'original_filename' => $media->original_filename,
            ]);
        }

        $handler = $save->handler();

        return response()->json([
            'done' => false,
            'progress' => $handler->getPercentageDone(),
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
            $modelAlias = $target['model'];
            $modelId = $target['model_id'];
            $collection = $target['collection'] ?? $media->collection;

            $modelClass = Relation::getMorphedModel($modelAlias) ?? $modelAlias;
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
