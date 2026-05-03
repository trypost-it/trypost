<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Plan\Slug;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'slug',
        'name',
        'stripe_monthly_price_id',
        'stripe_yearly_price_id',
        'monthly_price',
        'yearly_price',
        'social_account_limit',
        'member_limit',
        'workspace_limit',
        'ai_images_limit',
        'data_retention_days',
        'sort',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'slug' => Slug::class,
            'is_archived' => 'boolean',
            'monthly_price' => 'integer',
            'yearly_price' => 'integer',
            'social_account_limit' => 'integer',
            'member_limit' => 'integer',
            'workspace_limit' => 'integer',
            'ai_images_limit' => 'integer',
            'data_retention_days' => 'integer',
            'sort' => 'integer',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    public function formattedMonthlyPrice(): string
    {
        return '$'.number_format($this->monthly_price / 100, 0);
    }

    public function formattedYearlyPrice(): string
    {
        return '$'.number_format($this->yearly_price / 100, 0);
    }
}
