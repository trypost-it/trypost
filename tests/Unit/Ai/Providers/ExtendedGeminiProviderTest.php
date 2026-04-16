<?php

declare(strict_types=1);

use App\Ai\Providers\ExtendedGeminiProvider;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Gateway\Gemini\GeminiGateway;

beforeEach(function () {
    $this->provider = new ExtendedGeminiProvider(
        new GeminiGateway(app(Dispatcher::class)),
        ['name' => 'gemini', 'driver' => 'gemini'],
        app(Dispatcher::class),
    );
});

test('supports social media vertical aspect ratio 9:16', function () {
    $options = $this->provider->defaultImageOptions('9:16');

    expect($options['aspect_ratio'])->toBe('9:16');
});

test('supports social media horizontal aspect ratio 16:9', function () {
    $options = $this->provider->defaultImageOptions('16:9');

    expect($options['aspect_ratio'])->toBe('16:9');
});

test('still supports the three original ratios from the base provider', function () {
    expect($this->provider->defaultImageOptions('1:1')['aspect_ratio'])->toBe('1:1');
    expect($this->provider->defaultImageOptions('2:3')['aspect_ratio'])->toBe('2:3');
    expect($this->provider->defaultImageOptions('3:2')['aspect_ratio'])->toBe('3:2');
});

test('supports additional Instagram-friendly ratios', function () {
    expect($this->provider->defaultImageOptions('4:5')['aspect_ratio'])->toBe('4:5');
    expect($this->provider->defaultImageOptions('5:4')['aspect_ratio'])->toBe('5:4');
    expect($this->provider->defaultImageOptions('4:3')['aspect_ratio'])->toBe('4:3');
    expect($this->provider->defaultImageOptions('3:4')['aspect_ratio'])->toBe('3:4');
});

test('drops unsupported aspect ratios silently', function () {
    $options = $this->provider->defaultImageOptions('gibberish');

    expect($options)->not->toHaveKey('aspect_ratio');
});
