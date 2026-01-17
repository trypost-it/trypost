<?php

namespace App\Models;

use App\Enums\UserWorkspace\Role;
use App\Enums\WorkspaceInvite\Status as InviteStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class WorkspaceInvite extends Model
{
    /** @use HasFactory<\Database\Factories\WorkspaceInviteFactory> */
    use HasFactory, HasUuids, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'invited_by',
        'email',
        'token',
        'role',
        'status',
        'expires_at',
        'accepted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'status' => InviteStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkspaceInvite $invite) {
            if (empty($invite->token)) {
                $invite->token = Str::random(64);
            }
            if (empty($invite->expires_at)) {
                $invite->expires_at = now()->addDays(7);
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', InviteStatus::Pending);
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->pending()->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === InviteStatus::Pending;
    }

    public function isValid(): bool
    {
        return $this->isPending() && ! $this->isExpired();
    }

    public function accept(User $user): void
    {
        $this->workspace->members()->attach($user->id, [
            'role' => $this->role,
        ]);

        $this->update([
            'status' => InviteStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => InviteStatus::Cancelled]);
    }

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }
}
