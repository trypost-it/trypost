<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Billable, HasFactory, HasUuids, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
        ];
    }

    /**
     * Get workspaces owned by this user.
     */
    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    /**
     * Get workspaces where the user is a member (not owner).
     */
    public function memberWorkspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->using(WorkspaceMember::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the count of workspaces the user owns.
     */
    public function ownedWorkspacesCount(): int
    {
        return $this->workspaces()->count();
    }

    /**
     * Check if user has an active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscribed('default');
    }

    /**
     * Check if user can create more workspaces based on subscription.
     */
    public function canCreateWorkspace(): bool
    {
        // If no subscription, allow first workspace free (or require subscription)
        if (! $this->hasActiveSubscription()) {
            return $this->ownedWorkspacesCount() === 0;
        }

        $subscription = $this->subscription('default');

        return $subscription && $this->ownedWorkspacesCount() < $subscription->quantity;
    }

    /**
     * Increment workspace quantity on subscription.
     */
    public function incrementWorkspaceQuantity(): void
    {
        if ($this->hasActiveSubscription()) {
            $this->subscription('default')->incrementQuantity();
        }
    }

    /**
     * Decrement workspace quantity on subscription.
     */
    public function decrementWorkspaceQuantity(): void
    {
        if ($this->hasActiveSubscription()) {
            $subscription = $this->subscription('default');

            if ($subscription->quantity > 1) {
                $subscription->decrementQuantity();
            }
        }
    }

    /**
     * Sync subscription quantity with actual workspace count.
     */
    public function syncWorkspaceQuantity(): void
    {
        if ($this->hasActiveSubscription()) {
            $count = $this->ownedWorkspacesCount();

            if ($count > 0) {
                $this->subscription('default')->updateQuantity($count);
            }
        }
    }
}
