<?php

declare(strict_types=1);

namespace App\Http\Middleware\App;

use App\Http\Resources\App\HandleInertiaRequests\AuthUserResource;
use App\Http\Resources\App\HandleInertiaRequests\AuthWorkspaceResource;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        if ($user?->language) {
            App::setLocale($user->language->code);
        }

        $currentWorkspace = $user?->currentWorkspace;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? AuthUserResource::make($user) : null,
                'currentWorkspace' => $currentWorkspace ? AuthWorkspaceResource::make($currentWorkspace, $user) : null,
                'workspaces' => $user
                    ? $user->workspaces()->get()->map(fn ($ws) => AuthWorkspaceResource::summary($ws))
                    : [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => $request->session()->get('flash', []),
            'applicationUrl' => config('app.url'),
            'env' => config('app.env'),
            'locale' => app()->getLocale(),
            'languages' => fn () => Cache::remember('languages:public', 3600, fn () => Language::query()->orderBy('name')->get()->toArray()),
            'selfHosted' => config('trypost.self_hosted'),
        ];
    }
}
