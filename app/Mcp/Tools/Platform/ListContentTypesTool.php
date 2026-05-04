<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Platform;

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the valid content_types per social platform plus their constraints (max content length, recommended length, max media count, whether media is required, default content_type). Use before create-post-tool / update-post-tool to know which content_type to set.')]
class ListContentTypesTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $platforms = [];

        foreach (Platform::cases() as $platform) {
            $contentTypes = array_map(
                fn (ContentType $type) => [
                    'value' => $type->value,
                    'label' => $type->label(),
                    'description' => $type->description(),
                    'max_media_count' => $type->maxMediaCount(),
                    'requires_media' => $type->requiresMedia(),
                ],
                array_values(ContentType::forPlatform($platform)),
            );

            $platforms[] = [
                'platform' => $platform->value,
                'label' => $platform->label(),
                'max_content_length' => $platform->maxContentLength(),
                'recommended_content_length' => $platform->recommendedAiContentLength(),
                'allowed_media_types' => array_map(
                    fn ($type) => $type->value,
                    $platform->allowedMediaTypes(),
                ),
                'default_content_type' => ContentType::defaultFor($platform)->value,
                'content_types' => $contentTypes,
            ];
        }

        return Response::structured(['platforms' => $platforms]);
    }
}
