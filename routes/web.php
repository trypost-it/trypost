<?php

use App\Http\Controllers\Auth\BlueskyController;
use App\Http\Controllers\Auth\FacebookController;
use App\Http\Controllers\Auth\InstagramController;
use App\Http\Controllers\Auth\LinkedInController;
use App\Http\Controllers\Auth\LinkedInPageController;
use App\Http\Controllers\Auth\MastodonController;
use App\Http\Controllers\Auth\PinterestController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\Auth\ThreadsController;
use App\Http\Controllers\Auth\TikTokController;
use App\Http\Controllers\Auth\XController;
use App\Http\Controllers\Auth\YouTubeController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceHashtagController;
use App\Http\Controllers\WorkspaceInviteController;
use App\Http\Controllers\WorkspaceLabelController;
use App\Http\Middleware\EnsureUserSetupIsComplete;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('/privacy', function () {
    return Inertia::render('legal/Privacy');
})->name('privacy');

Route::get('/terms', function () {
    return Inertia::render('legal/Terms');
})->name('terms');

// Subscription selection (requires auth but not subscription)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('subscribe', [BillingController::class, 'subscribe'])->name('subscribe');
    Route::post('billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::get('billing/processing', [BillingController::class, 'processing'])->name('billing.processing');
});

// Onboarding routes (requires auth but not subscription or setup complete)
Route::middleware(['auth', 'verified'])->prefix('onboarding')->group(function () {
    Route::get('step1', [OnboardingController::class, 'step1'])->name('onboarding.step1');
    Route::post('step1', [OnboardingController::class, 'storeStep1'])->name('onboarding.step1.store');
    Route::get('step2', [OnboardingController::class, 'step2'])->name('onboarding.step2');
    Route::post('step2', [OnboardingController::class, 'storeStep2'])->name('onboarding.step2.store');
    Route::get('complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
});

// Social Connect routes (requires auth but not subscription - needed for onboarding)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('connect/linkedin', [LinkedInController::class, 'connect'])->name('social.linkedin.connect');
    Route::get('accounts/linkedin/callback', [LinkedInController::class, 'callback'])->name('social.linkedin.callback');

    Route::get('connect/linkedin-page', [LinkedInPageController::class, 'connect'])->name('social.linkedin-page.connect');
    Route::get('accounts/linkedin-page/callback', [LinkedInPageController::class, 'callback'])->name('social.linkedin-page.callback');
    Route::get('accounts/linkedin-page/select', [LinkedInPageController::class, 'selectPage'])->name('social.linkedin-page.select-page');
    Route::post('accounts/linkedin-page/select', [LinkedInPageController::class, 'select'])->name('social.linkedin-page.select');

    Route::get('connect/x', [XController::class, 'connect'])->name('social.x.connect');
    Route::get('accounts/x/callback', [XController::class, 'callback'])->name('social.x.callback');

    Route::get('connect/tiktok', [TikTokController::class, 'connect'])->name('social.tiktok.connect');
    Route::get('accounts/tiktok/callback', [TikTokController::class, 'callback'])->name('social.tiktok.callback');

    Route::get('connect/youtube', [YouTubeController::class, 'connect'])->name('social.youtube.connect');
    Route::get('accounts/youtube/callback', [YouTubeController::class, 'callback'])->name('social.youtube.callback');
    Route::get('accounts/youtube/select', [YouTubeController::class, 'selectChannel'])->name('social.youtube.select-channel');
    Route::post('accounts/youtube/select', [YouTubeController::class, 'select'])->name('social.youtube.select');

    Route::get('connect/facebook', [FacebookController::class, 'connect'])->name('social.facebook.connect');
    Route::get('accounts/facebook/callback', [FacebookController::class, 'callback'])->name('social.facebook.callback');
    Route::get('accounts/facebook/select', [FacebookController::class, 'selectPage'])->name('social.facebook.select-page');
    Route::post('accounts/facebook/select', [FacebookController::class, 'select'])->name('social.facebook.select');

    Route::get('connect/instagram', [InstagramController::class, 'connect'])->name('social.instagram.connect');
    Route::get('accounts/instagram/callback', [InstagramController::class, 'callback'])->name('social.instagram.callback');
    Route::get('accounts/instagram/select', [InstagramController::class, 'selectAccount'])->name('social.instagram.select-account');
    Route::post('accounts/instagram/select', [InstagramController::class, 'select'])->name('social.instagram.select');

    Route::get('connect/threads', [ThreadsController::class, 'connect'])->name('social.threads.connect');
    Route::get('accounts/threads/callback', [ThreadsController::class, 'callback'])->name('social.threads.callback');

    Route::get('connect/pinterest', [PinterestController::class, 'connect'])->name('social.pinterest.connect');
    Route::get('accounts/pinterest/callback', [PinterestController::class, 'callback'])->name('social.pinterest.callback');

    Route::get('connect/bluesky', [BlueskyController::class, 'connect'])->name('social.bluesky.connect');
    Route::post('connect/bluesky', [BlueskyController::class, 'store'])->name('social.bluesky.store');

    Route::get('connect/mastodon', [MastodonController::class, 'connect'])->name('social.mastodon.connect');
    Route::post('connect/mastodon', [MastodonController::class, 'authorizeInstance'])->name('social.mastodon.authorize');
    Route::get('accounts/mastodon/callback', [MastodonController::class, 'callback'])->name('social.mastodon.callback');
});

// Routes that require active subscription and completed onboarding
Route::middleware(['auth', 'verified', 'subscribed', EnsureUserSetupIsComplete::class])->group(function () {
    // Workspaces management
    Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::get('workspaces/create', [WorkspaceController::class, 'create'])->name('workspaces.create');
    Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::post('workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');
    Route::delete('workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');

    // Current workspace settings
    Route::get('settings/workspace', [WorkspaceController::class, 'settings'])->name('workspace.settings');
    Route::put('settings/workspace', [WorkspaceController::class, 'updateSettings'])->name('workspace.settings.update');

    // Social Accounts
    Route::get('accounts', [SocialController::class, 'index'])->name('accounts');
    Route::delete('accounts/{account}', [SocialController::class, 'disconnect'])->name('accounts.disconnect');

    // Calendar
    Route::get('calendar', [PostController::class, 'calendar'])->name('calendar');

    // Posts
    Route::get('posts', [PostController::class, 'index'])->name('posts.index');
    Route::get('posts/create', [PostController::class, 'create'])->name('posts.create');
    Route::post('posts', [PostController::class, 'store'])->name('posts.store');
    Route::get('posts/{post}', [PostController::class, 'show'])->name('posts.show');
    Route::get('posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::put('posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

    // Media
    Route::post('medias', [MediaController::class, 'store'])->name('medias.store');
    Route::post('medias/chunked', [MediaController::class, 'storeChunked'])->name('medias.store-chunked');
    Route::post('medias/{media}/duplicate', [MediaController::class, 'duplicate'])->name('medias.duplicate');
    Route::delete('medias/{modelId}/{media}', [MediaController::class, 'destroy'])->name('medias.destroy');

    // Members (Settings)
    Route::get('settings/members', [WorkspaceInviteController::class, 'index'])->name('members');
    Route::post('settings/members/invites', [WorkspaceInviteController::class, 'store'])->name('invites.store');
    Route::delete('settings/members/invites/{invite}', [WorkspaceInviteController::class, 'destroy'])->name('invites.destroy');
    Route::delete('settings/members/{user}', [WorkspaceInviteController::class, 'removeMember'])->name('members.remove');

    // Hashtags
    Route::get('hashtags', [WorkspaceHashtagController::class, 'index'])->name('hashtags.index');
    Route::post('hashtags', [WorkspaceHashtagController::class, 'store'])->name('hashtags.store');
    Route::put('hashtags/{hashtag}', [WorkspaceHashtagController::class, 'update'])->name('hashtags.update');
    Route::delete('hashtags/{hashtag}', [WorkspaceHashtagController::class, 'destroy'])->name('hashtags.destroy');

    // Labels
    Route::get('labels', [WorkspaceLabelController::class, 'index'])->name('labels.index');
    Route::post('labels', [WorkspaceLabelController::class, 'store'])->name('labels.store');
    Route::put('labels/{label}', [WorkspaceLabelController::class, 'update'])->name('labels.update');
    Route::delete('labels/{label}', [WorkspaceLabelController::class, 'destroy'])->name('labels.destroy');

    // Billing
    Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('billing/portal', [BillingController::class, 'portal'])->name('billing.portal');
});

// Invite routes (accessible with or without auth)
Route::get('invites/{token}/accept', [WorkspaceInviteController::class, 'show'])
    ->name('invites.show');
Route::post('invites/{token}/accept', [WorkspaceInviteController::class, 'accept'])
    ->name('invites.accept');

require __DIR__.'/settings.php';
