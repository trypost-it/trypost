<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiToken extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'api_tokens';

    protected $fillable = [
        'workspace_id',
        'name',
        'token_lookup',
        'token_hash',
        'last_used_at',
        'expires_at',
    ];

    protected $hidden = [
        'token_lookup',
        'token_hash',
    ];

    protected $appends = [
        'status',
        'key_hint',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    protected function keyHint(): Attribute
    {
        return Attribute::make(
            get: fn () => 'tp_'.substr($this->token_lookup, 0, 8).'...',
        );
    }

    protected function status(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->expires_at === null) {
                    return 'active';
                }

                return now()->greaterThan($this->expires_at) ? 'expired' : 'active';
            }
        );
    }
}
