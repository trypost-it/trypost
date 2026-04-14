<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\SocialAccount\Platform;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Services\Social\FacebookAnalytics;
use App\Services\Social\InstagramAnalytics;
use App\Services\Social\LinkedInPageAnalytics;
use App\Services\Social\PinterestAnalytics;
use App\Services\Social\ThreadsAnalytics;
use App\Services\Social\TikTokAnalytics;
use App\Services\Social\XAnalytics;
use App\Services\Social\YouTubeAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AnalyticsController extends Controller
{
    private const SUPPORTED_PLATFORMS = [
        Platform::TikTok,
        Platform::Instagram,
        Platform::InstagramFacebook,
        Platform::Threads,
        Platform::Facebook,
        Platform::X,
        Platform::LinkedInPage,
        Platform::Pinterest,
        Platform::YouTube,
    ];

    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        $accounts = $workspace->socialAccounts()
            ->where('is_active', true)
            ->whereIn('platform', self::SUPPORTED_PLATFORMS)
            ->get()
            ->map(fn (SocialAccount $account) => [
                'id' => $account->id,
                'platform' => $account->platform->value,
                'display_name' => $account->display_name,
                'username' => $account->username,
                'avatar_url' => $account->avatar_url,
            ]);

        return Inertia::render('analytics/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function show(Request $request, SocialAccount $account): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if ($account->workspace_id !== $workspace->id) {
            abort(HttpResponse::HTTP_FORBIDDEN);
        }

        $since = $request->has('since') ? Carbon::parse($request->input('since')) : null;
        $until = $request->has('until') ? Carbon::parse($request->input('until')) : null;

        $metrics = match ($account->platform) {
            Platform::TikTok => app(TikTokAnalytics::class)->getMetrics($account),
            Platform::Instagram, Platform::InstagramFacebook => app(InstagramAnalytics::class)->getMetrics($account, $since, $until),
            Platform::Threads => app(ThreadsAnalytics::class)->getMetrics($account, $since, $until),
            Platform::Facebook => app(FacebookAnalytics::class)->getMetrics($account, $since, $until),
            Platform::X => app(XAnalytics::class)->getMetrics($account, $since, $until),
            Platform::LinkedInPage => app(LinkedInPageAnalytics::class)->getMetrics($account, $since, $until),
            Platform::Pinterest => app(PinterestAnalytics::class)->getMetrics($account, $since, $until),
            Platform::YouTube => app(YouTubeAnalytics::class)->getMetrics($account, $since, $until),
            default => [],
        };

        return response()->json(['metrics' => $metrics]);
    }
}
