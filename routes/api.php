<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BibleAnnotationController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\BibleController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DailyVerseController;
use App\Http\Controllers\Api\LiveController;
use App\Http\Controllers\Api\LiveKitWebhookController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\ModerationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReactionController;
use App\Http\Controllers\Api\ReportController;
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
    Route::get('testimonies/my',        [TestimonyController::class, 'myTestimonies'])->middleware('auth:sanctum');
    Route::get('testimonies/{id}',      [TestimonyController::class, 'show']);
    Route::get('testimonies/{id}/comments',  [CommentController::class, 'index']);
    Route::get('testimonies/{id}/reactions', [ReactionController::class, 'index']);
    Route::get('users/{id}',           [UserController::class, 'show']);
    // Communauté : organisations et personnes à suivre (docs/fonctionnalites/abonnements.md)
    Route::get('community',            [\App\Http\Controllers\Api\CommunityController::class, 'index']);
    Route::get('users/{id}/testimonies', [UserController::class, 'testimonies']);

    // ── Témoignages en direct (lecture publique) ─ docs/fonctionnalites/lives.md
    Route::get('lives',                     [LiveController::class, 'index']);
    Route::get('lives/{id}',                [LiveController::class, 'show'])->whereUuid('id');
    Route::post('lives/{id}/viewer-token',  [LiveController::class, 'viewerToken'])->whereUuid('id');
    Route::get('lives/{id}/comments',       [LiveController::class, 'comments'])->whereUuid('id');
    Route::get('lives/{id}/stats',          [LiveController::class, 'stats'])->whereUuid('id');
    Route::get('lives/{id}/stage',          [LiveController::class, 'stage'])->whereUuid('id');
    Route::get('lives/{id}/viewers',        [LiveController::class, 'viewers'])->whereUuid('id')->middleware('throttle:30,1');
    Route::post('livekit/webhook',          LiveKitWebhookController::class)->name('livekit.webhook');

    Route::middleware('auth:sanctum')->whereUuid('id')->group(function () {
        Route::post('lives',                               [LiveController::class, 'store'])->middleware('role:moderateur,administrateur');
        Route::post('lives/{id}/host-token',               [LiveController::class, 'hostToken']);
        Route::post('lives/{id}/go-live',                  [LiveController::class, 'goLive']);
        Route::post('lives/{id}/end',                      [LiveController::class, 'end']);
        Route::post('lives/{id}/comments',                 [LiveController::class, 'storeComment']);
        Route::delete('lives/{id}/comments/{commentId}',   [LiveController::class, 'hideComment']);
        Route::post('lives/{id}/comments/{commentId}/pin', [LiveController::class, 'pinComment']);
        Route::delete('lives/{id}/pin',                    [LiveController::class, 'unpinComment']);
        Route::post('lives/{id}/bans',                     [LiveController::class, 'ban']);
        Route::post('lives/{id}/reactions',                [LiveController::class, 'react']);
        // Intervenants : docs/fonctionnalites/lives-intervenants.md
        Route::post('lives/{id}/stage/requests',             [LiveController::class, 'requestStage']);
        Route::delete('lives/{id}/stage/requests/mine',      [LiveController::class, 'withdrawStage']);
        Route::post('lives/{id}/stage/accept',               [LiveController::class, 'acceptStage']);
        Route::post('lives/{id}/stage/settings',             [LiveController::class, 'stageSettings']);
        Route::post('lives/{id}/stage/{speakerId}/invite',   [LiveController::class, 'inviteSpeaker'])->whereUuid('speakerId');
        Route::post('lives/{id}/stage/{speakerId}/decline',  [LiveController::class, 'declineSpeaker'])->whereUuid('speakerId');
        Route::post('lives/{id}/stage/{speakerId}/remove',   [LiveController::class, 'removeSpeaker'])->whereUuid('speakerId');
    });

    // ── Authenticated routes ─────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::post('auth/logout',           [AuthController::class, 'logout']);
        Route::post('auth/refresh',          [AuthController::class, 'refresh']);
        Route::get ('auth/me',               [AuthController::class, 'me']);
        Route::post('auth/change-password',  [AuthController::class, 'changePassword']);

        // Testimonies (write)
        Route::post('testimonies',         [TestimonyController::class, 'store']);
        Route::put('testimonies/{id}',     [TestimonyController::class, 'update']);
        Route::delete('testimonies/{id}',  [TestimonyController::class, 'destroy']);
        Route::put('testimonies/{id}/save',    [TestimonyController::class, 'save']);
        Route::delete('testimonies/{id}/unsave', [TestimonyController::class, 'unsave']);
        Route::get('testimonies/saved/list',   [TestimonyController::class, 'saved']);
        Route::post('testimonies/{id}/share',  [TestimonyController::class, 'recordShare']);
        Route::post('testimonies/{id}/report', [ReportController::class, 'store']);

        // Carnet privé ─ docs/fonctionnalites/carnet-prive.md
        Route::get('journal',                       [TestimonyController::class, 'journal']);
        Route::post('testimonies/{id}/publish',     [TestimonyController::class, 'publishFromJournal']);
        Route::post('testimonies/{id}/make-private', [TestimonyController::class, 'moveToJournal']);

        // Reactions
        Route::post('testimonies/{id}/reactions', [ReactionController::class, 'store']);
        Route::delete('testimonies/{id}/reactions/{reactionId}', [ReactionController::class, 'destroy']);

        // Comments
        Route::post('testimonies/{id}/comments', [CommentController::class, 'store']);
        Route::put('comments/{id}',              [CommentController::class, 'update']);
        Route::delete('comments/{id}',           [CommentController::class, 'destroy']);

        // Users (self management)
        Route::put('users/me',            [UserController::class, 'updateMe']);
        Route::get('users/me/following',  [UserController::class, 'following']);    // Mes abonnements
        Route::get('users/me/following-ids', [UserController::class, 'followingIds']); // boutons « Suivre » de l'application
        Route::post('users/me/avatar',    [UserController::class, 'uploadAvatar']);
        Route::post('users/me/cover',     [UserController::class, 'uploadCover'])->middleware('throttle:20,1'); // photo de couverture
        Route::delete('users/me/cover',   [UserController::class, 'deleteCover']);
        Route::put('users/me/settings',   [UserController::class, 'updateSettings']);
        Route::delete('users/me',         [UserController::class, 'deleteAccount']);
        Route::post('users/{id}/follow',  [UserController::class, 'follow'])->middleware('throttle:30,1');
        Route::delete('users/{id}/unfollow', [UserController::class, 'unfollow']);

        // Notifications
        Route::get('notifications',              [NotificationController::class, 'index']);
        Route::post('notifications/{id}/read',   [NotificationController::class, 'markRead']);
        Route::post('notifications/read-all',    [NotificationController::class, 'markAllRead']);

        // Media
        Route::post('media/upload',              [MediaController::class, 'upload']);
        Route::get('media/presigned-url',        [MediaController::class, 'presignedUrl']);

        // FCM device tokens (push notifications)
        Route::post  ('devices/token', [DeviceTokenController::class, 'store']);
        Route::delete('devices/token', [DeviceTokenController::class, 'destroy']);

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
            // Comptes organisation ─ docs/fonctionnalites/comptes-organisation.md
            Route::post('users/{id}/verify',              [AdminController::class, 'verifyOrganization']);
            Route::post('users/{id}/reject-verification', [AdminController::class, 'rejectOrganization']);
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
