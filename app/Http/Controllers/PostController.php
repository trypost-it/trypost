<?php

namespace App\Http\Controllers;

use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Http\Requests\UpdatePostRequest;
use App\Jobs\PublishPost;
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
            return redirect()->route('workspaces.create');
        }

        $this->authorize('view', $workspace);

        $query = $workspace->posts()
            ->with(['postPlatforms' => function ($query) {
                $query->where('enabled', true)->with('socialAccount');
            }, 'user']);

        // Apply status filter if provided
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
            return redirect()->route('workspaces.create');
        }

        $this->authorize('view', $workspace);

        $tz = $workspace->timezone;
        $view = $request->input('view', 'week');

        // Week view
        $weekStart = $request->input('week')
            ? Carbon::parse($request->input('week'), $tz)->startOfWeek()
            : Carbon::now($tz)->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        // Month view
        $monthDate = $request->input('month')
            ? Carbon::parse($request->input('month'), $tz)->startOfMonth()
            : Carbon::now($tz)->startOfMonth();
        $monthStart = $monthDate->copy()->startOfMonth()->startOfWeek();
        $monthEnd = $monthDate->copy()->endOfMonth()->endOfWeek();

        // Get posts for both views (we query the larger range to cover both)
        $rangeStart = $view === 'month' ? $monthStart : $weekStart;
        $rangeEnd = $view === 'month' ? $monthEnd : $weekEnd;

        $posts = $workspace->posts()
            ->with(['postPlatforms' => function ($query) {
                $query->where('enabled', true)->with('socialAccount');
            }])
            ->whereBetween('scheduled_at', [$rangeStart->copy()->utc(), $rangeEnd->copy()->utc()])
            ->orderBy('scheduled_at')
            ->get()
            ->groupBy(fn ($post) => $post->scheduled_at?->setTimezone($tz)->format('Y-m-d'));

        return Inertia::render('posts/Calendar', [
            'workspace' => $workspace,
            'posts' => $posts,
            'currentWeekStart' => $weekStart->format('Y-m-d'),
            'currentMonth' => $monthDate->format('Y-m-d'),
            'view' => $view,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('view', $workspace);

        $socialAccounts = $workspace->socialAccounts;

        if ($socialAccounts->isEmpty()) {
            session()->flash('flash.banner', __('posts.flash.connect_first'));
            session()->flash('flash.bannerStyle', 'danger');

            return redirect()->route('accounts');
        }

        // Create a draft post - default to today if no date provided
        $date = $request->input('date') ?: Carbon::now($workspace->timezone)->format('Y-m-d');
        $scheduledAt = Carbon::parse($date, $workspace->timezone)
            ->setTime(9, 0)
            ->utc();

        $post = $workspace->posts()->create([
            'user_id' => $request->user()->id,
            'status' => PostStatus::Draft,
            'synced' => true,
            'scheduled_at' => $scheduledAt,
        ]);

        // Create post_platforms for each connected account
        foreach ($socialAccounts as $account) {
            $post->postPlatforms()->create([
                'social_account_id' => $account->id,
                'platform' => $account->platform->value,
                'content' => '',
                'content_type' => ContentType::defaultFor($account->platform),
                'status' => 'pending',
                'enabled' => true,
            ]);
        }

        return redirect()->route('posts.edit', $post);
    }

    public function edit(Request $request, Post $post): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('view', $workspace);

        if ($post->workspace_id !== $workspace->id) {
            abort(404);
        }

        $post->load(['postPlatforms.socialAccount', 'postPlatforms.media']);
        $socialAccounts = $workspace->socialAccounts;

        $platformConfigs = $socialAccounts->mapWithKeys(function ($account) {
            $platform = $account->platform;

            return [
                $account->id => [
                    'maxContentLength' => $platform->maxContentLength(),
                    'maxImages' => $platform->maxImages(),
                    'allowedMediaTypes' => array_map(fn ($type) => $type->value, $platform->allowedMediaTypes()),
                    'supportsTextOnly' => $platform->supportsTextOnly(),
                ],
            ];
        });

        // Fetch Pinterest boards if Pinterest account exists
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
        ]);
    }

    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('view', $workspace);

        if ($post->workspace_id !== $workspace->id) {
            abort(404);
        }

        if ($post->status === PostStatus::Published) {
            session()->flash('flash.banner', __('posts.flash.cannot_edit_published'));
            session()->flash('flash.bannerStyle', 'danger');

            return back();
        }

        $scheduledAt = $post->scheduled_at;
        if ($request->has('scheduled_at') && $request->input('scheduled_at')) {
            $scheduledAt = Carbon::parse($request->input('scheduled_at'), $workspace->timezone)->utc();
        }

        $status = $request->input('status', $post->status);

        $post->update([
            'status' => $status === 'publishing' ? PostStatus::Publishing : $status,
            'synced' => $request->input('synced', $post->synced),
            'scheduled_at' => $scheduledAt,
        ]);

        // Get selected platform IDs
        $selectedPlatformIds = collect($request->input('platforms', []))->pluck('id')->toArray();

        // Update all platforms - disable those not selected, update content for selected ones
        $post->postPlatforms()->update(['enabled' => false]);

        foreach ($request->input('platforms', []) as $platformData) {
            $updateData = [
                'enabled' => true,
                'content' => $platformData['content'],
                'content_type' => $platformData['content_type'] ?? null,
            ];

            if (isset($platformData['meta'])) {
                $postPlatform = $post->postPlatforms()->where('id', $platformData['id'])->first();
                $updateData['meta'] = array_merge($postPlatform->meta ?? [], $platformData['meta']);
            }

            $post->postPlatforms()
                ->where('id', $platformData['id'])
                ->update($updateData);
        }

        // Dispatch publish job if publishing now
        if ($status === 'publishing') {
            PublishPost::dispatch($post);

            session()->flash('flash.banner', __('posts.flash.publishing'));
            session()->flash('flash.bannerStyle', 'success');

            return redirect()->route('posts.edit', $post);
        }

        // Redirect to show page for schedule action
        if ($status === 'scheduled') {
            session()->flash('flash.banner', __('posts.flash.scheduled'));
            session()->flash('flash.bannerStyle', 'success');

            return redirect()->route('posts.edit', $post);
        }

        return back();
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('view', $workspace);

        if ($post->workspace_id !== $workspace->id) {
            abort(404);
        }

        $post->delete();

        session()->flash('flash.banner', __('posts.flash.deleted'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }
}
