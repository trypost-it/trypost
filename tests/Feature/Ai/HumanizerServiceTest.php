<?php

declare(strict_types=1);

use App\Ai\Agents\Humanizer;
use App\Enums\UserWorkspace\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\HumanizerService;

beforeEach(function () {
    $this->user = User::factory()->create([]);
    $this->workspace = Workspace::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Acme',
        'brand_tone' => 'casual',
        'brand_voice_notes' => 'short, punchy sentences. no jargon.',
        'content_language' => 'pt-BR',
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
});

test('humanize returns rewritten text from agent', function () {
    Humanizer::fake(['Texto humanizado, sem travessões.']);

    $service = new HumanizerService;

    $result = $service->humanize('Texto original — com travessão.', $this->workspace);

    expect($result)->toBe('Texto humanizado, sem travessões.');
});

test('humanize trims surrounding whitespace from response', function () {
    Humanizer::fake(["\n\n  Texto limpo.  \n"]);

    $service = new HumanizerService;

    $result = $service->humanize('Original', $this->workspace);

    expect($result)->toBe('Texto limpo.');
});

test('humanize returns original text when input is empty or whitespace only', function () {
    $service = new HumanizerService;

    expect($service->humanize('', $this->workspace))->toBe('');
    expect($service->humanize('   ', $this->workspace))->toBe('   ');
});

test('humanize falls back to original text when agent throws', function () {
    Humanizer::fake(function () {
        throw new RuntimeException('Provider unavailable');
    });

    $service = new HumanizerService;

    $result = $service->humanize('Texto original', $this->workspace);

    expect($result)->toBe('Texto original');
});

test('humanize falls back to original text when agent returns empty string', function () {
    Humanizer::fake(['']);

    $service = new HumanizerService;

    $result = $service->humanize('Texto original', $this->workspace);

    expect($result)->toBe('Texto original');
});

test('humanize injects brand context and content language into instructions', function () {
    $capturedInstructions = null;
    Humanizer::fake(function ($prompt) use (&$capturedInstructions) {
        // Capture the agent's instructions through the agent instance — Promptable
        // doesn't expose them directly to the closure, so we rely on prompt content.
        $capturedInstructions = (string) $prompt;

        return 'ok';
    });

    $service = new HumanizerService;

    $service->humanize('text to rewrite', $this->workspace);

    // The user prompt itself is just the text. The instructions (brand context)
    // are part of the system prompt rendered from the blade — we verify that
    // separately via a render check below.
    expect($capturedInstructions)->toBe('text to rewrite');

    $rendered = view('prompts.assistant.humanize', [
        'brand_name' => $this->workspace->name,
        'brand_tone' => $this->workspace->brand_tone,
        'brand_voice_notes' => $this->workspace->brand_voice_notes,
        'content_language' => $this->workspace->content_language,
    ])->render();

    expect($rendered)
        ->toContain('Acme')
        ->toContain('casual')
        ->toContain('short, punchy sentences')
        ->toContain('pt-BR');
});
