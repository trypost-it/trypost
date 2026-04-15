<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Models\Media;
use App\Services\UnsplashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AssetController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageAccounts', $workspace);

        $assets = $workspace->getMedia('assets')
            ->latest()
            ->paginate(config('app.pagination.default'));

        return Inertia::render('assets/Index', [
            'assets' => Inertia::scroll(fn () => $assets),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('manageAccounts', $workspace);

        $request->validate([
            'media' => ['required', 'file', 'max:1048576', 'mimetypes:image/jpeg,image/png,image/gif,image/webp,video/mp4'], // max 1GB in KB
        ]);

        $media = $workspace->addMedia($request->file('media'), 'assets');

        return response()->json([
            'id' => $media->id,
            'url' => $media->url,
            'type' => $media->type->value,
            'original_filename' => $media->original_filename,
            'size' => $media->size,
            'meta' => $media->meta,
            'created_at' => $media->created_at->toISOString(),
        ]);
    }

    public function storeChunked(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('manageAccounts', $workspace);

        $contentRange = $request->header('Content-Range');

        preg_match('/bytes (\d+)-(\d+)\/(\d+)/', $contentRange, $matches);

        if (empty($matches)) {
            abort(SymfonyResponse::HTTP_BAD_REQUEST, 'Invalid Content-Range header');
        }

        $rangeStart = (int) $matches[1];
        $rangeEnd = (int) $matches[2];
        $totalSize = (int) $matches[3];

        $fileName = $request->header('X-File-Name', 'upload');

        if (! preg_match('/\.(jpe?g|png|gif|webp|mp4)$/i', $fileName)) {
            abort(SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY, 'File type not supported.');
        }

        if ($totalSize > 1073741824) { // 1GB
            abort(SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY, 'File size exceeds the maximum allowed (1GB).');
        }
        $identifier = md5($request->user()->id.$fileName.$totalSize);
        $tempFile = storage_path("app/private/chunks/{$identifier}");

        $directory = dirname($tempFile);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($tempFile, $request->getContent(), $rangeStart === 0 ? 0 : FILE_APPEND);

        $isLastChunk = ($rangeEnd + 1) >= $totalSize;

        if (! $isLastChunk) {
            return response()->json([
                'done' => false,
                'progress' => (int) round(($rangeEnd + 1) / $totalSize * 100),
            ]);
        }

        $media = $workspace->addMediaFromPath($tempFile, $fileName, 'assets');

        @unlink($tempFile);

        return response()->json([
            'done' => true,
            'id' => $media->id,
            'url' => $media->url,
            'type' => $media->type->value,
            'original_filename' => $media->original_filename,
            'size' => $media->size,
            'meta' => $media->meta,
            'created_at' => $media->created_at->toISOString(),
        ]);
    }

    public function storeFromUrl(Request $request, UnsplashService $unsplash): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('manageAccounts', $workspace);

        $validated = $request->validate([
            'url' => ['required', 'url', 'regex:/^https:\/\/(images\.unsplash\.com|media[0-9]*\.giphy\.com)\//'],
            'filename' => ['required', 'string', 'max:255'],
            'download_location' => ['nullable', 'url', 'regex:/^https:\/\/api\.unsplash\.com\//'],
        ]);

        // Trigger Unsplash download tracking (required by API guidelines)
        if ($downloadLocation = data_get($validated, 'download_location')) {
            $unsplash->trackDownload($downloadLocation);
        }

        $response = Http::timeout(30)->get(data_get($validated, 'url'));

        if ($response->failed()) {
            abort(SymfonyResponse::HTTP_BAD_REQUEST, 'Failed to download image from URL');
        }

        $mimeType = $response->header('Content-Type', 'image/jpeg');
        $extension = match (true) {
            str_contains($mimeType, 'png') => 'png',
            str_contains($mimeType, 'gif') => 'gif',
            str_contains($mimeType, 'webp') => 'webp',
            default => 'jpg',
        };

        $filename = Str::uuid().'.'.$extension;
        $path = 'medias/'.$filename;

        Storage::put($path, $response->body());

        $meta = [];
        $tempFile = tempnam(sys_get_temp_dir(), 'unsplash');
        file_put_contents($tempFile, $response->body());
        $imageInfo = @getimagesize($tempFile);
        if ($imageInfo) {
            $meta['width'] = $imageInfo[0];
            $meta['height'] = $imageInfo[1];
        }
        @unlink($tempFile);

        $workspace->media()->create([
            'group_id' => Str::uuid()->toString(),
            'collection' => 'assets',
            'type' => 'image',
            'path' => $path,
            'original_filename' => data_get($validated, 'filename'),
            'mime_type' => $mimeType,
            'size' => strlen($response->body()),
            'order' => 0,
            'meta' => $meta,
        ]);

        session()->flash('flash.banner', __('assets.saved'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function destroy(Request $request, Media $media): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('manageAccounts', $workspace);

        if ($media->mediable_type !== $workspace->getMorphClass() || $media->mediable_id !== $workspace->id) {
            abort(SymfonyResponse::HTTP_FORBIDDEN);
        }

        $media->delete();

        return back();
    }
}
