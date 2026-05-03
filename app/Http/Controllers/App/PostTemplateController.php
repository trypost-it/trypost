<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Post\CreatePost;
use App\Enums\Media\Type as MediaType;
use App\Http\Resources\App\PostTemplateResource;
use App\Models\Media;
use App\Models\PostTemplate;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Image\TemplateImageGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class PostTemplateController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $request->validate([
            'platform' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $templates = PostTemplate::query()
            ->when($request->input('platform'), fn ($q, $p) => $q->where('platform', $p))
            ->when($request->input('search'), function ($q, $search) {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('name', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('category')
            ->orderBy('name')
            ->paginate(config('app.pagination.default'));

        return Inertia::render('posts/templates/Index', [
            'templates' => Inertia::scroll(fn () => PostTemplateResource::collection($templates)),
            'filters' => [
                'search' => $request->input('search', ''),
                'platform' => $request->input('platform', ''),
            ],
        ]);
    }

    public function apply(Request $request, PostTemplate $template, TemplateImageGenerator $generator): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $request->validate([
            'social_account_id' => ['nullable', 'uuid'],
        ]);

        $socialAccountId = $request->input('social_account_id');
        $socialAccount = null;

        if ($socialAccountId) {
            $socialAccount = SocialAccount::where('id', $socialAccountId)
                ->where('workspace_id', $workspace->id)
                ->first();

            if (! $socialAccount) {
                abort(Response::HTTP_FORBIDDEN);
            }
        }

        $content = $this->interpolate($template->content, $workspace);

        $media = [];

        if ($socialAccount && $template->slides) {
            foreach ($template->slides as $i => $slide) {
                // First slide is always Template A (full-bleed cover); subsequent slides
                // alternate so even-indexed are A and odd-indexed are B.
                $tmpl = $i === 0 ? 'A' : ($i % 2 === 0 ? 'A' : 'B');
                $path = $generator->render(
                    template: $tmpl,
                    workspace: $workspace,
                    socialAccount: $socialAccount,
                    title: $this->interpolate(data_get($slide, 'title', ''), $workspace),
                    body: $this->interpolate(data_get($slide, 'body', ''), $workspace),
                    imageKeywords: data_get($slide, 'image_keywords', []),
                );

                if ($path) {
                    $mediaItem = $this->createMediaItem($workspace, $path);
                    $media[] = $mediaItem;
                }
            }
        }

        $post = CreatePost::execute($workspace, $request->user(), [
            'content' => $content,
            'media' => $media,
        ]);

        return response()->json([
            'post_id' => $post->id,
            'redirect_url' => route('app.posts.edit', $post),
        ]);
    }

    private function interpolate(string $text, Workspace $workspace): string
    {
        return strtr($text, [
            '{{brand_name}}' => $workspace->name ?? '',
            '{{brand_description}}' => $workspace->brand_description ?? '',
        ]);
    }

    /**
     * Create a Media record for a generated image and return it as an array.
     *
     * @return array<string, mixed>
     */
    private function createMediaItem(Workspace $workspace, string $path): array
    {
        $media = new Media([
            'collection' => 'ai-generated',
            'type' => MediaType::Image,
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
        ];
    }
}
