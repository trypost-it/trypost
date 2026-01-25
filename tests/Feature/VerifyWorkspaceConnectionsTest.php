<?php

use App\Enums\SocialAccount\Status;
use App\Exceptions\TokenExpiredException;
use App\Jobs\VerifyWorkspaceConnections;
use App\Mail\WorkspaceConnectionsDisconnected;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Social\ConnectionVerifier;
use Illuminate\Support\Facades\Mail;

test('job does nothing when workspace has no connected accounts', function () {
    Mail::fake();

    $workspace = Workspace::factory()->create();

    VerifyWorkspaceConnections::dispatch($workspace);

    Mail::assertNothingSent();
});

test('job does not send email when all connections are valid', function () {
    Mail::fake();

    $workspace = Workspace::factory()->create();
    SocialAccount::factory()->linkedin()->create(['workspace_id' => $workspace->id]);
    SocialAccount::factory()->x()->create(['workspace_id' => $workspace->id]);

    $verifier = mock(ConnectionVerifier::class);
    $verifier->shouldReceive('verify')->andReturn(true);

    app()->instance(ConnectionVerifier::class, $verifier);

    VerifyWorkspaceConnections::dispatch($workspace);

    Mail::assertNothingSent();
});

test('job marks account as disconnected and sends email when token is invalid', function () {
    Mail::fake();

    $workspace = Workspace::factory()->create();
    $account = SocialAccount::factory()->linkedin()->create(['workspace_id' => $workspace->id]);

    $verifier = mock(ConnectionVerifier::class);
    $verifier->shouldReceive('verify')
        ->andThrow(new TokenExpiredException('Token expired'));

    app()->instance(ConnectionVerifier::class, $verifier);

    VerifyWorkspaceConnections::dispatch($workspace);

    expect($account->fresh()->status)->toBe(Status::Disconnected);
    expect($account->fresh()->error_message)->toBe('Token expired');

    Mail::assertQueued(WorkspaceConnectionsDisconnected::class, function ($mail) use ($workspace) {
        return $mail->workspace->id === $workspace->id
            && $mail->disconnectedAccounts->count() === 1;
    });
});

test('job sends single email with all disconnected accounts', function () {
    Mail::fake();

    $workspace = Workspace::factory()->create();
    $account1 = SocialAccount::factory()->linkedin()->create(['workspace_id' => $workspace->id]);
    $account2 = SocialAccount::factory()->x()->create(['workspace_id' => $workspace->id]);

    $verifier = mock(ConnectionVerifier::class);
    $verifier->shouldReceive('verify')
        ->andThrow(new TokenExpiredException('Token expired'));

    app()->instance(ConnectionVerifier::class, $verifier);

    VerifyWorkspaceConnections::dispatch($workspace);

    expect($account1->fresh()->status)->toBe(Status::Disconnected);
    expect($account2->fresh()->status)->toBe(Status::Disconnected);

    Mail::assertQueued(WorkspaceConnectionsDisconnected::class, function ($mail) use ($workspace) {
        return $mail->workspace->id === $workspace->id
            && $mail->disconnectedAccounts->count() === 2;
    });

    Mail::assertQueuedCount(1);
});

test('job only includes failed accounts in email', function () {
    Mail::fake();

    $workspace = Workspace::factory()->create();
    $validAccount = SocialAccount::factory()->linkedin()->create(['workspace_id' => $workspace->id]);
    $invalidAccount = SocialAccount::factory()->x()->create(['workspace_id' => $workspace->id]);

    $verifier = mock(ConnectionVerifier::class);
    $verifier->shouldReceive('verify')
        ->with(\Mockery::on(fn ($acc) => $acc->id === $validAccount->id))
        ->andReturn(true);
    $verifier->shouldReceive('verify')
        ->with(\Mockery::on(fn ($acc) => $acc->id === $invalidAccount->id))
        ->andThrow(new TokenExpiredException('Token expired'));

    app()->instance(ConnectionVerifier::class, $verifier);

    VerifyWorkspaceConnections::dispatch($workspace);

    expect($validAccount->fresh()->status)->toBe(Status::Connected);
    expect($invalidAccount->fresh()->status)->toBe(Status::Disconnected);

    Mail::assertQueued(WorkspaceConnectionsDisconnected::class, function ($mail) use ($invalidAccount) {
        return $mail->disconnectedAccounts->count() === 1
            && $mail->disconnectedAccounts->first()->id === $invalidAccount->id;
    });
});

test('job skips already disconnected accounts', function () {
    Mail::fake();

    $workspace = Workspace::factory()->create();
    SocialAccount::factory()->linkedin()->disconnected()->create(['workspace_id' => $workspace->id]);

    $verifier = mock(ConnectionVerifier::class);
    $verifier->shouldNotReceive('verify');

    app()->instance(ConnectionVerifier::class, $verifier);

    VerifyWorkspaceConnections::dispatch($workspace);

    Mail::assertNothingSent();
});
