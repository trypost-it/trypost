<?php

namespace App\Models\Traits;

use App\Models\Media;
use App\Models\PostPlatform;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;

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
    public function addMedia(UploadedFile $file, string $collection = 'default', array $meta = []): Media
    {
        if ($this->isSingleMediaCollection($collection)) {
            $this->clearMediaCollection($collection);
        }

        $mimeType = $file->getMimeType();
        $type = $this->getMediaType($mimeType);

        $path = $file->store('media/'.now()->format('Y-m'));

        return $this->media()->create([
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
