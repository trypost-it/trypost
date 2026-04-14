<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\HasMedia;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;

class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use Billable, HasFactory, HasMedia, HasUuids;

    public const SUBSCRIPTION_NAME = 'default';

    protected $fillable = [
        'user_id',
        'plan_id',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
        'name',
        'timezone',
    ];

    protected $appends = ['has_logo', 'logo_url'];

    public function getHasLogoAttribute(): bool
    {
        return $this->getFirstMedia('logo') !== null;
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('logo') ?: null;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(WorkspaceInvite::class);
    }

    public function hashtags(): HasMany
    {
        return $this->hasMany(WorkspaceHashtag::class);
    }

    public function labels(): HasMany
    {
        return $this->hasMany(WorkspaceLabel::class);
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function hasMember(User $user): bool
    {
        return $this->user_id === $user->id || $this->members()->where('user_id', $user->id)->exists();
    }

    public function hasConnectedPlatform(string $platform): bool
    {
        return $this->socialAccounts()->where('platform', $platform)->exists();
    }

    public function getSocialAccount(string $platform): ?SocialAccount
    {
        return $this->socialAccounts()->where('platform', $platform)->first();
    }

    /**
     * Check if the workspace has an active subscription.
     * In self-hosted mode, always returns true.
     */
    public function hasActiveSubscription(): bool
    {
        if (config('trypost.self_hosted')) {
            return true;
        }

        return $this->subscribed(self::SUBSCRIPTION_NAME);
    }

    /**
     * Check if the workspace is on a trial period.
     */
    public function isOnTrial(): bool
    {
        return $this->subscription(self::SUBSCRIPTION_NAME)?->onTrial() ?? false;
    }

    /**
     * Get the email address for Stripe.
     */
    public function stripeEmail(): string
    {
        return $this->owner?->email ?? '';
    }

    /**
     * Get the name for Stripe.
     */
    public function stripeName(): string
    {
        return $this->name;
    }
}
