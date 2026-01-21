<?php

namespace App\Models\Traits;

use App\Models\Media;
use App\Models\PostPlatform;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HasMedia
{
    /**
     * Media collections configuration.
     * 'single' = only one media per collection (replaces existing)
     * 'multiple' = unlimited media per collection
     */
    protected static array $mediaCollections = [
        Workspace::class => [
            'logo' => 'single',
        ],
        User::class => [
            'avatar' => 'single',
        ],
        PostPlatform::class => [
            'default' => 'multiple',
        ],
    ];

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('order');
    }

    public function getMedia(string $collection = 'default'): MorphMany
    {
        return $this->media()->where('collection', $collection);
    }

    public function getFirstMedia(string $collection = 'default'): ?Media
    {
        return $this->getMedia($collection)->first();
    }

    public function getFirstMediaUrl(string $collection = 'default', ?string $default = null): ?string
    {
        $media = $this->getFirstMedia($collection);

        return $media?->url ?? $default;
    }

    /**
     * Generate a DiceBear fallback URL using initials.
     */
    public function getFallbackAvatarUrl(string $seed): string
    {
        return 'https://api.dicebear.com/9.x/initials/svg?backgroundColor=777777&fontFamily=Verdana&fontSize=40&seed='.urlencode($seed);
    }

    /**
     * Add media to a collection.
     * If the collection is configured as 'single', it will clear existing media first.
     */
    public function addMedia(UploadedFile $file, string $collection = 'default', array $meta = [], ?string $groupId = null): Media
    {
        if ($this->isSingleMediaCollection($collection)) {
            $this->clearMediaCollection($collection);
        }

        $mimeType = $file->getMimeType();
        $type = $this->getMediaType($mimeType);
        $extension = $file->getClientOriginalExtension();

        $filename = Str::uuid().'.'.$extension;
        $path = 'medias/'.$filename;

        Storage::put($path, file_get_contents($file->getPathname()));

        return $this->media()->create([
            'group_id' => $groupId ?? Str::uuid()->toString(),
            'collection' => $collection,
            'type' => $type,
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'order' => 0,
            'meta' => array_merge($this->getMediaMeta($file, $type), $meta),
        ]);
    }

    /**
     * Add media from a file path (used for chunked uploads).
     */
    public function addMediaFromPath(string $filePath, string $originalFilename, string $collection = 'default', array $meta = [], ?string $groupId = null): Media
    {
        if ($this->isSingleMediaCollection($collection)) {
            $this->clearMediaCollection($collection);
        }

        $mimeType = mime_content_type($filePath);
        $type = $this->getMediaType($mimeType);
        $size = filesize($filePath);
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);

        $filename = Str::uuid().'.'.$extension;
        $storagePath = 'medias/'.$filename;

        Storage::put($storagePath, file_get_contents($filePath));

        $mediaMeta = [];
        if ($type === 'image') {
            $imageInfo = @getimagesize($filePath);
            if ($imageInfo) {
                $mediaMeta['width'] = $imageInfo[0];
                $mediaMeta['height'] = $imageInfo[1];
            }
        }

        return $this->media()->create([
            'group_id' => $groupId ?? Str::uuid()->toString(),
            'collection' => $collection,
            'type' => $type,
            'path' => $storagePath,
            'original_filename' => $originalFilename,
            'mime_type' => $mimeType,
            'size' => $size,
            'order' => 0,
            'meta' => array_merge($mediaMeta, $meta),
        ]);
    }

    public function clearMediaCollection(string $collection = 'default'): void
    {
        $this->getMedia($collection)->each(fn (Media $media) => $media->delete());
    }

    public function isSingleMediaCollection(string $collection): bool
    {
        $modelClass = static::class;
        $config = self::$mediaCollections[$modelClass][$collection] ?? 'multiple';

        return $config === 'single';
    }

    private function getMediaType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        return 'document';
    }

    private function getMediaMeta(UploadedFile $file, string $type): array
    {
        $meta = [];

        if ($type === 'image') {
            $imageInfo = @getimagesize($file->getPathname());
            if ($imageInfo) {
                $meta['width'] = $imageInfo[0];
                $meta['height'] = $imageInfo[1];
            }
        }

        return $meta;
    }
}
