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

class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory, HasMedia, HasUuids;

    protected $fillable = [
        'user_id',
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
}
