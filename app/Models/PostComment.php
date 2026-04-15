<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PostCommentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostComment extends Model
{
    /** @use HasFactory<PostCommentFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'body',
        'reactions',
    ];

    protected function casts(): array
    {
        return [
            'reactions' => 'array',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }

    public function addReaction(string $userId, string $emoji): void
    {
        $reactions = $this->reactions ?? [];
        $reactions = array_filter($reactions, fn (array $r) => ! (data_get($r, 'user_id') === $userId && data_get($r, 'emoji') === $emoji));

        if (count($reactions) === count($this->reactions ?? [])) {
            $reactions[] = ['user_id' => $userId, 'emoji' => $emoji];
        }

        $this->update(['reactions' => array_values($reactions)]);
    }
}
