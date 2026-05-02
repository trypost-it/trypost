<?php

declare(strict_types=1);

use App\Enums\Media\Type as MediaType;
use App\Enums\PostPlatform\ContentType;
use App\Rules\ContentTypeCompatibleWithMedia;

function runMediaRule(string $contentType, array $media): array
{
    $errors = [];
    $rule = (new ContentTypeCompatibleWithMedia)->setData(['media' => $media]);
    $rule->validate('platforms.0.content_type', $contentType, function (string $message) use (&$errors): void {
        $errors[] = $message;
    });

    return $errors;
}

test('passes when content type does not require media and none provided', function () {
    expect(runMediaRule(ContentType::LinkedInPost->value, []))->toBe([]);
    expect(runMediaRule(ContentType::FacebookPost->value, []))->toBe([]);
    expect(runMediaRule(ContentType::XPost->value, []))->toBe([]);
});

test('fails when content type requires media and none provided', function () {
    $errors = runMediaRule(ContentType::InstagramReel->value, []);

    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain('requires at least one media file');
});

test('fails when content type does not support images and an image is present', function () {
    $media = [['type' => MediaType::Image->value, 'mime_type' => 'image/jpeg']];

    $errors = runMediaRule(ContentType::TikTokVideo->value, $media);

    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain('does not support images');
});

test('fails when content type does not support video and a video is present', function () {
    $media = [['type' => MediaType::Video->value, 'mime_type' => 'video/mp4']];

    $errors = runMediaRule(ContentType::PinterestPin->value, $media);

    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain('does not support videos');
});

test('youtube short rejects images', function () {
    $media = [['type' => MediaType::Image->value, 'mime_type' => 'image/jpeg']];

    $errors = runMediaRule(ContentType::YouTubeShort->value, $media);

    expect($errors[0])->toContain('does not support images');
});

test('passes when image-only content type receives an image', function () {
    $media = [['type' => MediaType::Image->value, 'mime_type' => 'image/png']];

    expect(runMediaRule(ContentType::PinterestPin->value, $media))->toBe([]);
});

test('passes when video-only content type receives a video', function () {
    $media = [['type' => MediaType::Video->value, 'mime_type' => 'video/mp4']];

    expect(runMediaRule(ContentType::TikTokVideo->value, $media))->toBe([]);
    expect(runMediaRule(ContentType::YouTubeShort->value, $media))->toBe([]);
    expect(runMediaRule(ContentType::InstagramReel->value, $media))->toBe([]);
});

test('detects media type from mime when type field is missing', function () {
    $media = [['mime_type' => 'image/jpeg']];

    $errors = runMediaRule(ContentType::TikTokVideo->value, $media);

    expect($errors)->toHaveCount(1);
});

test('does nothing for invalid content type values', function () {
    expect(runMediaRule('not_a_real_content_type', []))->toBe([]);
});
