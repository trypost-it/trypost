<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Post\CreatePost;
use App\Enums\Media\Type as MediaType;
use App\Http\Resources\App\PostTemplateResource;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Image\TemplateImageGenerator;
use App\Services\PostTemplate\Registry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class PostTemplateController extends Controller
{
    public function __construct(private readonly Registry $registry) {}

    public function index(Request $request): InertiaResponse
    {
        $request->validate([
            'platform' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $paginator = $this->registry->paginate(
            locale: app()->getLocale(),
            platform: $request->input('platform'),
            search: $request->input('search'),
            perPage: (int) config('app.pagination.default'),
            page: (int) $request->input('page', 1),
            path: $request->url(),
            query: $request->query(),
        );

        return Inertia::render('posts/templates/Index', [
            'templates' => Inertia::scroll(fn () => PostTemplateResource::collection($paginator)),
            'filters' => [
                'search' => $request->input('search', ''),
                'platform' => $request->input('platform', ''),
            ],
        ]);
    }

    public function apply(Request $request, string $slug, TemplateImageGenerator $generator): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $request->validate([
            'social_account_id' => ['nullable', 'uuid'],
        ]);

        $template = $this->registry->find($slug, app()->getLocale());

        $socialAccountId = $request->input('social_account_id');
        $socialAccount = null;

        if ($socialAccountId) {
            $socialAccount = SocialAccount::where('id', $socialAccountId)
                ->where('workspace_id', $workspace->id)
                ->first();

            abort_if($socialAccount === null, Response::HTTP_FORBIDDEN);
        }

        $content = $this->interpolate($template->content, $workspace);

        $media = [];

        if ($socialAccount && $template->slides) {
            foreach ($template->slides as $i => $slide) {
                // Even-indexed slides use Template A (full-bleed cover); odd-indexed slides use Template B.
                $tmpl = $i % 2 === 0 ? 'A' : 'B';
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
        // Use the relationship (not `mediable_type = Workspace::class`) so
        // the morph map alias `'workspace'` is persisted instead of the FQCN.
        $media = $workspace->media()->create([
            'collection' => 'ai-generated',
            'type' => MediaType::Image,
            'path' => $path,
            'original_filename' => basename($path),
            'mime_type' => 'image/webp',
            'size' => Storage::size($path),
            'order' => 0,
        ]);

        return [
            'id' => $media->id,
            'path' => $media->path,
            'url' => $media->url,
            'type' => 'image',
            'mime_type' => 'image/webp',
        ];
    }
}
