<?php

namespace App\Models;

use App\Enums\User\Persona;
use App\Enums\User\Setup;
use App\Models\Traits\HasMedia;
use App\Models\Traits\HasWorkspace;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Billable, HasFactory, HasMedia, HasUuids, HasWorkspace, Notifiable, TwoFactorAuthenticatable;

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
        'language_id',
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

    protected $appends = ['avatar'];

    /**
     * @return array{url: string, media_id: string|null}
     */
    public function getAvatarAttribute(): array
    {
        $media = $this->getFirstMedia('avatar');

        return [
            'url' => $media?->url ?? $this->getFallbackAvatarUrl($this->name),
            'media_id' => $media?->id,
        ];
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
     * Get the user's language.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Check if user has an active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscribed('default');
    }

    /**
     * Check if user has ever had a subscription (for trial eligibility).
     */
    public function hasEverSubscribed(): bool
    {
        return $this->subscriptions()->exists();
    }
}
