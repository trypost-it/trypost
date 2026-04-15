<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;

class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use Billable, HasFactory, HasUuids;

    public const SUBSCRIPTION_NAME = 'default';

    protected $fillable = [
        'owner_id',
        'name',
        'billing_email',
        'plan_id',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(Invite::class);
    }

    public function hasActiveSubscription(): bool
    {
        if (config('trypost.self_hosted')) {
            return true;
        }

        return $this->subscribed(self::SUBSCRIPTION_NAME);
    }

    public function isOnTrial(): bool
    {
        return $this->subscription(self::SUBSCRIPTION_NAME)?->onTrial() ?? false;
    }

    public function stripeEmail(): string
    {
        return $this->billing_email ?? $this->owner?->email ?? '';
    }

    public function stripeName(): string
    {
        return $this->name;
    }
}
