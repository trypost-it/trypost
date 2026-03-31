<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasWorkspace
{
    /**
     * Get all workspaces the user belongs to (as owner or member).
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'user_workspace')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the user's current workspace.
     */
    public function currentWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    /**
     * Switch to a different workspace.
     */
    public function switchWorkspace(Workspace $workspace): void
    {
        $this->update(['current_workspace_id' => $workspace->id]);
    }

    /**
     * Check if user belongs to a workspace (owner or member).
     */
    public function belongsToWorkspace(Workspace $workspace): bool
    {
        return $this->workspaces()->where('workspaces.id', $workspace->id)->exists();
    }

    /**
     * Get the count of workspaces the user owns.
     */
    public function ownedWorkspacesCount(): int
    {
        return $this->workspaces()->wherePivot('role', Role::Owner->value)->count();
    }

    /**
     * Check if user can create more workspaces based on subscription.
     * In self-hosted mode, users can create unlimited workspaces.
     */
    public function canCreateWorkspace(): bool
    {
        if (config('trypost.self_hosted')) {
            return true;
        }

        if (! $this->hasActiveSubscription()) {
            return $this->ownedWorkspacesCount() === 0;
        }

        $subscription = $this->subscription(User::SUBSCRIPTION_NAME);

        return $subscription && $this->ownedWorkspacesCount() < $subscription->quantity;
    }

    /**
     * Increment workspace quantity on subscription.
     * Skipped in self-hosted mode (no Stripe subscription).
     */
    public function incrementWorkspaceQuantity(): void
    {
        if (config('trypost.self_hosted')) {
            return;
        }

        if ($this->hasActiveSubscription()) {
            $this->subscription(User::SUBSCRIPTION_NAME)->incrementQuantity();
        }
    }

    /**
     * Decrement workspace quantity on subscription.
     * Skipped in self-hosted mode (no Stripe subscription).
     */
    public function decrementWorkspaceQuantity(): void
    {
        if (config('trypost.self_hosted')) {
            return;
        }

        if ($this->hasActiveSubscription()) {
            $subscription = $this->subscription(User::SUBSCRIPTION_NAME);

            if ($subscription->quantity > 1) {
                $subscription->decrementQuantity();
            }
        }
    }

    /**
     * Sync subscription quantity with actual workspace count.
     * Skipped in self-hosted mode (no Stripe subscription).
     */
    public function syncWorkspaceQuantity(): void
    {
        if (config('trypost.self_hosted')) {
            return;
        }

        if ($this->hasActiveSubscription()) {
            $count = $this->ownedWorkspacesCount();

            if ($count > 0) {
                $this->subscription(User::SUBSCRIPTION_NAME)->updateQuantity($count);
            }
        }
    }
}
