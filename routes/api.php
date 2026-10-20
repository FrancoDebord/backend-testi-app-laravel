<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BibleAnnotationController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\GoogleAuthController;
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

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| API Routes v1 — My Testimony
|--------------------------------------------------------------------------
| Base URL : /api/v1
| Auth     : Laravel Sanctum (Bearer token)
| Format   : { success, data, message, errors? }
*/

Route::prefix('v1')->group(function () {

    // ── Auth (public) ───────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('login',           [AuthController::class, 'login'])->middleware('throttle:login'); // e-mail ou téléphone + mot de passe
        Route::post('register',        [AuthController::class, 'register']);
        Route::post('phone',           [AuthController::class, 'phoneAuth']);
        // Connexion Google : { id_token } → même réponse que /auth/login (docs/fonctionnalites/connexion-google.md).
        // /social : anciennes versions de l'application ({ provider: google, token | id_token }), même traitement.
        Route::post('google',          GoogleAuthController::class)->middleware('throttle:20,1');
        Route::post('social',          GoogleAuthController::class)->middleware('throttle:20,1');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    });

    // ── Verset du jour (public + authentifié) ────────────────────────────
    Route::get('daily-verse', [DailyVerseController::class, 'today']); // ?date=AAAA-MM-JJ&translation=
    // Versions, commentaires, requêtes de prière, témoignages liés — docs/fonctionnalites/verset-du-jour.md
    Route::get('daily-verse/versions', [DailyVerseController::class, 'versions']); // ?date=&translation=
    Route::get('daily-verse/history', [DailyVerseController::class, 'history']); // ?page=&limit= — historique des versets du jour
    Route::whereNumber('id')->group(function () {
        Route::get('daily-verse/{id}',                 [DailyVerseController::class, 'show']);
        Route::get('daily-verse/{id}/versions',        [DailyVerseController::class, 'versions']);
        Route::get('daily-verse/{id}/comments',        [DailyVerseController::class, 'comments']);
        Route::get('daily-verse/{id}/prayer-requests', [DailyVerseController::class, 'prayerRequests']);
        Route::get('daily-verse/{id}/testimonies',     [DailyVerseController::class, 'testimonies']);
    });
    // Compteurs de partage : ouverts aux invités (l'application partage aussi sans compte), limités en débit.
    Route::middleware('throttle:30,1')->group(function () {
        Route::post('daily-verse/share',       [DailyVerseController::class, 'share']);
        Route::post('testimonies/{id}/share',  [TestimonyController::class, 'recordShare']);
    });
    Route::middleware('auth:sanctum')->group(function () {
        Route::post  ('daily-verse/react',  [DailyVerseController::class, 'react']);
        Route::delete('daily-verse/react',  [DailyVerseController::class, 'unreact']);
        Route::whereNumber('id')->whereUuid('commentId')->group(function () {
            Route::post  ('daily-verse/{id}/react',                       [DailyVerseController::class, 'reactTo'])->middleware('throttle:60,1');
            Route::post  ('daily-verse/{id}/pray/start',                  [DailyVerseController::class, 'startPrayer'])->middleware('throttle:30,1'); // temps de prière (docs/fonctionnalites/temps-de-priere.md)
            Route::post  ('daily-verse/{id}/comments',                    [DailyVerseController::class, 'storeComment'])->middleware('throttle:20,1');
            Route::delete('daily-verse/{id}/comments/{commentId}',        [DailyVerseController::class, 'destroyComment']);
            Route::post  ('daily-verse/{id}/comments/{commentId}/report', [DailyVerseController::class, 'reportComment'])->middleware('throttle:20,1');
        });
    });

    // Designs du verset : catalogue (public) et préférences synchronisées — docs/fonctionnalites/designs-du-verset.md
    Route::get('verse-designs', [\App\Http\Controllers\Api\VerseStyleController::class, 'designs']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me/verse-style', [\App\Http\Controllers\Api\VerseStyleController::class, 'show']);
        Route::put('me/verse-style', [\App\Http\Controllers\Api\VerseStyleController::class, 'update'])->middleware('throttle:60,1');
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
    // Politique de confidentialité, sans connexion : ?lang=fr|en ─ docs/fonctionnalites/confidentialite-et-suppression.md
    Route::get('legal/privacy', [\App\Http\Controllers\Api\LegalController::class, 'privacy'])->middleware('throttle:60,1');

    Route::get('categories',        [CategoryController::class, 'index']);
    Route::get('categories/{slug}', [CategoryController::class, 'show']);
    // Types d'événements (actifs ; ?all=1 pour les administrateurs) ─ docs/fonctionnalites/types-evenements.md
    Route::get('event-types',       [\App\Http\Controllers\Api\EventTypeController::class, 'index']);

    // ── Autocomplétion des barres de recherche (public) ─ docs/fonctionnalites/recherche.md
    Route::get('search/suggest', [\App\Http\Controllers\Api\SearchController::class, 'suggest'])->middleware('throttle:120,1'); // ?q=&scope=&limit=
    Route::post('search/log',    [\App\Http\Controllers\Api\SearchController::class, 'log'])->middleware('throttle:30,1');     // { q } recherche validée, anonyme

    // ── Testimonies (public read) ────────────────────────────────────────
    Route::get('testimonies',           [TestimonyController::class, 'index']);
    Route::get('testimonies/featured',  [TestimonyController::class, 'featured']);
    Route::get('testimonies/my',        [TestimonyController::class, 'myTestimonies'])->middleware('auth:sanctum');
    Route::get('testimonies/{id}',      [TestimonyController::class, 'show']);
    Route::get('testimonies/{id}/proofs/{proofId}', [\App\Http\Controllers\Api\TestimonyProofController::class, 'show']); // public si accepté par l'auteur
    Route::get('testimonies/{id}/recommendations', [TestimonyController::class, 'recommendations'])->middleware('throttle:60,1');
    // Vue dédoublonnée (30 min) + secondes regardées : statistiques (docs/fonctionnalites/gestion-des-temoignages.md)
    Route::post('testimonies/{id}/view', [TestimonyController::class, 'recordView'])->middleware('throttle:120,1');
    Route::get('testimonies/{id}/comments',  [CommentController::class, 'index']);
    Route::get('testimonies/{id}/reactions', [ReactionController::class, 'index']);
    // Avant users/{id} : comptes à suivre (invité accepté) et mentions « @ » (connecté)
    // ─ docs/fonctionnalites/abonnements.md, docs/fonctionnalites/mentions.md
    Route::get('users/suggestions',    [\App\Http\Controllers\Api\CommunityController::class, 'suggestions'])->middleware('throttle:60,1');
    Route::get('users/mention-search', [\App\Http\Controllers\Api\CommunityController::class, 'mentionSearch'])->middleware(['auth:sanctum', 'throttle:120,1']);
    Route::get('users/{id}',           [UserController::class, 'show'])->where('id', '(?!me$)[^/]+'); // « me » : profil connecté (plus bas)
    // Communauté : organisations et personnes à suivre (docs/fonctionnalites/abonnements.md)
    Route::get('community',            [\App\Http\Controllers\Api\CommunityController::class, 'index']);
    Route::get('users/{id}/testimonies', [UserController::class, 'testimonies']);
    // Témoignage de conversion publié d'un compte (404 s'il n'en a pas) ─ docs/fonctionnalites/temoignage-de-conversion.md
    Route::get('users/{id}/conversion-testimony', [\App\Http\Controllers\Api\ConversionTestimonyController::class, 'ofUser']);
    Route::get('users/{id}/reposts', [\App\Http\Controllers\Api\RepostController::class, 'ofUser']); // partages internes d'un compte (profil)

    // ── Témoignages en direct (lecture publique) ─ docs/fonctionnalites/lives.md
    Route::get('lives',                     [LiveController::class, 'index']);
    Route::get('lives/{id}',                [LiveController::class, 'show'])->whereUuid('id');
    Route::post('lives/{id}/viewer-token',  [LiveController::class, 'viewerToken'])->whereUuid('id');
    Route::get('lives/{id}/comments',       [LiveController::class, 'comments'])->whereUuid('id');
    // Directs passés : témoignages publiés sur le direct (docs/fonctionnalites/lives.md)
    Route::get('lives/{id}/testimonies',    [LiveController::class, 'testimonies'])->whereUuid('id');
    Route::get('lives/{id}/stats',          [LiveController::class, 'stats'])->whereUuid('id');
    Route::get('lives/{id}/stage',          [LiveController::class, 'stage'])->whereUuid('id');
    Route::get('lives/{id}/viewers',        [LiveController::class, 'viewers'])->whereUuid('id')->middleware('throttle:30,1');
    // Présence d'un spectateur (direct YouTube, sans LiveKit) : public, invités compris
    Route::post('lives/{id}/heartbeat',     [LiveController::class, 'heartbeat'])->whereUuid('id')->middleware('throttle:12,1');
    Route::post('livekit/webhook',          LiveKitWebhookController::class)->name('livekit.webhook');
    // Bunny Stream (public, état relu par l'API Bunny) — docs/fonctionnalites/videos-bunny-stream.md
    Route::post('webhooks/bunny',           \App\Http\Controllers\Api\BunnyWebhookController::class)->middleware('throttle:120,1')->name('bunny.webhook');

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
    // ?when=past : événements terminés, du plus récent au plus ancien, avec testimonies_count et thumbnail_url
    Route::get('events',                  [EventController::class, 'index']);
    Route::whereUuid('id')->group(function () {
        Route::get('events/{id}',             [EventController::class, 'show']);
        Route::get('events/{id}/comments',    [EventController::class, 'comments']);
        Route::get('events/{id}/testimonies', [EventController::class, 'testimonies']);
        Route::get('events/{id}/reviews',     [EventController::class, 'reviews']); // avis du sondage d'après l'événement
    });
    Route::middleware('auth:sanctum')->whereUuid(['id', 'imageId', 'commentId'])->group(function () {
        Route::post('events',                                   [EventController::class, 'store'])->middleware('throttle:20,1');
        // Report / annulation (gestionnaires), signalement, sondage — docs/fonctionnalites/evenements.md
        Route::post('events/{id}/postpone',                     [EventController::class, 'postpone'])->middleware('throttle:20,1');
        Route::post('events/{id}/cancel',                       [EventController::class, 'cancel'])->middleware('throttle:20,1');
        Route::post('events/{id}/report',                       [EventController::class, 'report'])->middleware('throttle:20,1');
        Route::post('events/{id}/review',                       [EventController::class, 'review'])->middleware('throttle:20,1');
        Route::middleware('role:moderateur,administrateur')->group(function () {
            Route::get('events/moderation',                     [EventController::class, 'moderation']); // ?filter=reported|unconfirmed|hidden
            Route::post('events/{id}/hide',                     [EventController::class, 'hide']);
            Route::post('events/{id}/restore',                  [EventController::class, 'restore']);
            Route::post('events/{id}/dismiss',                  [EventController::class, 'dismiss']);
        });
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
        // Administrateurs de compte ─ docs/fonctionnalites/administrateurs-de-compte.md
        Route::get('users/me/accounts',                         [\App\Http\Controllers\Api\OrganizationManagerController::class, 'accounts']);
        Route::get('users/me/managers/activity',                [\App\Http\Controllers\Api\OrganizationManagerController::class, 'activity']);
        Route::get('users/me/managed-organizations',            [\App\Http\Controllers\Api\OrganizationManagerController::class, 'managed']);
        Route::delete('users/me/managed-organizations/{organizationId}', [\App\Http\Controllers\Api\OrganizationManagerController::class, 'leave'])->whereUuid('organizationId');
    });

    // ── Requêtes et sessions de prière ─ docs/fonctionnalites/requetes-de-priere.md, sessions-de-priere.md
    // Lecture publique (selon la visibilité) ; la salle d'une session est un direct : routes lives/{id}/….
    Route::get('prayer/requests',                 [\App\Http\Controllers\Api\PrayerRequestController::class, 'index']); // ?scope=all|feed|following|mine|event|verse&event_id=&daily_verse_id=&status=
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
        Route::post('prayer/requests/{id}/pray',                    [\App\Http\Controllers\Api\PrayerRequestController::class, 'pray'])->middleware('throttle:60,1'); // bascule « Je prie » ({ prayed? }) ; ajout après le temps de prière
        Route::post('prayer/requests/{id}/pray/start',              [\App\Http\Controllers\Api\PrayerRequestController::class, 'startPrayer'])->middleware('throttle:30,1'); // temps de prière (docs/fonctionnalites/temps-de-priere.md)
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

    // ── Réactions et partages internes des événements, requêtes et sessions de prière
    // docs/fonctionnalites/reactions.md, partage-interne.md
    Route::middleware('auth:sanctum')->whereUuid('id')->group(function () {
        foreach (['events' => 'event', 'prayer/requests' => 'prayer_request', 'prayer/sessions' => 'prayer_session'] as $prefix => $kind) {
            Route::post("{$prefix}/{id}/reactions",   [\App\Http\Controllers\Api\ContentInteractionController::class, 'react'])->defaults('kind', $kind)->middleware('throttle:60,1');
            Route::delete("{$prefix}/{id}/reactions", [\App\Http\Controllers\Api\ContentInteractionController::class, 'unreact'])->defaults('kind', $kind)->middleware('throttle:60,1');
            Route::post("{$prefix}/{id}/repost",      [\App\Http\Controllers\Api\ContentInteractionController::class, 'repost'])->defaults('kind', $kind)->middleware('throttle:30,1');
            Route::delete("{$prefix}/{id}/repost",    [\App\Http\Controllers\Api\ContentInteractionController::class, 'unrepost'])->defaults('kind', $kind);
        }
        // Temps de prière avant « Je prie » (requêtes de prière : prayer/requests/{id}/pray/start) — docs/fonctionnalites/temps-de-priere.md
        foreach (['events' => 'event', 'prayer/sessions' => 'prayer_session'] as $prefix => $kind) {
            Route::post("{$prefix}/{id}/pray/start", [\App\Http\Controllers\Api\ContentInteractionController::class, 'startPrayer'])->defaults('kind', $kind)->middleware('throttle:30,1');
        }
    });

    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::post('auth/logout',           [AuthController::class, 'logout']);
        Route::post('auth/refresh',          [AuthController::class, 'refresh']);
        Route::get ('auth/me',               [AuthController::class, 'me']);
        Route::post('auth/change-password',  [AuthController::class, 'changePassword']);

        // Testimonies (write)
        Route::post('testimonies',         [TestimonyController::class, 'store']);

        // Témoignage vidéo envoyé directement à Bunny Stream (TUS) — docs/fonctionnalites/videos-bunny-stream.md
        Route::post('videos/upload-init', [\App\Http\Controllers\Api\VideoUploadController::class, 'init'])->middleware('throttle:20,1');
        Route::whereUuid('id')->group(function () {
            Route::post('videos/{id}/upload-signature', [\App\Http\Controllers\Api\VideoUploadController::class, 'signature'])->middleware('throttle:60,1');
            Route::post('videos/{id}/upload-complete',  [\App\Http\Controllers\Api\VideoUploadController::class, 'complete']);
        });
        Route::put('testimonies/{id}',     [TestimonyController::class, 'update']);
        Route::delete('testimonies/{id}',  [TestimonyController::class, 'destroy']);
        Route::put('testimonies/{id}/save',    [TestimonyController::class, 'save']);
        Route::delete('testimonies/{id}/unsave', [TestimonyController::class, 'unsave']);
        Route::get('testimonies/saved/list',   [TestimonyController::class, 'saved']);
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
            Route::post('prophecies/{id}/prayers',           [\App\Http\Controllers\Api\ProphecyController::class, 'pray'])->middleware('throttle:60,1'); // après le temps de prière
            Route::post('prophecies/{id}/pray/start',        [\App\Http\Controllers\Api\ProphecyController::class, 'startPrayer'])->middleware('throttle:30,1'); // temps de prière (docs/fonctionnalites/temps-de-priere.md)
            Route::delete('prophecies/{id}/prayers/{prayerId}', [\App\Http\Controllers\Api\ProphecyController::class, 'deletePrayer'])->whereNumber('prayerId');
        });
        // Mon fil : comptes suivis, complétés de suggestions ─ docs/fonctionnalites/recommandations.md
        Route::get('feed', [TestimonyController::class, 'personalFeed']);
        Route::get('feed/inserts', [\App\Http\Controllers\Api\FeedController::class, 'inserts']); // événements, sessions, requêtes, paroles publiques (suivis d'abord)
        // Partage interne « X a partagé » ─ docs/fonctionnalites/partage-interne.md
        Route::post('testimonies/{id}/repost',   [\App\Http\Controllers\Api\RepostController::class, 'store'])->middleware('throttle:30,1'); // { comment? }
        Route::delete('testimonies/{id}/repost', [\App\Http\Controllers\Api\RepostController::class, 'destroy']);

        // Carnet privé ─ docs/fonctionnalites/carnet-prive.md
        Route::get('journal',                       [TestimonyController::class, 'journal']);
        Route::post('testimonies/{id}/publish',     [TestimonyController::class, 'publishFromJournal']);
        Route::post('testimonies/{id}/make-private', [TestimonyController::class, 'moveToJournal']);

        // Reactions
        Route::post('testimonies/{id}/reactions', [ReactionController::class, 'store']);
        Route::post('testimonies/{id}/pray/start', [ReactionController::class, 'startPrayer'])->middleware('throttle:30,1'); // temps de prière (docs/fonctionnalites/temps-de-priere.md)
        Route::delete('testimonies/{id}/reactions/{reactionId}', [ReactionController::class, 'destroy']);
        Route::delete('testimonies/{id}/reactions', [ReactionController::class, 'destroy']);

        // Comments
        Route::post('testimonies/{id}/comments', [CommentController::class, 'store']);
        Route::put('comments/{id}',              [CommentController::class, 'update']);
        Route::delete('comments/{id}',           [CommentController::class, 'destroy']);

        // « J'aime » sur les commentaires ─ docs/fonctionnalites/jaime-commentaires.md
        // Réponse : { success, data: { likesCount, isLikedByMe } } ; 404 si invisible.
        Route::middleware('throttle:60,1')->group(function () {
            $likes = \App\Http\Controllers\Api\CommentLikeController::class;
            Route::post  ('comments/{id}/like', [$likes, 'likeTestimonyComment'])->whereUuid('id');
            Route::delete('comments/{id}/like', [$likes, 'unlikeTestimonyComment'])->whereUuid('id');
            Route::post  ('events/{id}/comments/{commentId}/like', [$likes, 'likeEventComment'])->whereUuid(['id', 'commentId']);
            Route::delete('events/{id}/comments/{commentId}/like', [$likes, 'unlikeEventComment'])->whereUuid(['id', 'commentId']);
            Route::post  ('daily-verse/{id}/comments/{commentId}/like', [$likes, 'likeVerseComment'])->whereNumber('id')->whereUuid('commentId');
            Route::delete('daily-verse/{id}/comments/{commentId}/like', [$likes, 'unlikeVerseComment'])->whereNumber('id')->whereUuid('commentId');
        });

        // Profil connecté : /me (forme courte) ─ langue des push et e-mails : { locale: fr|en } (docs/fonctionnalites/langues.md)
        Route::get('me',                  [AuthController::class, 'me']);
        Route::put('me',                  [UserController::class, 'updateMe']);
        // Supprimer mon compte et toutes mes données ({ password } ou { confirm: true }) ─ docs/fonctionnalites/confidentialite-et-suppression.md
        Route::delete('me',               [\App\Http\Controllers\Api\LegalController::class, 'destroyMe'])->middleware('throttle:10,1');
        // Mon témoignage de conversion (null s'il n'existe pas) ─ docs/fonctionnalites/temoignage-de-conversion.md
        Route::get('me/conversion-testimony', [\App\Http\Controllers\Api\ConversionTestimonyController::class, 'mine']);
        // Notification de test vers tous mes appareils, résultat par jeton ─ docs/fonctionnalites/notifications-push.md
        Route::post('me/push-test',       [DeviceTokenController::class, 'test'])->middleware('throttle:5,1');
        // Types de témoignages que le compte (qui agit) peut publier ─ docs/fonctionnalites/types-de-temoignages.md
        Route::get('me/publishing',       [\App\Http\Controllers\Api\PublishingController::class, 'mine']);

        // Users (self management)
        Route::get('users/me',            [AuthController::class, 'me']); // comme auth/me, mais suit X-Act-As (compte administré)
        Route::put('users/me',            [UserController::class, 'updateMe']);
        Route::get('users/me/following',  [UserController::class, 'following']);    // Mes abonnements
        Route::get('users/me/followers',  [UserController::class, 'followers']);    // Mes abonnés
        Route::get('users/me/following-ids', [UserController::class, 'followingIds']); // boutons « Suivre » de l'application
        Route::post('users/me/avatar',    [UserController::class, 'uploadAvatar']);
        Route::post('users/me/cover',     [UserController::class, 'uploadCover'])->middleware('throttle:20,1'); // photo de couverture
        Route::delete('users/me/cover',   [UserController::class, 'deleteCover']);
        // Vérification des comptes personnels ─ docs/fonctionnalites/verification-des-comptes.md
        Route::get('users/me/verification',  [\App\Http\Controllers\Api\AccountVerificationController::class, 'show']);
        Route::post('users/me/verification', [\App\Http\Controllers\Api\AccountVerificationController::class, 'store'])->middleware('throttle:10,60');
        Route::put('users/me/settings',   [UserController::class, 'updateSettings']);
        Route::get('users/me/settings',   [UserController::class, 'settings']);
        // Localisation privée pour classer les fils par zone ─ docs/fonctionnalites/localisation.md
        Route::get('users/me/location',    [\App\Http\Controllers\Api\LocationController::class, 'show']);
        Route::put('users/me/location',    [\App\Http\Controllers\Api\LocationController::class, 'update'])->middleware('throttle:20,1');
        Route::delete('users/me/location', [\App\Http\Controllers\Api\LocationController::class, 'destroy']);
        // Suppression définitive du compte et de ses données ─ docs/fonctionnalites/confidentialite-et-suppression.md
        Route::delete('users/me',         [\App\Http\Controllers\Api\LegalController::class, 'destroyMe']);
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
        Route::post  ('device-tokens', [DeviceTokenController::class, 'store']);
        Route::delete('device-tokens', [DeviceTokenController::class, 'destroy']);

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
            // Types de témoignages autorisés par profil ─ docs/fonctionnalites/types-de-temoignages.md
            Route::get('users/{id}/publishing',   [\App\Http\Controllers\Api\PublishingController::class, 'show']);
            Route::put('users/{id}/publishing',   [\App\Http\Controllers\Api\PublishingController::class, 'update']);
            // Comptes organisation ─ docs/fonctionnalites/comptes-organisation.md
            Route::post('users/{id}/verify',              [AdminController::class, 'verifyOrganization']);
            Route::post('users/{id}/reject-verification', [AdminController::class, 'rejectOrganization']);
            // Comptes personnels ─ docs/fonctionnalites/verification-des-comptes.md
            Route::post('users/{id}/revoke-verification', [\App\Http\Controllers\Api\AccountVerificationController::class, 'revoke']);
            // Badge « Approuvé » manuel, motif obligatoire ─ docs/fonctionnalites/badge-approuve.md
            Route::post('users/{id}/approval',   [\App\Http\Controllers\Api\AccountApprovalController::class, 'grant'])->whereUuid('id');
            Route::delete('users/{id}/approval', [\App\Http\Controllers\Api\AccountApprovalController::class, 'revoke'])->whereUuid('id');
            Route::get('verification-requests',                         [\App\Http\Controllers\Api\AccountVerificationController::class, 'index']);
            Route::get('verification-requests/{id}',                    [\App\Http\Controllers\Api\AccountVerificationController::class, 'adminShow'])->whereUuid('id');
            Route::get('verification-requests/{id}/documents/{index}',  [\App\Http\Controllers\Api\AccountVerificationController::class, 'document'])->whereUuid('id')->whereNumber('index');
            Route::post('verification-requests/{id}/approve',           [\App\Http\Controllers\Api\AccountVerificationController::class, 'approve'])->whereUuid('id');
            Route::post('verification-requests/{id}/reject',            [\App\Http\Controllers\Api\AccountVerificationController::class, 'reject'])->whereUuid('id');
            Route::get('testimonies',             [AdminController::class, 'content']);
            Route::get('categories',              [AdminController::class, 'categories']);
            Route::post('categories',             [AdminController::class, 'storeCategory']);
            Route::put('categories/{id}',         [AdminController::class, 'updateCategory']);
            Route::delete('categories/{id}',      [AdminController::class, 'deleteCategory']);
            // Types d'événements ─ docs/fonctionnalites/types-evenements.md
            Route::post('event-types',            [\App\Http\Controllers\Api\EventTypeController::class, 'store']);
            Route::put('event-types/reorder',     [\App\Http\Controllers\Api\EventTypeController::class, 'reorder']);
            Route::put('event-types/{id}',        [\App\Http\Controllers\Api\EventTypeController::class, 'update'])->whereNumber('id');
            Route::delete('event-types/{id}',     [\App\Http\Controllers\Api\EventTypeController::class, 'destroy'])->whereNumber('id');
            Route::get('settings',                [AdminController::class, 'settings']);
            // Mêmes groupes, libellés et descriptions que la page web ─ docs/fonctionnalites/parametres-globaux.md
            Route::get('settings/schema',         [AdminController::class, 'settingsSchema']);
            Route::put('settings',                [AdminController::class, 'updateSettings']);
        });
    });


    Route::post('/_deploy', function (Request $request) {
    $token = (string) config('app.deploy_token');
    abort_unless($token !== '' && hash_equals($token, (string) $request->header('X-Deploy-Token')), 403);

    $out = [];
    Artisan::call('migrate', ['--force' => true]);
    $out['migrate'] = Artisan::output();

    Artisan::call('optimize:clear');

    // Vidéos Bunny Stream : état relu chez Bunny juste après les migrations (docs/fonctionnalites/videos-bunny-stream.md).
    try {
        Artisan::call('videos:sync-bunny-status');
        $out['bunny_sync'] = trim(Artisan::output());
    } catch (\Throwable $e) {
        $out['bunny_sync'] = 'impossible : ' . $e->getMessage();
    }

    // Fichiers envoyés (images, audios) : servis par Laravel sans lien public/storage, sauf STORAGE_PUBLIC_LINK=true.
    // Un lien refusé par Apache (AH00037) donnait 403 sur toutes les images : il est retiré.
    $out['storage_link'] = \App\Support\PublicStorageLink::sync();

    return response()->json(['ok' => true] + $out);
})->middleware('throttle:5,1');


Route::get('/_cron', function (Request $request) {
    $token = (string) config('app.cron_token');
    abort_unless($token !== '' && hash_equals($token, (string) $request->query('token')), 403);
    Artisan::call('schedule:run');
    return response()->json(['ok' => true, 'output' => Artisan::output()]);
})->middleware('throttle:10,1');
});


