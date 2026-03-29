<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Label\CreateLabel;
use App\Actions\Label\DeleteLabel;
use App\Actions\Label\UpdateLabel;
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
            return redirect()->route('app.workspaces.create');
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
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('view', $workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        CreateLabel::execute($workspace, $validated);

        session()->flash('flash.banner', __('labels.flash.created'));
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('app.labels.index');
    }

    public function update(Request $request, WorkspaceLabel $label): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('view', $workspace);

        if ($label->workspace_id !== $workspace->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        UpdateLabel::execute($label, $validated);

        session()->flash('flash.banner', __('labels.flash.updated'));
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('app.labels.index');
    }

    public function destroy(Request $request, WorkspaceLabel $label): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('view', $workspace);

        if ($label->workspace_id !== $workspace->id) {
            abort(404);
        }

        DeleteLabel::execute($label);

        session()->flash('flash.banner', __('labels.flash.deleted'));
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('app.labels.index');
    }
}
