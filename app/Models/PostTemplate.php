<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PostTemplateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostTemplate extends Model
{
    /** @use HasFactory<PostTemplateFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'description',
        'category',
        'platform',
        'content',
        'slides',
        'image_count',
        'image_keywords',
    ];

    protected function casts(): array
    {
        return [
            'slides' => 'array',
            'image_keywords' => 'array',
            'image_count' => 'integer',
        ];
    }
}
