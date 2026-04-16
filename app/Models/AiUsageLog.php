<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AiUsageLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    /** @use HasFactory<AiUsageLogFactory> */
    use HasFactory, HasUuids;

    protected $table = 'workspace_ai_usages';

    protected $fillable = [
        'account_id',
        'workspace_id',
        'user_id',
        'post_id',
        'type',
        'provider',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public static function monthlyCount(string $accountId, string $type): int
    {
        return static::where('account_id', $accountId)
            ->where('type', $type)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
    }
}
