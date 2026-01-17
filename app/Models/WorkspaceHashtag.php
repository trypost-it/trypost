<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceHashtag extends Model
{
    /** @use HasFactory<\Database\Factories\WorkspaceHashtagFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'name',
        'hashtags',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
