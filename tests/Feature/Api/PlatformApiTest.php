<?php

declare(strict_types=1);

beforeEach(function () {
    $result = createApiTestToken();
    $this->user = $result['user'];
    $this->workspace = $result['workspace'];
    $this->plainToken = $result['plain_token'];
});

it('lists content types per platform', function () {
    $this->withHeaders(['Authorization' => 'Bearer '.$this->plainToken])
        ->getJson(route('api.content-types'))
        ->assertOk()
        ->assertJsonStructure([
            'platforms' => [
                '*' => [
                    'platform',
                    'label',
                    'max_content_length',
                    'recommended_content_length',
                    'allowed_media_types',
                    'default_content_type',
                    'content_types' => [
                        '*' => ['value', 'label', 'description', 'max_media_count', 'requires_media'],
                    ],
                ],
            ],
        ]);
});

it('rejects content-types without auth', function () {
    $this->getJson(route('api.content-types'))->assertUnauthorized();
});
