<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Models\AccessToken;
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

        $tokens = AccessToken::where('user_id', $request->user()->id)
            ->where('workspace_id', $workspace->id)
            ->where('revoked', false)
            ->latest()
            ->get()
            ->map(fn (AccessToken $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at,
                'expires_at' => $token->expires_at,
                'created_at' => $token->created_at,
            ]);

        return Inertia::render('settings/workspace/ApiKeys', [
            'workspace' => $workspace,
            'apiTokens' => $tokens,
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

        $result = $request->user()->createToken($validated['name']);
        $accessToken = AccessToken::find($result->token->id);
        $accessToken->forceFill([
            'workspace_id' => $workspace->id,
            'expires_at' => $validated['expires_at'] ?? null,
        ])->saveQuietly();

        return back()
            ->with('flash.success', __('settings.api_keys.flash.created'))
            ->with('flash.plainToken', $result->accessToken);
    }

    public function destroy(Request $request, string $tokenId): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageTeam', $workspace);

        $token = AccessToken::where('id', $tokenId)
            ->where('user_id', $request->user()->id)
            ->where('workspace_id', $workspace->id)
            ->first();

        if (! $token) {
            abort(404);
        }

        $token->forceFill(['revoked' => true])->saveQuietly();

        return back()->with('flash.success', __('settings.api_keys.flash.deleted'));
    }
}
