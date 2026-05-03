<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\ApiKey\CreateApiKey;
use App\Actions\ApiKey\DeleteApiKey;
use App\Models\ApiToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiKeyController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageTeam', $workspace);

        return Inertia::render('settings/workspace/ApiKeys', [
            'workspace' => $workspace,
            'apiTokens' => $workspace->apiTokens()->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageTeam', $workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $result = CreateApiKey::execute($workspace, $validated);

        session()->flash('flash.banner', __('settings.api_keys.flash.created'));
        session()->flash('flash.bannerStyle', 'success');
        session()->flash('flash.plainToken', data_get($result, 'plain_token'));

        return back();
    }

    public function destroy(Request $request, ApiToken $apiToken): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageTeam', $workspace);

        if ($apiToken->workspace_id !== $workspace->id) {
            abort(404);
        }

        DeleteApiKey::execute($apiToken);

        session()->flash('flash.banner', __('settings.api_keys.flash.deleted'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }
}
