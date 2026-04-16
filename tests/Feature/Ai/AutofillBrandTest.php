<?php

declare(strict_types=1);

use App\Actions\Ai\AutofillBrand;
use App\Ai\Agents\BrandAnalyzer;
use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create(['setup' => Setup::Brand]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    // Run tests without LLM credentials so the deterministic fallback is exercised.
    config()->set('services.gemini.api_key', '');
    config()->set('services.openai.api_key', '');
});

test('extracts name, description, language, and logo from meta tags', function () {
    Http::fake([
        'example.com' => Http::response(<<<'HTML'
            <!DOCTYPE html>
            <html lang="pt-BR">
            <head>
              <title>Acme Coffee | The best beans online</title>
              <meta name="description" content="Premium artisan coffee beans shipped worldwide.">
              <meta property="og:site_name" content="Acme Coffee">
              <meta property="og:image" content="https://example.com/og.png">
              <link rel="apple-touch-icon" href="https://example.com/apple-touch-icon.png">
              <link rel="icon" sizes="512x512" href="/icon-512.png">
            </head>
            <body>Welcome</body>
            </html>
        HTML, 200),
        'example.com/apple-touch-icon.png' => Http::response(file_get_contents(__DIR__.'/../../fixtures/1x1.png'), 200, ['Content-Type' => 'image/png']),
    ]);

    $result = (new AutofillBrand)('https://example.com', $this->workspace);

    expect($result['name'])->toBe('Acme Coffee');
    expect($result['brand_description'])->toBe('Premium artisan coffee beans shipped worldwide.');
    expect($result['content_language'])->toBe('pt-BR');
    expect($result['logo_url'])->toBe('https://example.com/apple-touch-icon.png');

    $this->workspace->refresh();
    expect($this->workspace->has_logo)->toBeTrue();
});

test('falls back to title without og:site_name', function () {
    Http::fake([
        'example.com' => Http::response(<<<'HTML'
            <html lang="en">
            <head>
              <title>Super SaaS | Landing page</title>
              <meta name="description" content="A simple product.">
            </head>
            <body></body>
            </html>
        HTML, 200),
    ]);

    $result = (new AutofillBrand)('https://example.com', $this->workspace);

    expect($result['name'])->toBe('Super SaaS');
    expect($result['content_language'])->toBe('en');
});

test('normalizes various language codes to supported locales', function (string $lang, ?string $expected) {
    Http::fake([
        'example.com' => Http::response("<html lang=\"{$lang}\"><head><title>X</title></head></html>", 200),
    ]);

    $result = (new AutofillBrand)('https://example.com', $this->workspace);

    expect($result['content_language'])->toBe($expected);
})->with([
    ['pt', 'pt-BR'],
    ['pt-PT', 'pt-BR'],
    ['en-US', 'en'],
    ['es-MX', 'es'],
    ['fr', null],
    ['ja-JP', null],
]);

test('rejects non-http schemes', function () {
    expect(fn () => (new AutofillBrand)('ftp://example.com', $this->workspace))
        ->toThrow(RuntimeException::class, 'Only http:// and https://');
});

test('rejects private network addresses', function () {
    expect(fn () => (new AutofillBrand)('http://127.0.0.1', $this->workspace))
        ->toThrow(RuntimeException::class, 'private');

    expect(fn () => (new AutofillBrand)('http://192.168.1.1', $this->workspace))
        ->toThrow(RuntimeException::class, 'private');
});

test('adds https:// when scheme is missing', function () {
    Http::fake([
        'example.com' => Http::response('<html><head><title>ok</title></head></html>', 200),
    ]);

    (new AutofillBrand)('example.com', $this->workspace);

    Http::assertSent(fn (HttpRequest $req) => str_starts_with($req->url(), 'https://example.com'));
});

test('returns empty fields when site has no meta tags', function () {
    Http::fake([
        'example.com' => Http::response('<html><body></body></html>', 200),
    ]);

    $result = (new AutofillBrand)('https://example.com', $this->workspace);

    expect($result['name'])->toBeNull();
    expect($result['brand_description'])->toBeNull();
    expect($result['content_language'])->toBeNull();
    expect($result['logo_url'])->toBeNull();
});

test('throws when upstream site returns an error', function () {
    Http::fake([
        'example.com' => Http::response('', 500),
    ]);

    expect(fn () => (new AutofillBrand)('https://example.com', $this->workspace))
        ->toThrow(RuntimeException::class, 'HTTP 500');
});

test('when llm is configured, polishes description/tone/language/voice_notes via BrandAnalyzer', function () {
    config()->set('services.gemini.api_key', 'fake-key');

    Http::fake([
        'example.com' => Http::response(<<<'HTML'
            <html lang="en">
            <head>
              <title>Widget Co</title>
              <meta name="description" content="A very terse seo blurb.">
            </head>
            <body>
              <main>
                <h1>Build widgets faster</h1>
                <p>Widget Co helps small teams ship production widgets 10x faster without writing boilerplate.</p>
              </main>
            </body>
            </html>
        HTML, 200),
    ]);

    BrandAnalyzer::fake([
        [
            'description' => 'Widget Co helps small teams ship production widgets faster.',
            'tone' => 'friendly',
            'language' => 'en',
            'voice_notes' => 'Use short punchy sentences. Focus on developer benefits.',
        ],
    ]);

    $result = (new AutofillBrand)('https://example.com', $this->workspace);

    expect($result['brand_description'])->toBe('Widget Co helps small teams ship production widgets faster.');
    expect($result['brand_tone'])->toBe('friendly');
    expect($result['content_language'])->toBe('en');
    expect($result['brand_voice_notes'])->toBe('Use short punchy sentences. Focus on developer benefits.');
});

test('when llm is not configured, falls back to meta tags only', function () {
    // beforeEach already cleared api keys.
    Http::fake([
        'example.com' => Http::response(<<<'HTML'
            <html lang="pt-BR">
            <head>
              <title>Marca</title>
              <meta name="description" content="Uma descrição curta.">
            </head>
            <body><main><p>hello</p></main></body>
            </html>
        HTML, 200),
    ]);

    // Fail loud if BrandAnalyzer is called.
    BrandAnalyzer::fake()->preventStrayPrompts();

    $result = (new AutofillBrand)('https://example.com', $this->workspace);

    expect($result['brand_description'])->toBe('Uma descrição curta.');
    expect($result['content_language'])->toBe('pt-BR');
    expect($result['brand_tone'])->toBeNull();
    expect($result['brand_voice_notes'])->toBeNull();
});

test('falls back to meta tags when BrandAnalyzer throws', function () {
    config()->set('services.gemini.api_key', 'fake-key');

    Http::fake([
        'example.com' => Http::response(<<<'HTML'
            <html lang="en">
            <head>
              <title>Acme</title>
              <meta name="description" content="Fallback desc.">
            </head>
            <body><main><p>hi</p></main></body>
            </html>
        HTML, 200),
    ]);

    BrandAnalyzer::fake([
        fn () => throw new RuntimeException('LLM went down'),
    ]);

    $result = (new AutofillBrand)('https://example.com', $this->workspace);

    expect($result['brand_description'])->toBe('Fallback desc.');
    expect($result['content_language'])->toBe('en');
    expect($result['brand_tone'])->toBeNull();
    expect($result['brand_voice_notes'])->toBeNull();
});

test('skips logo that is too large or wrong mime', function () {
    Http::fake([
        'example.com' => Http::response(<<<'HTML'
            <html><head>
            <link rel="apple-touch-icon" href="https://example.com/malicious.exe">
            </head></html>
        HTML, 200),
        'example.com/malicious.exe' => Http::response('fake', 200, ['Content-Type' => 'application/octet-stream']),
    ]);

    (new AutofillBrand)('https://example.com', $this->workspace);

    $this->workspace->refresh();
    expect($this->workspace->has_logo)->toBeFalse();
});
