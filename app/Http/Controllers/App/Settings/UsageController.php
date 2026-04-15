<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Features\AiImagesLimit;
use App\Features\AiVideosLimit;
use App\Features\DataRetentionDays;
use App\Features\MemberLimit;
use App\Features\SocialAccountLimit;
use App\Features\WorkspaceLimit;
use App\Http\Controllers\App\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UsageController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isAccountOwner(), SymfonyResponse::HTTP_FORBIDDEN);

        $account = $request->user()->account;

        $totalSocialAccounts = 0;
        $totalMembers = $account->users()->count();

        foreach ($account->workspaces as $workspace) {
            $totalSocialAccounts += $workspace->socialAccounts()->count();
        }

        return Inertia::render('settings/Usage', [
            'plan' => $account->plan,
            'usage' => [
                'workspaceCount' => $account->workspaces()->count(),
                'workspaceLimit' => Feature::for($account)->value(WorkspaceLimit::class),
                'socialAccountCount' => $totalSocialAccounts,
                'socialAccountLimit' => Feature::for($account)->value(SocialAccountLimit::class),
                'memberCount' => $totalMembers,
                'memberLimit' => Feature::for($account)->value(MemberLimit::class),
                'aiImagesUsed' => 0,
                'aiImagesLimit' => Feature::for($account)->value(AiImagesLimit::class),
                'aiVideosUsed' => 0,
                'aiVideosLimit' => Feature::for($account)->value(AiVideosLimit::class),
                'dataRetentionDays' => Feature::for($account)->value(DataRetentionDays::class),
            ],
        ]);
    }
}
