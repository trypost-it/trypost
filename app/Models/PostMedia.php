<?php

namespace App\Models;

use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PostMedia extends Model
{
    /** @use HasFactory<\Database\Factories\PostMediaFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'post_platform_id',
        'type',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size',
        'order',
        'meta',
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

    public function postPlatform(): BelongsTo
    {
        return $this->belongsTo(PostPlatform::class);
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => Storage::disk($this->disk)->url($this->path),
        );
    }

    public function getTemporaryUrl(int $expirationMinutes = 60): string
    {
        return Storage::disk($this->disk)->temporaryUrl(
            $this->path,
            now()->addMinutes($expirationMinutes)
        );
    }

    public function delete(): bool
    {
        Storage::disk($this->disk)->delete($this->path);

        return parent::delete();
    }
}
