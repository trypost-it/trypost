<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Http\Middleware\Mcp\AuthenticateMcpToken;
use App\Models\Account;
use App\Models\ApiToken;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * @return array{token: ApiToken, plain_token: string, workspace: Workspace, user: User}
 */
function createMcpToken(array $overrides = []): array
{
    $plainToken = 'tp_'.Str::random(48);

    $user = data_get($overrides, 'user') ?? User::factory()->create();
    $workspace = data_get($overrides, 'workspace') ?? Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->syncWithoutDetaching([$user->id => ['role' => Role::Member->value]]);

    $apiToken = ApiToken::factory()->create([
        'workspace_id' => $workspace->id,
        'token_lookup' => substr($plainToken, 3, 16),
        'token_hash' => Hash::make($plainToken),
        ...collect($overrides)->except(['user', 'workspace'])->toArray(),
    ]);

    return [
        'token' => $apiToken,
        'plain_token' => $plainToken,
        'workspace' => $workspace,
        'user' => $user,
    ];
}

function callMiddleware(string $bearerToken = ''): JsonResponse|Response
{
    $request = Request::create('/mcp/trypost', 'GET');
    if ($bearerToken) {
        $request->headers->set('Authorization', "Bearer {$bearerToken}");
    }

    $middleware = new AuthenticateMcpToken;

    return $middleware->handle($request, fn () => response()->json(['ok' => true]));
}

test('returns 401 without token', function () {
    $response = callMiddleware();

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
    expect(json_decode($response->getContent(), true))->toMatchArray(['message' => 'Missing API key.']);
});

test('returns 401 with invalid token format', function () {
    $response = callMiddleware('invalid-token');

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
    expect(json_decode($response->getContent(), true))->toMatchArray(['message' => 'Invalid API key.']);
});

test('returns 401 with token that does not start with tp_', function () {
    $response = callMiddleware('xx_'.Str::random(48));

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
});

test('returns 401 with wrong token length', function () {
    $response = callMiddleware('tp_short');

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
});

test('returns 401 with wrong token', function () {
    createMcpToken();

    $response = callMiddleware('tp_'.Str::random(48));

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
    expect(json_decode($response->getContent(), true))->toMatchArray(['message' => 'Invalid API key.']);
});

test('returns 401 with expired token', function () {
    $result = createMcpToken();
    $result['token']->update(['expires_at' => now()->subDay()]);

    $response = callMiddleware($result['plain_token']);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
    expect(json_decode($response->getContent(), true))->toMatchArray(['message' => 'API key has expired.']);
});

test('authenticates with valid token', function () {
    $result = createMcpToken();

    $response = callMiddleware($result['plain_token']);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
    expect(Auth::id())->toBe($result['user']->id);
});

test('sets current workspace on authenticated user', function () {
    $result = createMcpToken();

    callMiddleware($result['plain_token']);

    expect(Auth::user()->current_workspace_id)->toBe($result['workspace']->id);
});

test('updates last_used_at on successful auth', function () {
    $this->freezeTime();
    $result = createMcpToken();

    expect($result['token']->last_used_at)->toBeNull();

    callMiddleware($result['plain_token']);

    expect($result['token']->fresh()->last_used_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

test('returns 402 when owner has no subscription', function () {
    config(['trypost.self_hosted' => false]);
    $result = createMcpToken();

    $response = callMiddleware($result['plain_token']);

    expect($response->getStatusCode())->toBe(Response::HTTP_PAYMENT_REQUIRED);
    expect(json_decode($response->getContent(), true))->toMatchArray(['message' => 'Active subscription required.']);
});

test('allows access when owner has active subscription', function () {
    config(['trypost.self_hosted' => false]);
    $result = createMcpToken();

    $result['workspace']->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test',
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);

    $response = callMiddleware($result['plain_token']);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
});

test('allows access when owner is on trial', function () {
    config(['trypost.self_hosted' => false]);
    $result = createMcpToken();

    $result['workspace']->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_trial',
        'stripe_status' => 'trialing',
        'stripe_price' => 'price_123',
        'trial_ends_at' => now()->addDays(7),
    ]);

    $response = callMiddleware($result['plain_token']);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
});

test('skips subscription check in self-hosted mode', function () {
    config(['trypost.self_hosted' => true]);
    $result = createMcpToken();

    $response = callMiddleware($result['plain_token']);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
});
