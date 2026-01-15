<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    /** @use HasFactory<\Database\Factories\WorkspaceFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'name',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->using(WorkspaceMember::class)
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

    public function pendingInvites(): HasMany
    {
        return $this->invites()->pending();
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
}
