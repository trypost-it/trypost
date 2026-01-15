<?php

use App\Http\Controllers\Auth\LinkedInController;
use App\Http\Controllers\Auth\LinkedInPageController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\Auth\TikTokController;
use App\Http\Controllers\Auth\XController;
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

Route::get('dashboard', function () {
    return redirect()->route('workspaces.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    // Workspaces
    Route::resource('workspaces', WorkspaceController::class);

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
