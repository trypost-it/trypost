<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\HashtagController;
use App\Http\Controllers\Api\LabelController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::group(
    [
        'domain' => 'api.'.parse_url(config('app.url'), PHP_URL_HOST),
        'middleware' => ['api.auth', 'throttle:api'],
    ],
    function () {
        // Posts
        Route::get('/posts', [PostController::class, 'index'])->name('api.posts.index');
        Route::post('/posts', [PostController::class, 'store'])->name('api.posts.store');
        Route::get('/posts/{post}', [PostController::class, 'show'])->name('api.posts.show');
        Route::put('/posts/{post}', [PostController::class, 'update'])->name('api.posts.update');
        Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('api.posts.destroy');

        // Workspace
        Route::get('/workspace', [WorkspaceController::class, 'show'])->name('api.workspace.show');

        // Hashtags
        Route::get('/hashtags', [HashtagController::class, 'index'])->name('api.hashtags.index');
        Route::post('/hashtags', [HashtagController::class, 'store'])->name('api.hashtags.store');
        Route::put('/hashtags/{hashtag}', [HashtagController::class, 'update'])->name('api.hashtags.update');
        Route::delete('/hashtags/{hashtag}', [HashtagController::class, 'destroy'])->name('api.hashtags.destroy');

        // Labels
        Route::get('/labels', [LabelController::class, 'index'])->name('api.labels.index');
        Route::post('/labels', [LabelController::class, 'store'])->name('api.labels.store');
        Route::put('/labels/{label}', [LabelController::class, 'update'])->name('api.labels.update');
        Route::delete('/labels/{label}', [LabelController::class, 'destroy'])->name('api.labels.destroy');

        // API Keys
        Route::get('/api-keys', [ApiKeyController::class, 'index'])->name('api.api-keys.index');
        Route::post('/api-keys', [ApiKeyController::class, 'store'])->name('api.api-keys.store');
        Route::delete('/api-keys/{apiToken}', [ApiKeyController::class, 'destroy'])->name('api.api-keys.destroy');
    }
);
