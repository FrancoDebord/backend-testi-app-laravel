<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BibleAnnotationController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\BibleController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DailyVerseController;
use App\Http\Controllers\Api\EventController;
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
    Route::get('testimonies/{id}/proofs/{proofId}', [\App\Http\Controllers\Api\TestimonyProofController::class, 'show']); // public si accepté par l'auteur
    Route::get('testimonies/{id}/recommendations', [TestimonyController::class, 'recommendations'])->middleware('throttle:60,1');
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
        Route::post('lives',                               [LiveController::class, 'store']); // droits vérifiés par LiveService::start (modération, ou gestionnaire de l'événement)
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
    // ── Événements chrétiens ─ docs/fonctionnalites/evenements.md ──────────
    Route::get('events',                  [EventController::class, 'index']);
    Route::whereUuid('id')->group(function () {
        Route::get('events/{id}',             [EventController::class, 'show']);
        Route::get('events/{id}/comments',    [EventController::class, 'comments']);
        Route::get('events/{id}/testimonies', [EventController::class, 'testimonies']);
    });
    Route::middleware('auth:sanctum')->whereUuid(['id', 'imageId', 'commentId'])->group(function () {
        Route::post('events',                                   [EventController::class, 'store'])->middleware('throttle:20,1');
        Route::put('events/{id}',                               [EventController::class, 'update']);
        Route::delete('events/{id}',                            [EventController::class, 'destroy']);
        Route::post('events/{id}/images',                       [EventController::class, 'storeImage'])->middleware('throttle:30,1');
        Route::delete('events/{id}/images/{imageId}',           [EventController::class, 'destroyImage']);
        Route::post('events/{id}/images/{imageId}/cover',       [EventController::class, 'coverImage']);
        Route::post('events/{id}/participation',                [EventController::class, 'participate'])->middleware('throttle:30,1');
        Route::delete('events/{id}/participation',              [EventController::class, 'cancelParticipation']);
        Route::get('events/{id}/participants',                  [EventController::class, 'participants']);
        Route::post('events/{id}/comments',                     [EventController::class, 'storeComment']);
        Route::delete('events/{id}/comments/{commentId}',       [EventController::class, 'destroyComment']);
        Route::post('events/{id}/comments/{commentId}/promote', [EventController::class, 'promoteComment']);
        // Co-gestionnaires d'un événement (2 au plus)
        Route::post('events/{id}/managers',                     [EventController::class, 'addManager']);
        Route::delete('events/{id}/managers/{userId}',          [EventController::class, 'removeManager'])->whereUuid('userId');
        // Gestionnaires d'une organisation (2 au plus)
        Route::get('users/me/managers',                         [\App\Http\Controllers\Api\OrganizationManagerController::class, 'index']);
        Route::post('users/me/managers',                        [\App\Http\Controllers\Api\OrganizationManagerController::class, 'store']);
        Route::delete('users/me/managers/{userId}',             [\App\Http\Controllers\Api\OrganizationManagerController::class, 'destroy'])->whereUuid('userId');
        Route::get('users/me/managed-organizations',            [\App\Http\Controllers\Api\OrganizationManagerController::class, 'managed']);
        Route::delete('users/me/managed-organizations/{organizationId}', [\App\Http\Controllers\Api\OrganizationManagerController::class, 'leave'])->whereUuid('organizationId');
    });

    // ── Requêtes et sessions de prière ─ docs/fonctionnalites/requetes-de-priere.md, sessions-de-priere.md
    // Lecture publique (selon la visibilité) ; la salle d'une session est un direct : routes lives/{id}/….
    Route::get('prayer/requests',                 [\App\Http\Controllers\Api\PrayerRequestController::class, 'index']); // ?scope=all|feed|following|mine|event&event_id=&status=
    Route::get('prayer/sessions',                 [\App\Http\Controllers\Api\PrayerSessionController::class, 'index']); // ?scope=upcoming|past|mine|joined|event&event_id=
    Route::whereUuid('id')->group(function () {
        Route::get('prayer/requests/{id}',          [\App\Http\Controllers\Api\PrayerRequestController::class, 'show']);
        Route::get('prayer/requests/{id}/messages', [\App\Http\Controllers\Api\PrayerRequestController::class, 'messages']);
        Route::get('prayer/sessions/{id}',          [\App\Http\Controllers\Api\PrayerSessionController::class, 'show']);
    });
    Route::middleware('auth:sanctum')->whereUuid(['id', 'messageId'])->group(function () {
        Route::post('prayer/requests',                              [\App\Http\Controllers\Api\PrayerRequestController::class, 'store'])->middleware('throttle:20,1');
        Route::put('prayer/requests/{id}',                          [\App\Http\Controllers\Api\PrayerRequestController::class, 'update']);
        Route::delete('prayer/requests/{id}',                       [\App\Http\Controllers\Api\PrayerRequestController::class, 'destroy']);
        Route::post('prayer/requests/{id}/pray',                    [\App\Http\Controllers\Api\PrayerRequestController::class, 'pray'])->middleware('throttle:60,1'); // bascule « Je prie » ({ prayed? })
        Route::post('prayer/requests/{id}/answered',                [\App\Http\Controllers\Api\PrayerRequestController::class, 'answered']);
        Route::delete('prayer/requests/{id}/answered',              [\App\Http\Controllers\Api\PrayerRequestController::class, 'reopen']);
        Route::post('prayer/requests/{id}/messages',                [\App\Http\Controllers\Api\PrayerRequestController::class, 'storeMessage'])->middleware('throttle:20,1');
        Route::delete('prayer/requests/{id}/messages/{messageId}',  [\App\Http\Controllers\Api\PrayerRequestController::class, 'destroyMessage']);
        Route::post('prayer/requests/{id}/report',                  [\App\Http\Controllers\Api\PrayerRequestController::class, 'report'])->middleware('throttle:20,1');

        Route::post('prayer/sessions',                  [\App\Http\Controllers\Api\PrayerSessionController::class, 'store'])->middleware('throttle:20,1');
        Route::put('prayer/sessions/{id}',              [\App\Http\Controllers\Api\PrayerSessionController::class, 'update']);
        Route::delete('prayer/sessions/{id}',           [\App\Http\Controllers\Api\PrayerSessionController::class, 'destroy']);
        Route::post('prayer/sessions/{id}/cancel',      [\App\Http\Controllers\Api\PrayerSessionController::class, 'cancel']);
        Route::post('prayer/sessions/{id}/join',        [\App\Http\Controllers\Api\PrayerSessionController::class, 'join'])->middleware('throttle:30,1');
        Route::delete('prayer/sessions/{id}/join',      [\App\Http\Controllers\Api\PrayerSessionController::class, 'leave'])->middleware('throttle:30,1');
        Route::get('prayer/sessions/{id}/participants', [\App\Http\Controllers\Api\PrayerSessionController::class, 'participants']);
        Route::post('prayer/sessions/{id}/start',       [\App\Http\Controllers\Api\PrayerSessionController::class, 'start'])->middleware('throttle:10,1'); // hôte : ouvre la salle (direct)

        // Modération des requêtes (modérateurs et administrateurs)
        Route::middleware('role:moderateur,administrateur')->group(function () {
            Route::get('prayer/moderation',              [\App\Http\Controllers\Api\PrayerRequestController::class, 'moderation']); // ?filter=reported|hidden
            Route::post('prayer/requests/{id}/hide',     [\App\Http\Controllers\Api\PrayerRequestController::class, 'hide']);
            Route::post('prayer/requests/{id}/restore',  [\App\Http\Controllers\Api\PrayerRequestController::class, 'restore']);
        });
    });

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
        // Preuves (docs/fonctionnalites/preuves.md)
        Route::post('testimonies/{id}/proofs',             [\App\Http\Controllers\Api\TestimonyProofController::class, 'store'])->middleware('throttle:20,1');
        Route::delete('testimonies/{id}/proofs/{proofId}', [\App\Http\Controllers\Api\TestimonyProofController::class, 'destroy']);

        // Paroles prophétiques du carnet privé ─ docs/fonctionnalites/paroles-prophetiques.md
        Route::whereUuid('id')->group(function () {
            Route::get('prophecies',                         [\App\Http\Controllers\Api\ProphecyController::class, 'index']);
            Route::post('prophecies',                        [\App\Http\Controllers\Api\ProphecyController::class, 'store'])->middleware('throttle:30,1');
            Route::get('prophecies/{id}',                    [\App\Http\Controllers\Api\ProphecyController::class, 'show']);
            Route::put('prophecies/{id}',                    [\App\Http\Controllers\Api\ProphecyController::class, 'update']);
            Route::delete('prophecies/{id}',                 [\App\Http\Controllers\Api\ProphecyController::class, 'destroy']);
            Route::get('prophecies/{id}/prayers',            [\App\Http\Controllers\Api\ProphecyController::class, 'prayers']);
            Route::post('prophecies/{id}/prayers',           [\App\Http\Controllers\Api\ProphecyController::class, 'pray'])->middleware('throttle:60,1');
            Route::delete('prophecies/{id}/prayers/{prayerId}', [\App\Http\Controllers\Api\ProphecyController::class, 'deletePrayer'])->whereNumber('prayerId');
        });
        // Mon fil : comptes suivis, complétés de suggestions ─ docs/fonctionnalites/recommandations.md
        Route::get('feed', [TestimonyController::class, 'personalFeed']);

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
        Route::get('users/me/followers',  [UserController::class, 'followers']);    // Mes abonnés
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
