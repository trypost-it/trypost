<?php

declare(strict_types=1);

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Rules\ContentTypeMatchesPostPlatform;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function runMatchesPostPlatformRule(string $contentType, ?string $postPlatformId): array
{
    $errors = [];
    $rule = (new ContentTypeMatchesPostPlatform)->setData([
        'platforms' => [
            ['id' => $postPlatformId, 'content_type' => $contentType],
        ],
    ]);

    $rule->validate('platforms.0.content_type', $contentType, function (string $message) use (&$errors): void {
        $errors[] = $message;
    });

    return $errors;
}

test('passes when content_type matches the post_platform social account', function () {
    $workspace = Workspace::factory()->create();
    $linkedin = SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::LinkedIn,
    ]);
    $post = Post::factory()->create(['workspace_id' => $workspace->id]);
    $postPlatform = PostPlatform::factory()->linkedin()->create([
        'post_id' => $post->id,
        'social_account_id' => $linkedin->id,
    ]);

    expect(runMatchesPostPlatformRule(ContentType::LinkedInPost->value, $postPlatform->id))->toBe([]);
    expect(runMatchesPostPlatformRule(ContentType::LinkedInCarousel->value, $postPlatform->id))->toBe([]);
});

test('fails when content_type belongs to a different platform than the post_platform', function () {
    $workspace = Workspace::factory()->create();
    $linkedin = SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::LinkedIn,
    ]);
    $post = Post::factory()->create(['workspace_id' => $workspace->id]);
    $postPlatform = PostPlatform::factory()->linkedin()->create([
        'post_id' => $post->id,
        'social_account_id' => $linkedin->id,
    ]);

    $errors = runMatchesPostPlatformRule(ContentType::XPost->value, $postPlatform->id);

    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain('not compatible');
});

test('skips validation when platform id is missing', function () {
    expect(runMatchesPostPlatformRule(ContentType::XPost->value, null))->toBe([]);
});

test('skips validation when post_platform does not exist', function () {
    // Valid-format UUID that does not exist in the database. The rule
    // intentionally no-ops so the missing-resource error is reported by
    // the surrounding Rule::exists check rather than this rule.
    expect(runMatchesPostPlatformRule(ContentType::XPost->value, '00000000-0000-0000-0000-000000000000'))->toBe([]);
});
