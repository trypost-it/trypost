<?php

namespace App\Http\Controllers;

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
            return redirect()->route('workspaces.create');
        }

        $this->authorize('view', $workspace);

        return Inertia::render('hashtags/Index', [
            'workspace' => $workspace,
            'hashtags' => $workspace->hashtags()->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('view', $workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'hashtags' => ['required', 'string'],
        ]);

        $workspace->hashtags()->create($validated);

        session()->flash('flash.banner', 'Hashtag group created successfully!');
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('hashtags.index');
    }

    public function update(Request $request, WorkspaceHashtag $hashtag): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('view', $workspace);

        if ($hashtag->workspace_id !== $workspace->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'hashtags' => ['required', 'string'],
        ]);

        $hashtag->update($validated);

        session()->flash('flash.banner', 'Hashtag group updated successfully!');
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('hashtags.index');
    }

    public function destroy(Request $request, WorkspaceHashtag $hashtag): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('view', $workspace);

        if ($hashtag->workspace_id !== $workspace->id) {
            abort(404);
        }

        $hashtag->delete();

        session()->flash('flash.banner', 'Hashtag group deleted successfully!');
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('hashtags.index');
    }
}
