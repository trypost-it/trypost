<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Status;
use App\Exceptions\TokenExpiredException;
use App\Jobs\RefreshSocialToken;
use App\Jobs\SendNotification;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\ConnectionVerifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
    $this->account = SocialAccount::factory()->x()->create([
        'workspace_id' => $this->workspace->id,
        'status' => Status::Connected,
        'username' => 'testuser',
    ]);
});

test('refresh job calls refreshToken (not verify) on the verifier', function () {
    $verifier = mock(ConnectionVerifier::class);
    $verifier->shouldReceive('refreshToken')->once()->with(
        Mockery::on(fn ($account) => $account->id === $this->account->id)
    );
    $verifier->shouldNotReceive('verify');
    app()->instance(ConnectionVerifier::class, $verifier);

    (new RefreshSocialToken($this->account))->handle($verifier);
});

test('refresh job marks account as TokenExpired when refresh_token is rejected', function () {
    Queue::fake();

    $verifier = mock(ConnectionVerifier::class);
    $verifier->shouldReceive('refreshToken')->once()->andThrow(
        new TokenExpiredException('refresh_token revoked')
    );
    app()->instance(ConnectionVerifier::class, $verifier);

    (new RefreshSocialToken($this->account))->handle($verifier);

    expect($this->account->fresh()->status)->toBe(Status::TokenExpired);
    expect($this->account->fresh()->error_message)->toBe('refresh_token revoked');

    // Notification dispatched because account transitioned from Connected.
    Queue::assertPushed(SendNotification::class);
});

test('refresh job logs warning on non-token errors and leaves status alone', function () {
    Log::shouldReceive('warning')->once()->withArgs(function ($message, $context) {
        return $message === 'Proactive token refresh failed'
            && $context['account_id'] === $this->account->id
            && $context['error'] === 'network blip';
    });

    $verifier = mock(ConnectionVerifier::class);
    $verifier->shouldReceive('refreshToken')->once()->andThrow(new RuntimeException('network blip'));
    app()->instance(ConnectionVerifier::class, $verifier);

    (new RefreshSocialToken($this->account))->handle($verifier);

    expect($this->account->fresh()->status)->toBe(Status::Connected);
});
