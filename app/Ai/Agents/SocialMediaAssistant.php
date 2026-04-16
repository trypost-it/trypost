<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\PlatformRules\Contract;
use App\Ai\PlatformRules\Registry as PlatformRulesRegistry;
use App\Ai\Tools\GenerateAudio;
use App\Ai\Tools\GenerateImage;
use App\Ai\Tools\GenerateVideo;
use App\Enums\SocialAccount\Platform;
use App\Models\AiMessage;
use App\Models\Post;
use App\Models\Workspace;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

class SocialMediaAssistant implements Agent, Conversational, HasTools
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
            'locale' => app()->getLocale(),
            'platform_rules' => $this->activePlatformRules(),
        ])->render();
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
            ->limit(20)
            ->get()
            ->map(fn (AiMessage $m) => new Message($m->role, $this->enrichContent($m)))
            ->all();
    }

    public function provider(): Lab
    {
        return match (config('trypost.ai.text_provider')) {
            'openai' => Lab::OpenAI,
            default => Lab::Gemini,
        };
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
            new GenerateAudio(
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
                ->map(fn ($group, $type) => count($group)." {$type}")
                ->implode(', ');

            $content .= "\n\n[This assistant message attached: {$counts}]";
        }

        return $content;
    }
}
