<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserWorkspace\Role;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceInvite extends Model
{
    /** @use HasFactory<\Database\Factories\WorkspaceInviteFactory> */
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'role',
        'workspace_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function accept(User $user): void
    {
        $this->workspace->members()->attach($user->id, [
            'role' => $this->role->value,
        ]);

        $this->delete();
    }
}
