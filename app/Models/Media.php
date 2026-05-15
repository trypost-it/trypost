<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Media\Type as MediaType;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory, HasUuids;

    protected $table = 'medias';

    protected $appends = ['url'];

    protected $fillable = [
        'mediable_id',
        'mediable_type',
        'group_id',
        'collection',
        'type',
        'path',
        'original_filename',
        'mime_type',
        'size',
        'order',
        'meta',
        'upload_token',
    ];

    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'size' => 'integer',
            'order' => 'integer',
            'meta' => 'array',
        ];
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => Storage::url($this->path),
        );
    }

    public function isVideo(): bool
    {
        if ($this->mime_type) {
            return str_starts_with($this->mime_type, 'video/');
        }

        $extension = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));

        return in_array($extension, ['mp4', 'mov', 'avi', 'wmv', 'webm', 'mkv', 'm4v']);
    }

    public function isImage(): bool
    {
        if ($this->mime_type) {
            return str_starts_with($this->mime_type, 'image/');
        }

        $extension = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'heic', 'heif']);
    }

    public function getTemporaryUrl(int $expirationMinutes = 60): string
    {
        return Storage::temporaryUrl(
            $this->path,
            now()->addMinutes($expirationMinutes)
        );
    }

    public function delete(): bool
    {
        // Only delete the file if no other media records use the same path
        $otherMediaWithSamePath = static::where('path', $this->path)
            ->where('id', '!=', $this->id)
            ->exists();

        if (! $otherMediaWithSamePath) {
            Storage::delete($this->path);
        }

        return parent::delete();
    }
}
