<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AiMessageFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AiMessage extends Model
{
    /** @use HasFactory<AiMessageFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'post_id',
        'user_id',
        'role',
        'content',
        'attachments',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected $appends = ['content_html'];

    protected function contentHtml(): Attribute
    {
        return Attribute::get(fn () => $this->role === 'assistant' && $this->content
            ? Str::markdown($this->content)
            : null
        );
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
