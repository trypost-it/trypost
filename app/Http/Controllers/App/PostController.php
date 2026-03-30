<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Post\CreatePost;
use App\Actions\Post\DeletePost;
use App\Actions\Post\UpdatePost;
use App\Enums\SocialAccount\Platform;
use App\Http\Requests\App\Post\UpdatePostRequest;
use App\Models\Post;
use App\Services\Social\PinterestPublisher;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function index(Request $request, ?string $status = null): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('view', $workspace);

        $query = $workspace->posts()
            ->with(['postPlatforms' => fn ($query) => $query->where('enabled', true)->with('socialAccount'), 'user', 'labels']);

        if ($status) {
            $query = match ($status) {
                'draft' => $query->draft(),
                'scheduled' => $query->scheduled(),
                'published' => $query->published(),
                default => $query,
            };
        }

        return Inertia::render('posts/Index', [
            'workspace' => $workspace,
            'posts' => Inertia::scroll(fn () => $query->latest('scheduled_at')->paginate(15)),
            'currentStatus' => $status,
        ]);
    }

    public function calendar(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('view', $workspace);

        $tz = $workspace->timezone;
        $view = $request->input('view', 'week');

        $currentDay = $request->input('day')
            ? Carbon::parse($request->input('day'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();

        $weekStart = $request->input('week')
            ? Carbon::parse($request->input('week'), $tz)->startOfWeek()
            : Carbon::now($tz)->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $monthDate = $request->input('month')
            ? Carbon::parse($request->input('month'), $tz)->startOfMonth()
            : Carbon::now($tz)->startOfMonth();
        $monthStart = $monthDate->copy()->startOfMonth()->startOfWeek();
        $monthEnd = $monthDate->copy()->endOfMonth()->endOfWeek();

        $rangeStart = match ($view) {
            'day' => $currentDay,
            'month' => $monthStart,
            default => $weekStart,
        };
        $rangeEnd = match ($view) {
            'day' => $currentDay->copy()->endOfDay(),
            'month' => $monthEnd,
            default => $weekEnd,
        };

        $posts = $workspace->posts()
            ->with(['postPlatforms' => fn ($query) => $query->where('enabled', true)->with('socialAccount')])
            ->whereBetween('scheduled_at', [$rangeStart->copy()->utc(), $rangeEnd->copy()->utc()])
            ->orderBy('scheduled_at')
            ->get()
            ->groupBy(fn ($post) => $post->scheduled_at?->setTimezone($tz)->format('Y-m-d'));

        return Inertia::render('posts/Calendar', [
            'workspace' => $workspace,
            'posts' => $posts,
            'currentDay' => $currentDay->format('Y-m-d'),
            'currentWeekStart' => $weekStart->format('Y-m-d'),
            'currentMonth' => $monthDate->format('Y-m-d'),
            'view' => $view,
        ]);
    }

    public function store(Request $request): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('view', $workspace);

        $socialAccounts = $workspace->socialAccounts;

        if ($socialAccounts->isEmpty()) {
            session()->flash('flash.banner', __('posts.flash.connect_first'));
            session()->flash('flash.bannerStyle', 'danger');

            return redirect()->route('app.accounts');
        }

        $post = CreatePost::execute($workspace, $request->user(), [
            'date' => $request->input('date'),
        ]);

        return Inertia::location(route('app.posts.edit', $post));
    }

    public function edit(Request $request, Post $post): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('view', $workspace);

        if ($post->workspace_id !== $workspace->id) {
            abort(404);
        }

        $post->load(['postPlatforms.socialAccount', 'postPlatforms.media', 'labels']);
        $socialAccounts = $workspace->socialAccounts;
        $labels = $workspace->labels;
        $hashtags = $workspace->hashtags;

        $platformConfigs = $socialAccounts->mapWithKeys(fn ($account) => [
            $account->id => [
                'maxContentLength' => $account->platform->maxContentLength(),
                'maxImages' => $account->platform->maxImages(),
                'allowedMediaTypes' => array_map(fn ($type) => $type->value, $account->platform->allowedMediaTypes()),
                'supportsTextOnly' => $account->platform->supportsTextOnly(),
            ],
        ]);

        $pinterestBoards = [];
        $pinterestAccount = $socialAccounts->firstWhere('platform', Platform::Pinterest);
        if ($pinterestAccount) {
            try {
                $pinterestBoards = app(PinterestPublisher::class)->getBoards($pinterestAccount);
            } catch (\Exception $e) {
                // Silently fail - boards will be empty
            }
        }

        return Inertia::render('posts/Edit', [
            'workspace' => $workspace,
            'post' => $post,
            'socialAccounts' => $socialAccounts,
            'platformConfigs' => $platformConfigs,
            'pinterestBoards' => $pinterestBoards,
            'labels' => $labels,
            'hashtags' => $hashtags,
        ]);
    }

    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('view', $workspace);

        if ($post->workspace_id !== $workspace->id) {
            abort(404);
        }

        $result = UpdatePost::execute($workspace, $post, $request->validated());

        $action = data_get($result, 'action');

        if ($action === 'already_published') {
            session()->flash('flash.banner', __('posts.flash.cannot_edit_published'));
            session()->flash('flash.bannerStyle', 'danger');

            return back();
        }

        if ($action === 'publishing') {
            session()->flash('flash.banner', __('posts.flash.publishing'));
            session()->flash('flash.bannerStyle', 'success');

            return redirect()->route('app.posts.edit', $post);
        }

        if ($action === 'scheduled') {
            session()->flash('flash.banner', __('posts.flash.scheduled'));
            session()->flash('flash.bannerStyle', 'success');

            return redirect()->route('app.posts.edit', $post);
        }

        return back();
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('view', $workspace);

        if ($post->workspace_id !== $workspace->id) {
            abort(404);
        }

        DeletePost::execute($post);

        session()->flash('flash.banner', __('posts.flash.deleted'));
        session()->flash('flash.bannerStyle', 'success');

        if ($redirect = $request->input('redirect')) {
            return redirect()->route($redirect);
        }

        return back();
    }
}
