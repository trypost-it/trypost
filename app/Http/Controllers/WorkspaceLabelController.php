<?php

namespace App\Http\Controllers;

use App\Models\WorkspaceLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceLabelController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('view', $workspace);

        return Inertia::render('labels/Index', [
            'workspace' => $workspace,
            'labels' => $workspace->labels()->latest()->get(),
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
            'color' => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $workspace->labels()->create($validated);

        session()->flash('flash.banner', 'Label created successfully!');
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('labels.index');
    }

    public function update(Request $request, WorkspaceLabel $label): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('view', $workspace);

        if ($label->workspace_id !== $workspace->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $label->update($validated);

        session()->flash('flash.banner', 'Label updated successfully!');
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('labels.index');
    }

    public function destroy(Request $request, WorkspaceLabel $label): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('view', $workspace);

        if ($label->workspace_id !== $workspace->id) {
            abort(404);
        }

        $label->delete();

        session()->flash('flash.banner', 'Label deleted successfully!');
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('labels.index');
    }
}
