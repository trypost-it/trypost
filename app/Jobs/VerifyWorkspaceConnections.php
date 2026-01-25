<?php

namespace App\Jobs;

use App\Enums\SocialAccount\Status;
use App\Exceptions\TokenExpiredException;
use App\Mail\WorkspaceConnectionsDisconnected;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Social\ConnectionVerifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class VerifyWorkspaceConnections implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public Workspace $workspace) {}

    public function handle(ConnectionVerifier $verifier): void
    {
        $connectedAccounts = $this->workspace->socialAccounts()
            ->where('status', Status::Connected)
            ->get();

        if ($connectedAccounts->isEmpty()) {
            return;
        }

        Log::info('Verifying workspace connections', [
            'workspace_id' => $this->workspace->id,
            'workspace_name' => $this->workspace->name,
            'account_count' => $connectedAccounts->count(),
        ]);

        $disconnectedAccounts = collect();

        foreach ($connectedAccounts as $account) {
            if ($this->verifyAccount($verifier, $account)) {
                continue;
            }

            $disconnectedAccounts->push($account);
        }

        if ($disconnectedAccounts->isNotEmpty()) {
            $this->notifyOwner($disconnectedAccounts);
        }
    }

    private function verifyAccount(ConnectionVerifier $verifier, SocialAccount $account): bool
    {
        try {
            $verifier->verify($account);

            Log::info('Social account connection verified', [
                'account_id' => $account->id,
                'platform' => $account->platform->value,
            ]);

            return true;
        } catch (TokenExpiredException $e) {
            Log::warning('Social account connection is invalid', [
                'account_id' => $account->id,
                'platform' => $account->platform->value,
                'error' => $e->getMessage(),
            ]);

            $account->update([
                'status' => Status::Disconnected,
                'error_message' => $e->getMessage(),
                'disconnected_at' => now(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to verify social account connection', [
                'account_id' => $account->id,
                'platform' => $account->platform->value,
                'error' => $e->getMessage(),
            ]);

            return true;
        }
    }

    /**
     * @param  Collection<int, SocialAccount>  $disconnectedAccounts
     */
    private function notifyOwner(Collection $disconnectedAccounts): void
    {
        Log::info('Sending workspace disconnection notification', [
            'workspace_id' => $this->workspace->id,
            'disconnected_count' => $disconnectedAccounts->count(),
        ]);

        Mail::to($this->workspace->owner)
            ->send(new WorkspaceConnectionsDisconnected($this->workspace, $disconnectedAccounts));
    }
}
