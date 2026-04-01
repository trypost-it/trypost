<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostPlatform\ContentType;
use App\Enums\PostPlatform\Status;
use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Models\Traits\HasMedia;
use Database\Factories\PostPlatformFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostPlatform extends Model
{
    /** @use HasFactory<PostPlatformFactory> */
    use HasFactory, HasMedia, HasUuids;

    protected $fillable = [
        'post_id',
        'social_account_id',
        'enabled',
        'platform',
        'content',
        'content_type',
        'status',
        'platform_post_id',
        'platform_url',
        'error_message',
        'error_context',
        'published_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'platform' => SocialPlatform::class,
            'content_type' => ContentType::class,
            'status' => Status::class,
            'published_at' => 'datetime',
            'meta' => 'array',
            'error_context' => 'array',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function markAsPublishing(): void
    {
        $this->update(['status' => Status::Publishing]);
    }

    public function markAsPublished(string $platformPostId, ?string $platformUrl = null): void
    {
        $this->update([
            'status' => Status::Published,
            'platform_post_id' => $platformPostId,
            'platform_url' => $platformUrl,
            'published_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage, ?array $errorContext = null): void
    {
        $this->update([
            'status' => Status::Failed,
            'error_message' => $errorMessage,
            'error_context' => $errorContext,
        ]);
    }
}
