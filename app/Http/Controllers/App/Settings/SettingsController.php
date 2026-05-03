<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Enums\UserWorkspace\Role as WorkspaceRole;
use App\Http\Controllers\App\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user?->currentWorkspace;

        $workspaceRole = $workspace
            ? ($user->isAccountOwner() && $workspace->account_id === $user->account_id
                ? 'owner'
                : $workspace->members()->where('users.id', $user->id)->first()?->pivot?->role)
            : null;

        $canManageWorkspace = $workspace && in_array(
            $workspaceRole,
            ['owner', WorkspaceRole::Admin->value],
            true,
        );

        return Inertia::render('settings/Index', [
            'permissions' => [
                'canManageProfile' => true,
                'canManageWorkspace' => $canManageWorkspace,
                'canManageAccount' => $user?->isAccountOwner() && ! config('trypost.self_hosted'),
            ],
        ]);
    }
}
