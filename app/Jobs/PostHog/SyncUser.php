<?php

declare(strict_types=1);

namespace App\Jobs\PostHog;

use App\Models\User;
use App\Services\PostHogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public string $userId)
    {
        $this->onQueue('posthog');
    }

    public function handle(PostHogService $postHog): void
    {
        if (! PostHogService::isEnabled()) {
            return;
        }

        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        $postHog->identify((string) $user->id, [
            '$email' => $user->email,
            '$name' => $user->name,
            '$set_once' => ['signed_up_at' => $user->created_at?->toIso8601String()],
        ]);

        if ($user->account_id) {
            SyncAccountUsage::dispatch(
                (string) $user->account_id,
                $user->current_workspace_id ? (string) $user->current_workspace_id : null,
            );
        }
    }
}
