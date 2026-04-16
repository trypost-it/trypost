<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Enums\Ai\UsageType;
use App\Features\AiVideosLimit;
use App\Models\AiUsageLog;
use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Audio;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Laravel\Pennant\Feature;
use Stringable;

class GenerateAudio implements Tool
{
    public function __construct(
        public Workspace $workspace,
        public ?Post $post = null,
        public ?string $userId = null,
        public ?AttachmentCollector $collector = null,
    ) {
        $this->collector ??= app(AttachmentCollector::class);
    }

    public function description(): Stringable|string
    {
        return <<<'TXT'
Generate an AI voiceover or narration audio from text.

Call this when the user asks for a voiceover, narration, TTS, podcast clip,
or any voice content. The output language will match the language of the
input text — so pass the text in the target language.

Audio is generated via ElevenLabs with the configured default voice.
TXT;
    }

    public function handle(Request $request): Stringable|string
    {
        // Audio generation counts against the monthly video quota by design.
        $limit = (int) Feature::for($this->workspace->account)->value(AiVideosLimit::class);
        $used = AiUsageLog::monthlyCount($this->workspace->account_id, UsageType::Video)
            + AiUsageLog::monthlyCount($this->workspace->account_id, UsageType::Audio);

        if ($used >= $limit) {
            return "Audio and video share a monthly quota that is exhausted ({$used} of {$limit} used). Ask the user to upgrade their plan or wait until next month.";
        }

        $text = (string) data_get($request, 'text', '');
        $voice = config('services.elevenlabs.default_voice', 'EXAVITQu4vr4xnSDxMaL');

        $response = Audio::of($text)->voice($voice)->generate();

        $storedPath = $response->store('medias', 'public');

        $media = $this->workspace->media()->create([
            'group_id' => Str::uuid()->toString(),
            'collection' => 'assets',
            'type' => 'video',
            'path' => $storedPath,
            'original_filename' => 'ai-generated.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => Storage::disk('public')->size($storedPath),
            'order' => 0,
            'meta' => ['ai_generated' => true, 'text' => Str::limit($text, 200)],
        ]);

        AiUsageLog::create([
            'account_id' => $this->workspace->account_id,
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->userId,
            'post_id' => $this->post?->id,
            'type' => UsageType::Audio,
            'provider' => 'elevenlabs',
        ]);

        $this->collector->push([
            'id' => $media->id,
            'path' => $media->path,
            'url' => $media->url,
            'mime_type' => 'audio/mpeg',
            'type' => 'audio',
        ]);

        return "Generated audio (id: {$media->id}) and attached it to the post.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'text' => $schema->string()
                ->description('The text to convert into speech. Write it in the target language — the output voice language will match.')
                ->required(),
        ];
    }
}
