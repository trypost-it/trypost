<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Requests\App\Media\StoreChunkedMediaRequest;
use App\Http\Requests\App\Media\StoreMediaRequest;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostPlatform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function store(StoreMediaRequest $request): JsonResponse
    {
        $model = $this->resolveModel($request->input('model'), $request->input('model_id'));
        $this->authorizeModelOwnership($model, $request);
        $collection = $request->input('collection', 'default');

        $media = $model->addMedia(
            $request->file('media'),
            $collection
        );

        return response()->json([
            'id' => $media->id,
            'group_id' => $media->group_id,
            'url' => $media->url,
            'type' => $media->type->value,
            'original_filename' => $media->original_filename,
        ]);
    }

    public function storeChunked(StoreChunkedMediaRequest $request): JsonResponse
    {
        $tempFile = $this->chunkTempPath($request->chunkIdentifier());

        $this->appendChunk($tempFile, $request->getContent(), $request->isFirstChunk());

        if (! $request->isLastChunk()) {
            return response()->json([
                'done' => false,
                'progress' => $request->progress(),
            ]);
        }

        $model = $this->resolveModel($request->input('model'), $request->input('model_id'));
        $this->authorizeModelOwnership($model, $request);

        $media = $model->addMediaFromPath(
            $tempFile,
            $request->input('file_name'),
            $request->input('collection'),
        );

        @unlink($tempFile);

        return response()->json([
            'done' => true,
            'id' => $media->id,
            'group_id' => $media->group_id,
            'url' => $media->url,
            'type' => $media->type->value,
            'original_filename' => $media->original_filename,
        ]);
    }

    public function destroy(string $modelId, Media $media, Request $request): JsonResponse
    {
        if ($media->mediable_id !== $modelId) {
            abort(403);
        }

        $model = $media->mediable;
        $this->authorizeModelOwnership($model, $request);

        $media->delete();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'media' => 'required|array',
            'media.*.id' => 'required|exists:medias,id',
            'media.*.order' => 'required|integer|min:0',
        ]);

        foreach ($request->input('media') as $item) {
            Media::where('id', $item['id'])->update(['order' => $item['order']]);
        }

        return response()->json(['success' => true]);
    }

    public function duplicate(Media $media, Request $request): JsonResponse
    {
        $sourceModel = $media->mediable;
        $this->authorizeModelOwnership($sourceModel, $request);

        $targets = $request->input('targets', []);
        $duplicates = [];

        foreach ($targets as $target) {
            $model = $this->resolveModel($target['model'], $target['model_id']);
            $this->authorizeModelOwnership($model, $request);
            $collection = $target['collection'] ?? $media->collection;

            $duplicate = $model->media()->create([
                'group_id' => $media->group_id,
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
                'group_id' => $duplicate->group_id,
                'mediable_id' => $duplicate->mediable_id,
                'mediable_type' => $duplicate->mediable_type,
                'url' => $duplicate->url,
                'type' => $duplicate->type->value,
                'original_filename' => $duplicate->original_filename,
            ];
        }

        return response()->json($duplicates);
    }

    private function authorizeModelOwnership(Model $model, Request $request): void
    {
        $workspace = $request->user()->currentWorkspace;

        if ($model instanceof PostPlatform) {
            if ($model->post->workspace_id !== $workspace->id) {
                abort(403);
            }
        } elseif ($model instanceof Post) {
            if ($model->workspace_id !== $workspace->id) {
                abort(403);
            }
        }
    }

    private function resolveModel(string $alias, string $id): Model
    {
        $modelClass = Relation::getMorphedModel($alias) ?? $alias;

        return $modelClass::findOrFail($id);
    }

    private function chunkTempPath(string $identifier): string
    {
        return storage_path("app/private/chunks/{$identifier}");
    }

    private function appendChunk(string $path, string $content, bool $isFirst): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $content, $isFirst ? 0 : FILE_APPEND);
    }
}
