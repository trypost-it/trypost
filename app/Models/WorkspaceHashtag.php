<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WorkspaceHashtagFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkspaceHashtag extends Model
{
    /** @use HasFactory<WorkspaceHashtagFactory> */
    use HasFactory, HasUuids, SoftDeletes;

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
