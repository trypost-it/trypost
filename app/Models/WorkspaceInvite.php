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
            'accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkspaceInvite $invite) {
            if (empty($invite->token)) {
                $invite->token = Str::random(64);
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

    public function isPending(): bool
    {
        return $this->status === InviteStatus::Pending;
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

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }
}
