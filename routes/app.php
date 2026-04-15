<?php

declare(strict_types=1);

use App\Http\Controllers\App\AnalyticsController;
use App\Http\Controllers\App\ApiKeyController;
use App\Http\Controllers\App\AssetController;
use App\Http\Controllers\App\BillingController;
use App\Http\Controllers\App\GiphyController;
use App\Http\Controllers\App\MediaController;
use App\Http\Controllers\App\NotificationController;
use App\Http\Controllers\App\OnboardingController;
use App\Http\Controllers\App\PostCommentController;
use App\Http\Controllers\App\PostController;
use App\Http\Controllers\App\Settings\AccountController;
use App\Http\Controllers\App\Settings\NotificationPreferenceController;
use App\Http\Controllers\App\Settings\PasswordController;
use App\Http\Controllers\App\Settings\ProfileController;
use App\Http\Controllers\App\Settings\UsageController;
use App\Http\Controllers\App\UnsplashController;
use App\Http\Controllers\App\WorkspaceController;
use App\Http\Controllers\App\WorkspaceHashtagController;
use App\Http\Controllers\App\WorkspaceInviteController;
use App\Http\Controllers\App\WorkspaceLabelController;
use App\Http\Controllers\Auth\BlueskyController;
use App\Http\Controllers\Auth\FacebookController;
use App\Http\Controllers\Auth\InstagramController;
use App\Http\Controllers\Auth\InstagramFacebookController;
use App\Http\Controllers\Auth\LinkedInController;
use App\Http\Controllers\Auth\LinkedInPageController;
use App\Http\Controllers\Auth\MastodonController;
use App\Http\Controllers\Auth\PinterestController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\Auth\ThreadsController;
use App\Http\Controllers\Auth\TikTokController;
use App\Http\Controllers\Auth\XController;
use App\Http\Controllers\Auth\YouTubeController;
use App\Http\Middleware\App\EnsureUserSetupIsComplete;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('app.calendar');
})->name('app.home')->middleware(['auth', 'verified']);

// Subscription selection (requires auth but not subscription)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('subscribe', [BillingController::class, 'subscribe'])->name('app.subscribe');
    Route::post('billing/checkout/{plan}', [BillingController::class, 'checkout'])->name('app.billing.checkout');
    Route::get('billing/processing', [BillingController::class, 'processing'])->name('app.billing.processing');
});

// Onboarding routes
Route::middleware(['auth', 'verified'])->prefix('onboarding')->group(function () {
    Route::get('/', fn () => redirect()->route('app.onboarding.role'));
    Route::get('role', [OnboardingController::class, 'role'])->name('app.onboarding.role');
    Route::post('role', [OnboardingController::class, 'storeRole'])->name('app.onboarding.role.store');
    Route::get('account', [OnboardingController::class, 'account'])->name('app.onboarding.account');
    Route::post('account', [OnboardingController::class, 'storeAccount'])->name('app.onboarding.account.store');
});

// Social Connect routes
Route::middleware(['auth', 'verified', 'throttle:6,1'])->group(function () {
    Route::get('connect/linkedin', [LinkedInController::class, 'connect'])->name('app.social.linkedin.connect');
    Route::get('accounts/linkedin/callback', [LinkedInController::class, 'callback'])->name('app.social.linkedin.callback');

    Route::get('connect/linkedin-page', [LinkedInPageController::class, 'connect'])->name('app.social.linkedin-page.connect');
    Route::get('accounts/linkedin-page/callback', [LinkedInPageController::class, 'callback'])->name('app.social.linkedin-page.callback');
    Route::get('accounts/linkedin-page/select', [LinkedInPageController::class, 'selectPage'])->name('app.social.linkedin-page.select-page');
    Route::post('accounts/linkedin-page/select', [LinkedInPageController::class, 'select'])->name('app.social.linkedin-page.select');

    Route::get('connect/x', [XController::class, 'connect'])->name('app.social.x.connect');
    Route::get('accounts/x/callback', [XController::class, 'callback'])->name('app.social.x.callback');

    Route::get('connect/tiktok', [TikTokController::class, 'connect'])->name('app.social.tiktok.connect');
    Route::get('accounts/tiktok/callback', [TikTokController::class, 'callback'])->name('app.social.tiktok.callback');

    Route::get('connect/youtube', [YouTubeController::class, 'connect'])->name('app.social.youtube.connect');
    Route::get('accounts/youtube/callback', [YouTubeController::class, 'callback'])->name('app.social.youtube.callback');
    Route::get('accounts/youtube/select', [YouTubeController::class, 'selectChannel'])->name('app.social.youtube.select-channel');
    Route::post('accounts/youtube/select', [YouTubeController::class, 'select'])->name('app.social.youtube.select');

    Route::get('connect/facebook', [FacebookController::class, 'connect'])->name('app.social.facebook.connect');
    Route::get('accounts/facebook/callback', [FacebookController::class, 'callback'])->name('app.social.facebook.callback');
    Route::get('accounts/facebook/select', [FacebookController::class, 'selectPage'])->name('app.social.facebook.select-page');
    Route::post('accounts/facebook/select', [FacebookController::class, 'select'])->name('app.social.facebook.select');

    Route::get('connect/instagram', [InstagramController::class, 'connect'])->name('app.social.instagram.connect');
    Route::get('accounts/instagram/callback', [InstagramController::class, 'callback'])->name('app.social.instagram.callback');
    Route::get('accounts/instagram/select', [InstagramController::class, 'selectAccount'])->name('app.social.instagram.select-account');
    Route::post('accounts/instagram/select', [InstagramController::class, 'select'])->name('app.social.instagram.select');

    Route::get('connect/instagram-facebook', [InstagramFacebookController::class, 'connect'])->name('app.social.instagram-facebook.connect');
    Route::get('accounts/instagram-facebook/callback', [InstagramFacebookController::class, 'callback'])->name('app.social.instagram-facebook.callback');
    Route::get('accounts/instagram-facebook/select-page', [InstagramFacebookController::class, 'selectPage'])->name('app.social.instagram-facebook.select-page');
    Route::post('accounts/instagram-facebook/select', [InstagramFacebookController::class, 'select'])->name('app.social.instagram-facebook.select');

    Route::get('connect/threads', [ThreadsController::class, 'connect'])->name('app.social.threads.connect');
    Route::get('accounts/threads/callback', [ThreadsController::class, 'callback'])->name('app.social.threads.callback');

    Route::get('connect/pinterest', [PinterestController::class, 'connect'])->name('app.social.pinterest.connect');
    Route::get('accounts/pinterest/callback', [PinterestController::class, 'callback'])->name('app.social.pinterest.callback');

    Route::get('connect/bluesky', [BlueskyController::class, 'connect'])->name('app.social.bluesky.connect');
    Route::post('connect/bluesky', [BlueskyController::class, 'store'])->name('app.social.bluesky.store');

    Route::get('connect/mastodon', [MastodonController::class, 'connect'])->name('app.social.mastodon.connect');
    Route::post('connect/mastodon', [MastodonController::class, 'authorizeInstance'])->name('app.social.mastodon.authorize');
    Route::get('accounts/mastodon/callback', [MastodonController::class, 'callback'])->name('app.social.mastodon.callback');
});

// Routes that require active subscription and completed onboarding
Route::middleware(['auth', 'verified', 'subscribed', EnsureUserSetupIsComplete::class])->group(function () {
    // Workspaces
    Route::get('workspaces', [WorkspaceController::class, 'index'])->name('app.workspaces.index');
    Route::get('workspaces/create', [WorkspaceController::class, 'create'])->name('app.workspaces.create');
    Route::post('workspaces', [WorkspaceController::class, 'store'])->name('app.workspaces.store');
    Route::post('workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])->name('app.workspaces.switch');
    Route::delete('workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('app.workspaces.destroy');

    // Workspace settings
    Route::get('settings/workspace', [WorkspaceController::class, 'settings'])->name('app.workspace.settings');
    Route::put('settings/workspace', [WorkspaceController::class, 'updateSettings'])->name('app.workspace.settings.update');
    Route::post('settings/workspace/logo', [WorkspaceController::class, 'uploadLogo'])->name('app.workspace.upload-logo');
    Route::delete('settings/workspace/logo', [WorkspaceController::class, 'deleteLogo'])->name('app.workspace.delete-logo');

    // Social Accounts
    Route::get('accounts', [SocialController::class, 'index'])->name('app.accounts');
    Route::delete('accounts/{account}', [SocialController::class, 'disconnect'])->name('app.accounts.disconnect');
    Route::put('accounts/{account}/toggle', [SocialController::class, 'toggleActive'])->name('app.accounts.toggle');

    // Analytics
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('app.analytics');
    Route::get('analytics/{account}', [AnalyticsController::class, 'show'])->name('app.analytics.show');

    // Calendar
    Route::get('calendar', [PostController::class, 'calendar'])->name('app.calendar');

    // Posts
    Route::get('posts/{status?}', [PostController::class, 'index'])->name('app.posts.index')->where('status', 'draft|scheduled|published');
    Route::post('posts', [PostController::class, 'store'])->name('app.posts.store');
    Route::get('posts/{post}/edit', [PostController::class, 'edit'])->name('app.posts.edit');
    Route::put('posts/{post}', [PostController::class, 'update'])->name('app.posts.update');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('app.posts.destroy');

    // Post Comments
    Route::get('posts/{post}/comments', [PostCommentController::class, 'index'])->name('app.posts.comments.index');
    Route::post('posts/{post}/comments', [PostCommentController::class, 'store'])->name('app.posts.comments.store');
    Route::put('posts/{post}/comments/{comment}', [PostCommentController::class, 'update'])->name('app.posts.comments.update');
    Route::delete('posts/{post}/comments/{comment}', [PostCommentController::class, 'destroy'])->name('app.posts.comments.destroy');
    Route::post('posts/{post}/comments/{comment}/react', [PostCommentController::class, 'react'])->name('app.posts.comments.react');

    // Media
    Route::post('medias', [MediaController::class, 'store'])->name('app.medias.store');
    Route::post('medias/chunked', [MediaController::class, 'storeChunked'])->name('app.medias.store-chunked');
    Route::post('medias/reorder', [MediaController::class, 'reorder'])->name('app.medias.reorder');
    Route::post('medias/{media}/duplicate', [MediaController::class, 'duplicate'])->name('app.medias.duplicate');
    Route::delete('medias/{modelId}/{media}', [MediaController::class, 'destroy'])->name('app.medias.destroy');

    // Members
    Route::get('settings/members', fn () => redirect()->route('app.workspace.settings'))->name('app.members');
    Route::post('settings/members/invites', [WorkspaceInviteController::class, 'store'])->name('app.invites.store');
    Route::delete('settings/members/invites/{invite}', [WorkspaceInviteController::class, 'destroy'])->name('app.invites.destroy');
    Route::delete('settings/members/{user}', [WorkspaceInviteController::class, 'removeMember'])->name('app.members.remove');
    Route::put('settings/members/{user}/role', [WorkspaceInviteController::class, 'updateRole'])->name('app.members.update-role');

    // Hashtags
    Route::get('hashtags', [WorkspaceHashtagController::class, 'index'])->name('app.hashtags.index');
    Route::post('hashtags', [WorkspaceHashtagController::class, 'store'])->name('app.hashtags.store');
    Route::put('hashtags/{hashtag}', [WorkspaceHashtagController::class, 'update'])->name('app.hashtags.update');
    Route::delete('hashtags/{hashtag}', [WorkspaceHashtagController::class, 'destroy'])->name('app.hashtags.destroy');

    // Assets
    Route::get('assets', [AssetController::class, 'index'])->name('app.assets.index');
    Route::post('assets', [AssetController::class, 'store'])->name('app.assets.store');
    Route::post('assets/chunked', [AssetController::class, 'storeChunked'])->name('app.assets.store-chunked');
    Route::post('assets/from-url', [AssetController::class, 'storeFromUrl'])->name('app.assets.store-from-url');
    Route::delete('assets/{media}', [AssetController::class, 'destroy'])->name('app.assets.destroy');
    Route::get('assets/unsplash/search', [UnsplashController::class, 'search'])->name('app.assets.unsplash.search');
    Route::get('assets/unsplash/trending', [UnsplashController::class, 'trending'])->name('app.assets.unsplash.trending');
    Route::get('assets/giphy/search', [GiphyController::class, 'search'])->name('app.assets.giphy.search');
    Route::get('assets/giphy/trending', [GiphyController::class, 'trending'])->name('app.assets.giphy.trending');

    // Labels
    Route::get('labels', [WorkspaceLabelController::class, 'index'])->name('app.labels.index');
    Route::post('labels', [WorkspaceLabelController::class, 'store'])->name('app.labels.store');
    Route::put('labels/{label}', [WorkspaceLabelController::class, 'update'])->name('app.labels.update');
    Route::delete('labels/{label}', [WorkspaceLabelController::class, 'destroy'])->name('app.labels.destroy');

    // API Keys
    Route::get('api-keys', [ApiKeyController::class, 'index'])->name('app.api-keys.index');
    Route::post('api-keys', [ApiKeyController::class, 'store'])->name('app.api-keys.store');
    Route::delete('api-keys/{apiToken}', [ApiKeyController::class, 'destroy'])->name('app.api-keys.destroy');

    // Account Settings
    Route::get('settings/account', [AccountController::class, 'edit'])->name('app.account.edit');
    Route::put('settings/account', [AccountController::class, 'update'])->name('app.account.update');
    Route::get('settings/usage', [UsageController::class, 'index'])->name('app.usage.index');

    // Billing
    Route::get('settings/billing', [BillingController::class, 'index'])->name('app.billing.index');
    Route::get('settings/billing/portal', [BillingController::class, 'portal'])->name('app.billing.portal');
    Route::post('settings/billing/swap/{plan}', [BillingController::class, 'swap'])->name('app.billing.swap');

});

// Notifications (auth only, no subscription required)
Route::middleware(['auth'])->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->name('app.notifications.index');
    Route::put('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('app.notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('app.notifications.read-all');
    Route::post('notifications/archive-all', [NotificationController::class, 'archiveAll'])->name('app.notifications.archive-all');
});

// Settings (auth required)
Route::middleware(['auth'])->group(function () {
    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('app.profile.edit');
    Route::put('settings/profile', [ProfileController::class, 'update'])->name('app.profile.update');
    Route::post('settings/profile/photo', [ProfileController::class, 'uploadPhoto'])->name('app.profile.upload-photo');
    Route::delete('settings/profile/photo', [ProfileController::class, 'deletePhoto'])->name('app.profile.delete-photo');
    Route::put('settings/language', [ProfileController::class, 'updateLanguage'])->name('app.profile.language');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('app.profile.destroy');
    Route::get('settings/password', [PasswordController::class, 'edit'])->name('app.user-password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('app.user-password.update');
    Route::get('settings/notifications', [NotificationPreferenceController::class, 'edit'])->name('app.notifications.preferences');
    Route::put('settings/notifications', [NotificationPreferenceController::class, 'update'])->name('app.notifications.preferences.update');
});
