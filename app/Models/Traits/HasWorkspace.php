<?php

namespace App\Models\Traits;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasWorkspace
{
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
        return $this->belongsToMany(Workspace::class)
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
        return $this->workspaces()->where('id', $workspace->id)->exists()
            || $this->memberWorkspaces()->where('workspaces.id', $workspace->id)->exists();
    }

    /**
     * Get the count of workspaces the user owns.
     */
    public function ownedWorkspacesCount(): int
    {
        return $this->workspaces()->count();
    }

    /**
     * Check if user can create more workspaces based on subscription.
     */
    public function canCreateWorkspace(): bool
    {
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
