<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Notification\Type as NotificationType;
use App\Enums\User\Persona;
use App\Enums\User\Setup;
use App\Models\Traits\HasMedia;
use App\Models\Traits\HasWorkspace;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasFactory, HasMedia, HasUuids, HasWorkspace, Notifiable;

    public const SUBSCRIPTION_NAME = 'default';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'setup',
        'persona',
        'current_workspace_id',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected $appends = [
        'has_photo',
        'photo_url',
    ];

    public function getHasPhotoAttribute(): bool
    {
        return $this->getFirstMedia('avatar') !== null;
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('avatar');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'setup' => Setup::class,
            'persona' => Persona::class,
        ];
    }

    /**
     * @return HasMany<Notification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function wantsEmailFor(NotificationType $type): bool
    {
        $preference = $this->notificationPreference;

        if (! $preference) {
            return true; // Default: all enabled
        }

        return match ($type) {
            NotificationType::PostPublished => $preference->post_published,
            NotificationType::PostFailed, NotificationType::PostPartiallyPublished => $preference->post_failed,
            NotificationType::AccountDisconnected => $preference->account_disconnected,
            default => true,
        };
    }

    /**
     * Check if user has an active subscription.
     * In self-hosted mode, always returns true (no subscription required).
     */
    public function hasActiveSubscription(): bool
    {
        if (config('trypost.self_hosted')) {
            return true;
        }

        return $this->subscribed(self::SUBSCRIPTION_NAME);
    }

    /**
     * Check if user has ever had a subscription (for trial eligibility).
     */
    public function hasEverSubscribed(): bool
    {
        return $this->subscriptions()->exists();
    }
}
