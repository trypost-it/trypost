<?php

declare(strict_types=1);

namespace App\Models;

use App\DataTransferObjects\MediaItem;
use App\Enums\Post\Status as PostStatus;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'content',
        'media',
        'status',
        'scheduled_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'media' => 'array',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Get media items as a collection of MediaItem DTOs.
     *
     * @return Collection<int, MediaItem>
     */
    protected function mediaItems(): Attribute
    {
        return Attribute::make(
            get: fn () => collect($this->media ?? [])->map(fn (array $item) => MediaItem::fromArray($item)),
        );
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function postPlatforms(): HasMany
    {
        return $this->hasMany(PostPlatform::class)->orderBy('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(WorkspaceLabel::class);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Scheduled);
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->scheduled()->where('scheduled_at', '<=', now());
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Draft);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Published);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Failed);
    }

    public function markAsPublishing(): void
    {
        $this->update(['status' => PostStatus::Publishing]);
    }

    public function markAsPublished(): void
    {
        $this->update([
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function markAsPartiallyPublished(): void
    {
        $this->update([
            'status' => PostStatus::PartiallyPublished,
            'published_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => PostStatus::Failed]);
    }
}
