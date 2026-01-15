<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Post;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        $this->authorize('view', $workspace);

        $posts = $workspace->posts()
            ->with(['postPlatforms.socialAccount', 'user'])
            ->latest('scheduled_at')
            ->paginate(20);

        return Inertia::render('posts/Index', [
            'workspace' => $workspace,
            'posts' => $posts,
        ]);
    }

    public function calendar(Request $request, Workspace $workspace): Response
    {
        $this->authorize('view', $workspace);

        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth();

        $posts = $workspace->posts()
            ->with(['postPlatforms.socialAccount'])
            ->whereBetween('scheduled_at', [$startOfMonth, $endOfMonth])
            ->orderBy('scheduled_at')
            ->get()
            ->groupBy(fn ($post) => $post->scheduled_at?->format('Y-m-d'));

        $socialAccounts = $workspace->socialAccounts;

        return Inertia::render('posts/Calendar', [
            'workspace' => $workspace,
            'posts' => $posts,
            'socialAccounts' => $socialAccounts,
            'currentMonth' => $month,
            'currentYear' => $year,
        ]);
    }

    public function create(Request $request, Workspace $workspace): Response|RedirectResponse
    {
        $this->authorize('view', $workspace);

        $socialAccounts = $workspace->socialAccounts;

        if ($socialAccounts->isEmpty()) {
            return redirect()->route('workspaces.accounts', $workspace)
                ->with('error', 'Conecte pelo menos uma rede social antes de criar um post.');
        }

        return Inertia::render('posts/Create', [
            'workspace' => $workspace,
            'socialAccounts' => $socialAccounts,
            'scheduledDate' => $request->input('date'),
        ]);
    }

    public function store(StorePostRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('view', $workspace);

        $post = $workspace->posts()->create([
            'user_id' => $request->user()->id,
            'status' => $request->input('status', PostStatus::Draft),
            'scheduled_at' => $request->input('scheduled_at'),
        ]);

        foreach ($request->input('platforms', []) as $platformData) {
            $postPlatform = $post->postPlatforms()->create([
                'social_account_id' => $platformData['social_account_id'],
                'platform' => $platformData['platform'],
                'content' => $platformData['content'],
                'status' => 'pending',
            ]);

            if (! empty($platformData['media_ids'])) {
                $postPlatform->media()->whereIn('id', $platformData['media_ids'])->update([
                    'post_platform_id' => $postPlatform->id,
                ]);
            }
        }

        $route = $request->input('status') === PostStatus::Scheduled->value
            ? 'workspaces.calendar'
            : 'workspaces.posts.index';

        return redirect()->route($route, $workspace)
            ->with('success', 'Post criado com sucesso!');
    }

    public function show(Workspace $workspace, Post $post): Response
    {
        $this->authorize('view', $workspace);

        if ($post->workspace_id !== $workspace->id) {
            abort(404);
        }

        $post->load(['postPlatforms.socialAccount', 'postPlatforms.media', 'user']);

        return Inertia::render('posts/Show', [
            'workspace' => $workspace,
            'post' => $post,
        ]);
    }

    public function edit(Workspace $workspace, Post $post): Response|RedirectResponse
    {
        $this->authorize('view', $workspace);

        if ($post->workspace_id !== $workspace->id) {
            abort(404);
        }

        if ($post->status === PostStatus::Published) {
            return redirect()->route('workspaces.posts.show', [$workspace, $post])
                ->with('error', 'Posts publicados não podem ser editados.');
        }

        $post->load(['postPlatforms.socialAccount', 'postPlatforms.media']);
        $socialAccounts = $workspace->socialAccounts;

        return Inertia::render('posts/Edit', [
            'workspace' => $workspace,
            'post' => $post,
            'socialAccounts' => $socialAccounts,
        ]);
    }

    public function update(UpdatePostRequest $request, Workspace $workspace, Post $post): RedirectResponse
    {
        $this->authorize('view', $workspace);

        if ($post->workspace_id !== $workspace->id) {
            abort(404);
        }

        if ($post->status === PostStatus::Published) {
            return back()->with('error', 'Posts publicados não podem ser editados.');
        }

        $post->update([
            'status' => $request->input('status', $post->status),
            'scheduled_at' => $request->input('scheduled_at', $post->scheduled_at),
        ]);

        foreach ($request->input('platforms', []) as $platformData) {
            $post->postPlatforms()
                ->where('id', $platformData['id'])
                ->update([
                    'content' => $platformData['content'],
                ]);
        }

        return redirect()->route('workspaces.posts.show', [$workspace, $post])
            ->with('success', 'Post atualizado com sucesso!');
    }

    public function destroy(Workspace $workspace, Post $post): RedirectResponse
    {
        $this->authorize('view', $workspace);

        if ($post->workspace_id !== $workspace->id) {
            abort(404);
        }

        $post->delete();

        return redirect()->route('workspaces.calendar', $workspace)
            ->with('success', 'Post excluído com sucesso!');
    }
}
