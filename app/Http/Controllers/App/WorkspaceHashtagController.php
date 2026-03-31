<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Hashtag\CreateHashtag;
use App\Actions\Hashtag\DeleteHashtag;
use App\Actions\Hashtag\UpdateHashtag;
use App\Models\WorkspaceHashtag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceHashtagController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('view', $workspace);

        $hashtags = $workspace->hashtags()
            ->when($request->input('search'), fn ($query, $search) => $query->where('name', 'ilike', "%{$search}%"))
            ->latest()
            ->paginate(config('app.pagination.default'));

        return Inertia::render('hashtags/Index', [
            'workspace' => $workspace,
            'hashtags' => Inertia::scroll(fn () => $hashtags),
            'filters' => [
                'search' => $request->input('search', ''),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('view', $workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'hashtags' => ['required', 'string'],
        ]);

        CreateHashtag::execute($workspace, $validated);

        session()->flash('flash.banner', __('hashtags.flash.created'));
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('app.hashtags.index');
    }

    public function update(Request $request, WorkspaceHashtag $hashtag): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('view', $workspace);

        if ($hashtag->workspace_id !== $workspace->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'hashtags' => ['required', 'string'],
        ]);

        UpdateHashtag::execute($hashtag, $validated);

        session()->flash('flash.banner', __('hashtags.flash.updated'));
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('app.hashtags.index');
    }

    public function destroy(Request $request, WorkspaceHashtag $hashtag): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('view', $workspace);

        if ($hashtag->workspace_id !== $workspace->id) {
            abort(404);
        }

        DeleteHashtag::execute($hashtag);

        session()->flash('flash.banner', __('hashtags.flash.deleted'));
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('app.hashtags.index');
    }
}
