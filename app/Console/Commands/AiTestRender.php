<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Media\Type;
use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Image\TemplateImageGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Debug helper to iterate on the AI image template visuals without going through
 * the full wizard + AI flow.
 *
 *   php artisan ai:test-render --template=A --title="Some title" --body="Some body" --keywords="kitchen,team"
 *   php artisan ai:test-render --post=019deae5-... --template=A
 *
 * Outputs the storage path so you can open the file directly.
 */
class AiTestRender extends Command
{
    protected $signature = 'ai:test-render
        {--template=A : Template to render (A or B)}
        {--title= : Slide title}
        {--body= : Slide body}
        {--keywords= : Comma-separated Unsplash keywords}
        {--post= : Post UUID to re-render images for (replaces Post.media in place)}
        {--workspace= : Workspace UUID (defaults to first)}
        {--account= : Social account UUID (defaults to first connected on workspace)}
        {--width=1080 : Canvas width in pixels}
        {--height=1350 : Canvas height in pixels}';

    protected $description = 'Render an AI image template with custom inputs (debug).';

    public function handle(TemplateImageGenerator $generator): int
    {
        $workspace = $this->resolveWorkspace();
        if (! $workspace) {
            $this->error('No workspace found. Use --workspace=<uuid>.');

            return self::FAILURE;
        }

        $socialAccount = $this->resolveSocialAccount($workspace);
        if (! $socialAccount) {
            $this->error('No social account on workspace. Use --account=<uuid> or connect one.');

            return self::FAILURE;
        }

        if ($this->option('post')) {
            return $this->rerenderPost($generator, $workspace, $socialAccount);
        }

        return $this->renderSingle($generator, $workspace, $socialAccount);
    }

    private function renderSingle(TemplateImageGenerator $generator, Workspace $workspace, SocialAccount $socialAccount): int
    {
        $template = strtoupper((string) $this->option('template'));
        $title = (string) ($this->option('title') ?: 'Hello world');
        $body = (string) ($this->option('body') ?: 'A short body that gives more context about the slide.');
        $keywords = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('keywords'))))) ?: ['business'];

        $this->info("Rendering Template {$template}...");
        $this->line("  title:    {$title}");
        $this->line("  body:     {$body}");
        $this->line('  keywords: '.implode(', ', $keywords));

        $path = $generator->render(
            template: $template,
            workspace: $workspace,
            socialAccount: $socialAccount,
            title: $title,
            body: $body,
            imageKeywords: $keywords,
            width: (int) $this->option('width'),
            height: (int) $this->option('height'),
        );

        if (! $path) {
            $this->error('Render failed. Check Unsplash key + recent logs.');

            return self::FAILURE;
        }

        $this->info('OK');
        $this->line('storage path: '.$path);
        $this->line('absolute:     '.Storage::path($path));
        $this->line('public url:   '.Storage::url($path));

        return self::SUCCESS;
    }

    private function rerenderPost(TemplateImageGenerator $generator, Workspace $workspace, SocialAccount $socialAccount): int
    {
        $postId = (string) $this->option('post');
        $post = Post::query()->where('id', $postId)->first();
        if (! $post) {
            $this->error("Post {$postId} not found.");

            return self::FAILURE;
        }
        if ($post->workspace_id !== $workspace->id) {
            $this->error("Post {$postId} belongs to a different workspace.");

            return self::FAILURE;
        }

        $template = strtoupper((string) ($this->option('template') ?: 'A'));
        $media = $post->media ?? [];
        if (empty($media)) {
            $this->error('Post has no media items. Use direct mode (--title/--body/--keywords) instead.');

            return self::FAILURE;
        }

        $this->info("Re-rendering {$template} for ".count($media).' slide(s)...');

        $rendered = [];
        $newMedia = [];
        foreach ($media as $i => $item) {
            $isClosing = (bool) data_get($item, 'meta.is_closing', false);

            $width = (int) $this->option('width');
            $height = (int) $this->option('height');

            if ($isClosing) {
                $this->line("  [{$i}] template=C (closing)");
                $path = $generator->renderClosing(
                    workspace: $workspace,
                    socialAccount: $socialAccount,
                    width: $width,
                    height: $height,
                );
            } else {
                $title = data_get($item, 'meta.slide_title') ?? 'Slide '.($i + 1);
                $body = data_get($item, 'meta.slide_body') ?? ($post->content ?? '');
                $keywords = data_get($item, 'meta.slide_keywords') ?: ['business'];
                // First slide is always Template A; subsequent slides alternate.
                $slotTemplate = $i === 0 ? 'A' : ($i % 2 === 0 ? 'A' : 'B');

                $this->line("  [{$i}] template={$slotTemplate} title={$title}");

                $path = $generator->render(
                    template: $slotTemplate,
                    workspace: $workspace,
                    socialAccount: $socialAccount,
                    title: $title,
                    body: $body,
                    imageKeywords: $keywords,
                    width: $width,
                    height: $height,
                );
            }

            if ($path) {
                $rendered[] = $path;
                $this->line('       → '.$path);
                $newMedia[] = $this->replaceMediaItem($workspace, $item, $path);
            } else {
                $this->warn('       → (failed)');
                $newMedia[] = $item;
            }
        }

        $post->media = $newMedia;
        $post->save();

        $this->info('Done. '.count($rendered).' image(s) replaced in post.');
        foreach ($rendered as $p) {
            $this->line('  '.Storage::url($p));
        }

        return self::SUCCESS;
    }

    /**
     * Persist a new Media row pointing at $path and return a media-array shape
     * matching the existing $original entry (preserves meta.slide_*).
     *
     * @param  array<string, mixed>  $original
     * @return array<string, mixed>
     */
    private function replaceMediaItem(Workspace $workspace, array $original, string $path): array
    {
        $media = new Media([
            'collection' => 'ai-generated',
            'type' => Type::Image,
            'path' => $path,
            'original_filename' => basename($path),
            'mime_type' => 'image/webp',
            'size' => Storage::size($path),
            'order' => 0,
        ]);
        $media->mediable_type = Workspace::class;
        $media->mediable_id = $workspace->id;
        $media->save();

        return [
            'id' => $media->id,
            'path' => $media->path,
            'url' => $media->url,
            'type' => 'image',
            'mime_type' => 'image/webp',
            'meta' => data_get($original, 'meta', []),
        ];
    }

    private function resolveWorkspace(): ?Workspace
    {
        if ($id = $this->option('workspace')) {
            return Workspace::query()->where('id', $id)->first();
        }

        return Workspace::query()->first();
    }

    private function resolveSocialAccount(Workspace $workspace): ?SocialAccount
    {
        if ($id = $this->option('account')) {
            return $workspace->socialAccounts()->where('id', $id)->first();
        }

        return $workspace->socialAccounts()->first();
    }
}
