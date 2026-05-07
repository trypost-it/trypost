<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Mcp\Servers\postproServer;
use App\Mcp\Tools\Platform\ListContentTypesTool;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('list content types returns all platforms with constraints', function () {
    $response = postproServer::actingAs($this->user)
        ->tool(ListContentTypesTool::class, []);

    $response->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) {
            $json->has('platforms', fn (AssertableJson $platforms) => $platforms
                ->each(fn (AssertableJson $p) => $p
                    ->hasAll([
                        'platform',
                        'label',
                        'max_content_length',
                        'recommended_content_length',
                        'allowed_media_types',
                        'default_content_type',
                        'content_types',
                    ])
                )
            );
        });
});

test('list content types includes content types per platform', function () {
    $response = postproServer::actingAs($this->user)
        ->tool(ListContentTypesTool::class, []);

    $response->assertOk()
        ->assertSee(['linkedin', 'linkedin_post', 'x_post', 'instagram_feed', 'mastodon_post']);
});

