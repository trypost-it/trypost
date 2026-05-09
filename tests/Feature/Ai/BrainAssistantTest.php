<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Config;

it('can chat with the brain assistant', function () {
    $user = User::factory()->withPersonalWorkspace()->create();
    $workspace = $user->personalWorkspace();

    $this->actingAs($user);

    $response = $this->postJson(route('app.brain.chat'), [
        'message' => 'Hello Brain!',
        'context' => [
            'url' => 'http://postpro.test/dashboard',
            'page' => 'Dashboard',
        ],
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'message',
        'suggested_actions',
    ]);
});

it('requires authentication to chat with the brain', function () {
    $response = $this->postJson(route('app.brain.chat'), [
        'message' => 'Hello Brain!',
    ]);

    $response->assertStatus(401);
});
