<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Middleware\DebugGeminiRequest;
use App\Ai\PlatformRules\Contract;
use App\Ai\PlatformRules\Registry as PlatformRulesRegistry;
use App\Ai\Tools\GenerateImage;
use App\Ai\Tools\GenerateVideo;
use App\Enums\SocialAccount\Platform;
use App\Models\AiMessage;
use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

#[Temperature(0.3)]
#[MaxSteps(3)]
class SocialMediaAssistant implements Agent, Conversational, HasMiddleware, HasStructuredOutput, HasTools
{
    use Promptable;

    public function __construct(
        public Workspace $workspace,
        public ?Post $post = null,
        public ?string $userId = null,
    ) {}

    public function instructions(): string
    {
        return view('prompts.assistant.system', [
            'brand_name' => $this->workspace->name ?? '',
            'brand_description' => $this->workspace->brand_description ?? '',
            'brand_website' => $this->workspace->brand_website ?? '',
            'tone' => $this->workspace->brand_tone ?? 'professional',
            'voice_notes' => $this->workspace->brand_voice_notes ?? '',
            'content_language' => $this->workspace->content_language ?? 'en',
            'platform_rules' => $this->activePlatformRules(),
            'connected_platforms' => $this->connectedPlatformLabels(),
        ])->render();
    }

    public function contentLanguage(): string
    {
        return $this->workspace->content_language ?? 'en';
    }

    /**
     * @return array<int, Contract>
     */
    private function activePlatformRules(): array
    {
        if (! $this->post) {
            return [];
        }

        $platforms = $this->post->postPlatforms
            ->pluck('platform')
            ->filter()
            ->map(fn ($p) => $p instanceof Platform ? $p : Platform::tryFrom((string) $p))
            ->filter()
            ->unique(fn (Platform $p) => $p->value)
            ->values()
            ->all();

        return PlatformRulesRegistry::forMany($platforms);
    }

    /**
     * @return array<int, array{slug: string, label: string}>
     */
    private function connectedPlatformLabels(): array
    {
        return $this->workspace->socialAccounts()
            ->active()
            ->get()
            ->map(fn ($account) => [
                'slug' => $account->platform->value,
                'label' => $account->platform->label(),
            ])
            ->unique('slug')
            ->values()
            ->all();
    }

    /**
     * @return iterable<Message>
     */
    public function messages(): iterable
    {
        if (! $this->post) {
            return [];
        }

        return AiMessage::query()
            ->where('post_id', $this->post->id)
            ->whereIn('role', ['user', 'assistant'])
            ->oldest()
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->map(fn (AiMessage $m) => new Message($m->role, $this->enrichContent($m)))
            ->all();
    }

    public function provider(): Lab
    {
        return match (config('ai.default')) {
            'openai' => Lab::OpenAI,
            default => Lab::Gemini,
        };
    }

    public function middleware(): array
    {
        return [
            new DebugGeminiRequest,
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'message' => $schema->string()
                ->description('Your response. 1-2 sentences max. No emojis. No brand pitching.')
                ->required(),
            'quick_actions' => $schema->array()
                ->items($schema->object(fn ($s) => [
                    'label' => $s->string()->description('Button text, no emojis, max 20 chars.')->required(),
                    'value' => $s->string()->description('Same as label.')->required(),
                ]))
                ->description('Buttons for FINITE choices only (format, platform, confirm). Empty array for open-ended questions. Max 4 items.')
                ->required(),
        ];
    }

    /**
     * @return iterable<Tool>
     */
    public function tools(): iterable
    {
        return [
            new GenerateImage(
                workspace: $this->workspace,
                post: $this->post,
                userId: $this->userId,
            ),
            new GenerateVideo(
                workspace: $this->workspace,
                post: $this->post,
                userId: $this->userId,
            ),
        ];
    }

    private function enrichContent(AiMessage $m): string
    {
        $content = $m->content;

        if ($m->role === 'assistant' && ! empty($m->attachments)) {
            $counts = collect($m->attachments)
                ->groupBy('type')
                ->map(fn ($group, $type) => count($group).' '.Str::plural((string) $type, count($group)))
                ->implode(', ');

            $content .= "\n\n[This assistant message attached: {$counts}]";
        }

        return $content;
    }
}
