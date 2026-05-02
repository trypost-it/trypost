<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Notification\Channel;
use App\Enums\Notification\Type;
use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\SocialAccount\Status;
use App\Jobs\SendNotification;
use App\Mail\AccountDisconnected;
use Database\Factories\SocialAccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'platform',
        'platform_user_id',
        'username',
        'display_name',
        'avatar_url',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'meta',
        'status',
        'is_active',
        'error_message',
        'disconnected_at',
        'last_used_at',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
            'status' => Status::class,
            'is_active' => 'boolean',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'disconnected_at' => 'datetime',
            'last_used_at' => 'datetime',
            'scopes' => 'array',
            'meta' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function postPlatforms(): HasMany
    {
        return $this->hasMany(PostPlatform::class);
    }

    protected function isTokenExpired(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->token_expires_at && $this->token_expires_at->isPast(),
        );
    }

    protected function isTokenExpiringSoon(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->token_expires_at && $this->token_expires_at->isBefore(now()->addHour()),
        );
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Storage::url($value) : null,
        );
    }

    protected function profileUrl(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $username = $this->username;
                $platformUserId = $this->platform_user_id;

                return match ($this->platform) {
                    SocialPlatform::Facebook => ($username || $platformUserId)
                        ? 'https://facebook.com/'.($username ?: $platformUserId)
                        : null,
                    SocialPlatform::LinkedIn => $username ? "https://linkedin.com/in/{$username}" : null,
                    SocialPlatform::LinkedInPage => $username ? "https://linkedin.com/company/{$username}" : null,
                    SocialPlatform::X => $username ? "https://x.com/{$username}" : null,
                    SocialPlatform::TikTok => $username ? "https://tiktok.com/@{$username}" : null,
                    SocialPlatform::Instagram, SocialPlatform::InstagramFacebook => $username
                        ? "https://instagram.com/{$username}"
                        : null,
                    SocialPlatform::YouTube => $username ? "https://youtube.com/@{$username}" : null,
                    SocialPlatform::Threads => $username ? "https://threads.net/@{$username}" : null,
                    SocialPlatform::Bluesky => $username ? "https://bsky.app/profile/{$username}" : null,
                    SocialPlatform::Pinterest => $username ? "https://pinterest.com/{$username}" : null,
                    SocialPlatform::Mastodon => ($username && data_get($this->meta, 'instance'))
                        ? rtrim((string) data_get($this->meta, 'instance'), '/')."/@{$username}"
                        : null,
                    default => null,
                };
            },
        );
    }

    public function markAsDisconnected(string $errorMessage): void
    {
        $lock = Cache::lock("social_account_disconnect:{$this->id}", 10);

        if ($lock->get()) {
            try {
                $this->refresh();
                $wasConnected = $this->status !== Status::Disconnected;

                $this->update([
                    'status' => Status::Disconnected,
                    'error_message' => $errorMessage,
                    'disconnected_at' => now(),
                ]);

                if ($wasConnected && $this->workspace->owner) {
                    $platformName = $this->platform->label();
                    $accountName = $this->username ?? $this->display_name;

                    SendNotification::dispatch(
                        user: $this->workspace->owner,
                        workspaceId: $this->workspace_id,
                        type: Type::AccountDisconnected,
                        channel: Channel::Both,
                        title: "{$platformName} account disconnected",
                        body: "@{$accountName} needs to be reconnected",
                        data: ['social_account_id' => $this->id],
                        mailable: new AccountDisconnected($this),
                    );
                }
            } finally {
                $lock->release();
            }
        }
    }

    public function markAsTokenExpired(string $errorMessage): void
    {
        $this->update([
            'status' => Status::TokenExpired,
            'error_message' => $errorMessage,
            'disconnected_at' => $this->disconnected_at ?? now(),
        ]);
    }

    public function markAsConnected(): void
    {
        $this->update([
            'status' => Status::Connected,
            'error_message' => null,
            'disconnected_at' => null,
        ]);
    }

    public function isDisconnected(): bool
    {
        return $this->status === Status::Disconnected || $this->status === Status::TokenExpired;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('platform');
    }
}
