<?php

use App\Http\Controllers\Auth\FacebookController;
use App\Http\Controllers\Auth\InstagramController;
use App\Http\Controllers\Auth\LinkedInController;
use App\Http\Controllers\Auth\LinkedInPageController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\Auth\ThreadsController;
use App\Http\Controllers\Auth\TikTokController;
use App\Http\Controllers\Auth\XController;
use App\Http\Controllers\Auth\YouTubeController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceInviteController;
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

Route::get('dashboard', function () {
    return redirect()->route('workspaces.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    // Workspaces
    Route::resource('workspaces', WorkspaceController::class);
    Route::get('workspaces/{workspace}/settings', [WorkspaceController::class, 'settings'])
        ->name('workspaces.settings');
    Route::put('workspaces/{workspace}/settings', [WorkspaceController::class, 'updateSettings'])
        ->name('workspaces.settings.update');

    // Social Accounts
    Route::get('workspaces/{workspace}/accounts', [SocialController::class, 'index'])
        ->name('workspaces.accounts');
    Route::delete('workspaces/{workspace}/accounts/{account}', [SocialController::class, 'disconnect'])
        ->name('workspaces.disconnect');

    // LinkedIn
    Route::get('workspaces/{workspace}/connect/linkedin', [LinkedInController::class, 'connect'])
        ->name('social.linkedin.connect');
    Route::get('accounts/linkedin/callback', [LinkedInController::class, 'callback'])
        ->name('social.linkedin.callback');

    // LinkedIn Page
    Route::get('workspaces/{workspace}/connect/linkedin-page', [LinkedInPageController::class, 'connect'])
        ->name('social.linkedin-page.connect');
    Route::get('accounts/linkedin-page/callback', [LinkedInPageController::class, 'callback'])
        ->name('social.linkedin-page.callback');
    Route::get('accounts/linkedin-page/select', [LinkedInPageController::class, 'selectPage'])
        ->name('social.linkedin-page.select-page');
    Route::post('accounts/linkedin-page/select', [LinkedInPageController::class, 'select'])
        ->name('social.linkedin-page.select');

    // X (Twitter)
    Route::get('workspaces/{workspace}/connect/x', [XController::class, 'connect'])
        ->name('social.x.connect');
    Route::get('accounts/x/callback', [XController::class, 'callback'])
        ->name('social.x.callback');

    // TikTok
    Route::get('workspaces/{workspace}/connect/tiktok', [TikTokController::class, 'connect'])
        ->name('social.tiktok.connect');
    Route::get('accounts/tiktok/callback', [TikTokController::class, 'callback'])
        ->name('social.tiktok.callback');

    // YouTube
    Route::get('workspaces/{workspace}/connect/youtube', [YouTubeController::class, 'connect'])
        ->name('social.youtube.connect');
    Route::get('accounts/youtube/callback', [YouTubeController::class, 'callback'])
        ->name('social.youtube.callback');
    Route::get('accounts/youtube/select', [YouTubeController::class, 'selectChannel'])
        ->name('social.youtube.select-channel');
    Route::post('accounts/youtube/select', [YouTubeController::class, 'select'])
        ->name('social.youtube.select');

    // Facebook
    Route::get('workspaces/{workspace}/connect/facebook', [FacebookController::class, 'connect'])
        ->name('social.facebook.connect');
    Route::get('accounts/facebook/callback', [FacebookController::class, 'callback'])
        ->name('social.facebook.callback');
    Route::get('accounts/facebook/select', [FacebookController::class, 'selectPage'])
        ->name('social.facebook.select-page');
    Route::post('accounts/facebook/select', [FacebookController::class, 'select'])
        ->name('social.facebook.select');

    // Instagram
    Route::get('workspaces/{workspace}/connect/instagram', [InstagramController::class, 'connect'])
        ->name('social.instagram.connect');
    Route::get('accounts/instagram/callback', [InstagramController::class, 'callback'])
        ->name('social.instagram.callback');
    Route::get('accounts/instagram/select', [InstagramController::class, 'selectAccount'])
        ->name('social.instagram.select-account');
    Route::post('accounts/instagram/select', [InstagramController::class, 'select'])
        ->name('social.instagram.select');

    // Threads
    Route::get('workspaces/{workspace}/connect/threads', [ThreadsController::class, 'connect'])
        ->name('social.threads.connect');
    Route::get('accounts/threads/callback', [ThreadsController::class, 'callback'])
        ->name('social.threads.callback');

    // Calendar
    Route::get('workspaces/{workspace}/calendar', [PostController::class, 'calendar'])
        ->name('workspaces.calendar');

    // Posts
    Route::resource('workspaces.posts', PostController::class);

    // Media
    Route::post('media', [MediaController::class, 'store'])->name('media.store');
    Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

    // Workspace Invites & Members
    Route::get('workspaces/{workspace}/members', [WorkspaceInviteController::class, 'index'])
        ->name('workspaces.members');
    Route::post('workspaces/{workspace}/invites', [WorkspaceInviteController::class, 'store'])
        ->name('workspaces.invites.store');
    Route::delete('workspaces/{workspace}/invites/{invite}', [WorkspaceInviteController::class, 'destroy'])
        ->name('workspaces.invites.destroy');
    Route::delete('workspaces/{workspace}/members/{user}', [WorkspaceInviteController::class, 'removeMember'])
        ->name('workspaces.members.remove');

    // Billing
    Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::get('billing/portal', [BillingController::class, 'portal'])->name('billing.portal');
});

// Accept invite (accessible with or without auth)
Route::get('invites/{token}/accept', [WorkspaceInviteController::class, 'accept'])
    ->name('invites.accept');

require __DIR__.'/settings.php';
