<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BibleAnnotationController;
use App\Http\Controllers\Api\BibleController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DailyVerseController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\ModerationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReactionController;
use App\Http\Controllers\Api\TestimonyController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes v1 — TestiApp
|--------------------------------------------------------------------------
| Base URL : /api/v1
| Auth     : Laravel Sanctum (Bearer token)
| Format   : { success, data, message, errors? }
*/

Route::prefix('v1')->group(function () {

    // ── Auth (public) ───────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('login',           [AuthController::class, 'login']);
        Route::post('register',        [AuthController::class, 'register']);
        Route::post('phone',           [AuthController::class, 'phoneAuth']);
        Route::post('social',          [AuthController::class, 'socialAuth']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    });

    // ── Verset du jour (public + authentifié) ────────────────────────────
    Route::get('daily-verse', [DailyVerseController::class, 'today']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post  ('daily-verse/react',  [DailyVerseController::class, 'react']);
        Route::delete('daily-verse/react',  [DailyVerseController::class, 'unreact']);
        Route::post  ('daily-verse/share',  [DailyVerseController::class, 'share']);
    });

    // ── Bible (public) ──────────────────────────────────────────────────
    Route::prefix('bible')->group(function () {
        Route::get('translations',                          [BibleController::class, 'translations']);
        Route::get('download/{translation}',                [BibleController::class, 'download']);
        Route::get('books',                                 [BibleController::class, 'books']);
        Route::get('search',                                [BibleController::class, 'search']);
        Route::get('{book}/{chapter}/{verse}/share',        [BibleAnnotationController::class, 'shareVerse']);
        Route::get('{book}/{chapter}/{verse}',              [BibleController::class, 'verse']);
        Route::get('{book}/{chapter}',                      [BibleController::class, 'chapter']); // annotations incluses si auth

        // ── Annotations (authentifié) ──────────────────────────────────
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('{book}/{chapter}/annotations',      [BibleAnnotationController::class, 'chapter']);

            Route::get('bookmarks',                         [BibleAnnotationController::class, 'bookmarks']);
            Route::post('bookmarks',                        [BibleAnnotationController::class, 'storeBookmark']);
            Route::delete('bookmarks/{id}',                 [BibleAnnotationController::class, 'destroyBookmark']);

            Route::post('highlights',                       [BibleAnnotationController::class, 'storeHighlight']);
            Route::delete('highlights/{id}',                [BibleAnnotationController::class, 'destroyHighlight']);

            Route::post('notes',                            [BibleAnnotationController::class, 'storeNote']);
            Route::delete('notes/{id}',                     [BibleAnnotationController::class, 'destroyNote']);
        });
    });

    // ── Categories (public) ─────────────────────────────────────────────
    Route::get('categories',        [CategoryController::class, 'index']);
    Route::get('categories/{slug}', [CategoryController::class, 'show']);

    // ── Testimonies (public read) ────────────────────────────────────────
    Route::get('testimonies',           [TestimonyController::class, 'index']);
    Route::get('testimonies/featured',  [TestimonyController::class, 'featured']);
    Route::get('testimonies/{id}',      [TestimonyController::class, 'show']);
    Route::get('testimonies/{id}/comments',  [CommentController::class, 'index']);
    Route::get('testimonies/{id}/reactions', [ReactionController::class, 'index']);
    Route::get('users/{id}',           [UserController::class, 'show']);
    Route::get('users/{id}/testimonies', [UserController::class, 'testimonies']);

    // ── Authenticated routes ─────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::post('auth/logout',  [AuthController::class, 'logout']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::get('auth/me',       [AuthController::class, 'me']);

        // Testimonies (write)
        Route::post('testimonies',         [TestimonyController::class, 'store']);
        Route::put('testimonies/{id}',     [TestimonyController::class, 'update']);
        Route::delete('testimonies/{id}',  [TestimonyController::class, 'destroy']);
        Route::put('testimonies/{id}/save',   [TestimonyController::class, 'save']);
        Route::delete('testimonies/{id}/unsave', [TestimonyController::class, 'unsave']);
        Route::get('testimonies/saved/list',  [TestimonyController::class, 'saved']);

        // Reactions
        Route::post('testimonies/{id}/reactions', [ReactionController::class, 'store']);
        Route::delete('testimonies/{id}/reactions/{reactionId}', [ReactionController::class, 'destroy']);

        // Comments
        Route::post('testimonies/{id}/comments', [CommentController::class, 'store']);
        Route::put('comments/{id}',              [CommentController::class, 'update']);
        Route::delete('comments/{id}',           [CommentController::class, 'destroy']);

        // Users (self management)
        Route::put('users/me',            [UserController::class, 'updateMe']);
        Route::post('users/me/avatar',    [UserController::class, 'uploadAvatar']);
        Route::put('users/me/settings',   [UserController::class, 'updateSettings']);
        Route::delete('users/me',         [UserController::class, 'deleteAccount']);
        Route::post('users/{id}/follow',  [UserController::class, 'follow']);
        Route::delete('users/{id}/unfollow', [UserController::class, 'unfollow']);

        // Notifications
        Route::get('notifications',              [NotificationController::class, 'index']);
        Route::post('notifications/{id}/read',   [NotificationController::class, 'markRead']);
        Route::post('notifications/read-all',    [NotificationController::class, 'markAllRead']);

        // Media
        Route::post('media/upload',              [MediaController::class, 'upload']);
        Route::get('media/presigned-url',        [MediaController::class, 'presignedUrl']);

        // Moderation (moderateur + admin)
        Route::middleware('role:moderateur,administrateur')->prefix('moderation')->group(function () {
            Route::get('stats',                   [ModerationController::class, 'stats']);
            Route::get('pending',                 [ModerationController::class, 'index']);
            Route::get('{id}',                    [ModerationController::class, 'show']);
            Route::post('{id}/approve',           [ModerationController::class, 'approve']);
            Route::post('{id}/reject',            [ModerationController::class, 'reject']);
        });

        // Admin (admin only)
        Route::middleware('role:administrateur')->prefix('admin')->group(function () {
            Route::get('stats',                   [AdminController::class, 'stats']);
            Route::get('users',                   [AdminController::class, 'users']);
            Route::get('users/{id}',              [AdminController::class, 'showUser']);
            Route::post('users/{id}/ban',         [AdminController::class, 'banUser']);
            Route::post('users/{id}/suspend',     [AdminController::class, 'suspendUser']);
            Route::post('users/{id}/activate',    [AdminController::class, 'activateUser']);
            Route::put('users/{id}/role',         [AdminController::class, 'updateUserRole']);
            Route::get('testimonies',             [AdminController::class, 'content']);
            Route::get('categories',              [AdminController::class, 'categories']);
            Route::post('categories',             [AdminController::class, 'storeCategory']);
            Route::put('categories/{id}',         [AdminController::class, 'updateCategory']);
            Route::delete('categories/{id}',      [AdminController::class, 'deleteCategory']);
            Route::get('settings',                [AdminController::class, 'settings']);
            Route::put('settings',                [AdminController::class, 'updateSettings']);
        });
    });
});
