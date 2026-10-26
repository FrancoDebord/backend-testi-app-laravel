<?php

use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BibleController;
use App\Http\Controllers\Web\DisplayPreferenceController;
use App\Http\Controllers\Web\DocumentationController;
use App\Http\Controllers\Web\EventController;
use App\Http\Controllers\Web\LiveController;
use App\Http\Controllers\Api\LiveController as LiveActions;
use App\Http\Controllers\Web\ExploreController;
use App\Http\Controllers\Web\FeedController;
use App\Http\Controllers\Web\UserSearchController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\JournalController;
use App\Http\Controllers\Web\LegalController;
use App\Http\Controllers\Web\ProphecyController;
use App\Http\Controllers\Web\ModerationController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\TestimonyController;
use App\Http\Controllers\Web\VideoController;
use App\Http\Controllers\Web\CommentController;
use App\Http\Controllers\Web\CommunityController;
use App\Http\Controllers\PublicStorageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — My Testimony
|--------------------------------------------------------------------------
*/

// ── Auth ─────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',            [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',           [AuthController::class, 'login'])->middleware('throttle:login'); // e-mail ou téléphone + mot de passe
    // Bouton « Se connecter avec Google » (docs/fonctionnalites/connexion-google.md)
    Route::post('/login/google',    [AuthController::class, 'google'])->name('login.google')->middleware('throttle:20,1');
    Route::get('/register',         [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register',        [AuthController::class, 'register']);
    Route::get('/forgot-password',  [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ── Fichiers envoyés (audios, vidéos, images) ─────────────────────────────
// Secours si le lien public/storage est absent sur l'hébergement : Laravel
// sert alors les fichiers lui-même (lecture partielle « Range » comprise).
// Si le lien existe, le serveur web les sert directement et cette route
// n'est pas appelée. Sans session ni cookies : ce sont de simples fichiers.
Route::get('/storage/{path}', [PublicStorageController::class, 'show'])
    ->where('path', '.*')
    ->withoutMiddleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ])
    ->name('storage.public');

// ── Images d'aperçu de partage (og:image) ─ docs/fonctionnalites/apercu-de-partage.md ──
// JPEG 1200 × 630 générés au premier appel (storage/app/share-images), publics, sans session ni
// cookies : lus par les robots de WhatsApp, Facebook, Telegram, X et LinkedIn. Jamais sous /storage
// (refusé par Apache en production). Contenu non visible sans compte : 404.
Route::withoutMiddleware([
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    \App\Http\Middleware\ActAsAccount::class,
])->whereUuid('id')->group(function () {
    Route::get('/partage/temoignages/{id}.jpg', [\App\Http\Controllers\Web\ShareImageController::class, 'testimony'])->name('share-images.testimony');
    Route::get('/partage/evenements/{id}.jpg',  [\App\Http\Controllers\Web\ShareImageController::class, 'event'])->name('share-images.event');
    Route::get('/partage/requetes/{id}.jpg',    [\App\Http\Controllers\Web\ShareImageController::class, 'prayerRequest'])->name('share-images.prayer-request');
    Route::get('/partage/directs/{id}.jpg',     [\App\Http\Controllers\Web\ShareImageController::class, 'live'])->name('share-images.live');
    Route::get('/partage/site.jpg',             [\App\Http\Controllers\Web\ShareImageController::class, 'site'])->name('share-images.site');
});

// ── App Links / Universal Links ───────────────────────────────────────────
// Vérification par Android et iOS : les liens share_url s'ouvrent dans l'app.
Route::get('/.well-known/assetlinks.json', function () {
    return response()->json([[
        'relation' => ['delegate_permission/common.handle_all_urls'],
        'target'   => [
            'namespace'                => 'android_app',
            'package_name'             => config('applinks.android.package'),
            'sha256_cert_fingerprints' => config('applinks.android.sha256_fingerprints'),
        ],
    ]], 200, [], JSON_UNESCAPED_SLASHES);
})->name('applinks.android');

Route::get('/.well-known/apple-app-site-association', function () {
    $appId = config('applinks.ios.app_id');

    return response()->json([
        'applinks' => [
            'apps'    => [],
            'details' => $appId ? [[
                'appIDs'     => [$appId],
                'components' => array_map(fn ($p) => ['/' => $p], config('applinks.paths')),
            ]] : [],
        ],
    ], 200, [], JSON_UNESCAPED_SLASHES);
})->name('applinks.ios');

// ── Confidentialité et suppression de compte ─ docs/fonctionnalites/confidentialite-et-suppression.md ──
// Publiques, sans connexion (exigence Google Play). Langue : ?lang=, sinon la langue choisie, sinon celle de l'adresse.
Route::get('/confidentialite',     [LegalController::class, 'privacy'])->defaults('default', 'fr')->name('legal.privacy');
Route::get('/privacy',             [LegalController::class, 'privacy'])->defaults('default', 'en')->name('legal.privacy.en');
Route::get('/suppression-compte',  [LegalController::class, 'accountDeletion'])->defaults('default', 'fr')->name('account-deletion');
Route::get('/delete-account',      [LegalController::class, 'accountDeletion'])->defaults('default', 'en')->name('account-deletion.en');
// Demande sans connexion : 5 par heure et par adresse IP, plus un champ piège contre les robots.
Route::post('/suppression-compte', [LegalController::class, 'requestDeletion'])->middleware('throttle:5,60')->name('account-deletion.request');
// Personne connectée : suppression immédiate après confirmation.
Route::delete('/suppression-compte', [LegalController::class, 'destroyAccount'])->middleware(['auth', 'throttle:10,1'])->name('account-deletion.destroy');

// ── Public ────────────────────────────────────────────────────────────────
Route::get('/',                     [HomeController::class, 'index'])->name('home');
Route::get('/explore',              [ExploreController::class, 'index'])->name('explore');
Route::get('/bible',                [BibleController::class, 'reader'])->name('bible.reader');
// Lien partageable d'un passage, ouvert aussi dans l'application : /bible/LSG/jean/3/16-18 (docs/fonctionnalites/liens-bible.md)
Route::get('/bible/{translation}/{book}/{chapter}/{verses?}', [BibleController::class, 'passage'])
    ->where(['translation' => '[A-Za-z0-9_.-]{1,30}', 'book' => '[^/]{1,40}', 'chapter' => '[0-9]{1,3}', 'verses' => '[0-9,-]{1,200}'])
    ->name('bible.passage');
// Popup « Qui a réagi ? » : effectif de chaque réaction et personnes (docs/fonctionnalites/qui-a-reagi.md)
Route::get('/reactions/{kind}/{id}', \App\Http\Controllers\Web\ReactionPeopleController::class)
    ->whereIn('kind', \App\Services\ReactionPeople::KINDS)->where('id', '[0-9a-fA-F-]{1,36}')
    ->middleware('throttle:60,1')->name('reactions.people');
// Verset du jour en plein écran : versions, prière, commentaires, témoignages (docs/fonctionnalites/verset-du-jour.md)
Route::get('/verset-du-jour/historique', [\App\Http\Controllers\Web\DailyVerseController::class, 'history'])->name('daily-verse.history');
Route::get('/verset-du-jour/{date?}', [\App\Http\Controllers\Web\DailyVerseController::class, 'show'])
    ->where('date', '\d{4}-\d{2}-\d{2}')->name('daily-verse.show');
// Image de partage (aperçu des réseaux sociaux) et design choisi : docs/fonctionnalites/designs-du-verset.md
Route::get('/verset-du-jour/{date}/image.png', [\App\Http\Controllers\Web\DailyVerseController::class, 'image'])
    ->where('date', '\d{4}-\d{2}-\d{2}')->middleware('throttle:60,1')->name('daily-verse.image');
Route::put('/verset-du-jour/style', [\App\Http\Controllers\Web\DailyVerseController::class, 'updateStyle'])
    ->middleware(['auth', 'throttle:60,1'])->name('daily-verse.style');
Route::middleware('auth')->whereNumber('id')->whereUuid('comment')->group(function () {
    Route::post('/verset-du-jour/{id}/reagir',                          [\App\Http\Controllers\Web\DailyVerseController::class, 'react'])->middleware('throttle:60,1')->name('daily-verse.react');
    Route::post('/verset-du-jour/{id}/je-prie/commencer',               [\App\Http\Controllers\Web\DailyVerseController::class, 'startPrayer'])->middleware('throttle:30,1')->name('daily-verse.pray.start'); // temps de prière
    Route::post('/verset-du-jour/{id}/prieres',                         [\App\Http\Controllers\Web\DailyVerseController::class, 'storePrayer'])->middleware('throttle:20,1')->name('daily-verse.prayers.store');
    Route::post('/verset-du-jour/{id}/commentaires',                    [\App\Http\Controllers\Web\DailyVerseController::class, 'storeComment'])->middleware('throttle:20,1')->name('daily-verse.comments.store');
    Route::delete('/verset-du-jour/{id}/commentaires/{comment}',        [\App\Http\Controllers\Web\DailyVerseController::class, 'destroyComment'])->name('daily-verse.comments.destroy');
    Route::post('/verset-du-jour/{id}/commentaires/{comment}/signaler', [\App\Http\Controllers\Web\DailyVerseController::class, 'reportComment'])->middleware('throttle:20,1')->name('daily-verse.comments.report');
});
Route::get('/testimonies/{id}',     [TestimonyController::class, 'show'])->name('testimonies.show');
// Preuves : auteur et équipe, ou public si l'auteur l'a accepté (docs/fonctionnalites/preuves.md)
Route::get('/testimonies/{id}/proofs/{proofId}', [TestimonyController::class, 'proof'])->name('testimonies.proof');
Route::get('/profiles/{id}',        [ProfileController::class, 'show'])->name('profiles.show');

// ── Communauté et abonnements ─ docs/fonctionnalites/abonnements.md ────────
Route::get('/communaute', [CommunityController::class, 'index'])->name('community.index');
Route::middleware(['auth', 'throttle:30,1'])->whereUuid('id')->group(function () {
    Route::post('/profiles/{id}/follow',   [CommunityController::class, 'follow'])->name('users.follow');
    Route::delete('/profiles/{id}/follow', [CommunityController::class, 'unfollow'])->name('users.unfollow');
});

// ── Langue du site (français / anglais) ─ docs/fonctionnalites/langues.md ──
Route::post('/langue', [\App\Http\Controllers\Web\LocaleController::class, 'update'])->middleware('throttle:30,1')->name('locale.update');

// ── Témoignages de conversion ─ docs/fonctionnalites/temoignage-de-conversion.md
Route::get('/temoignages-de-conversion', [\App\Http\Controllers\Web\ConversionTestimonyController::class, 'index'])->name('testimonies.conversions');
Route::middleware('auth')->group(function () {
    Route::get('/temoignages-de-conversion/le-mien/modifier', [\App\Http\Controllers\Web\ConversionTestimonyController::class, 'edit'])->name('testimonies.conversion.edit');
    Route::put('/temoignages-de-conversion/le-mien', [\App\Http\Controllers\Web\ConversionTestimonyController::class, 'update'])->middleware('throttle:20,1')->name('testimonies.conversion.update');
});

// ── Affichage des listes ─ docs/fonctionnalites/affichage-et-lecture.md ─────
Route::post('/affichage', [DisplayPreferenceController::class, 'layout'])->middleware('throttle:30,1')->name('preferences.layout');

// ── Vidéos ─ docs/fonctionnalites/videos.md ────────────────────────────────
// Consultation publique (vidéos, shorts, directs, audios, textes) ; commentaires réservés aux personnes connectées.
Route::get('/videos', [VideoController::class, 'index'])->name('videos.index');
Route::whereUuid(['id', 'comment'])->group(function () {
    Route::get('/videos/{id}',          [VideoController::class, 'show'])->name('videos.show');
    Route::post('/videos/{id}/view',    [VideoController::class, 'recordView'])->middleware('throttle:60,1')->name('videos.view');
    Route::get('/comments/{comment}/replies', [CommentController::class, 'replies'])->name('comments.replies');

    Route::middleware('auth')->group(function () {
        Route::post('/videos/{id}/comments',  [CommentController::class, 'store'])->middleware('throttle:10,1')->name('videos.comments.store');
        Route::put('/comments/{comment}',     [CommentController::class, 'update'])->middleware('throttle:20,1')->name('comments.update');
        Route::delete('/comments/{comment}',  [CommentController::class, 'destroy'])->name('comments.destroy');
    });
});

// ── « J'aime » sur les commentaires ─ docs/fonctionnalites/jaime-commentaires.md ──
// Mêmes actions et réponses JSON que l'API (Api\CommentLikeController), session + CSRF.
Route::middleware(['auth', 'throttle:60,1'])->group(function () {
    $likes = \App\Http\Controllers\Api\CommentLikeController::class;
    Route::post  ('/comments/{id}/like', [$likes, 'likeTestimonyComment'])->whereUuid('id')->name('comments.like');
    Route::delete('/comments/{id}/like', [$likes, 'unlikeTestimonyComment'])->whereUuid('id')->name('comments.unlike');
    Route::post  ('/evenements/{id}/commentaires/{commentId}/like', [$likes, 'likeEventComment'])->whereUuid(['id', 'commentId'])->name('events.comments.like');
    Route::delete('/evenements/{id}/commentaires/{commentId}/like', [$likes, 'unlikeEventComment'])->whereUuid(['id', 'commentId'])->name('events.comments.unlike');
    Route::post  ('/verset-du-jour/{id}/commentaires/{commentId}/like', [$likes, 'likeVerseComment'])->whereNumber('id')->whereUuid('commentId')->name('daily-verse.comments.like');
    Route::delete('/verset-du-jour/{id}/commentaires/{commentId}/like', [$likes, 'unlikeVerseComment'])->whereNumber('id')->whereUuid('commentId')->name('daily-verse.comments.unlike');
});

// ── Témoignages en direct ─ docs/fonctionnalites/lives.md ─────────────────
// Pages : Web\LiveController · actions JSON (partagées avec l'API mobile) : Api\LiveController
Route::get('/lives',                    [LiveController::class, 'index'])->name('lives.index');
// Modérateurs et administrateurs ; aussi les gestionnaires d'un événement pour son direct
// (?event= / event_id, vérifié dans Web\LiveController et LiveService::start).
Route::middleware('auth')->group(function () {
    Route::get('/lives/create',         [LiveController::class, 'create'])->name('lives.create');
    Route::post('/lives',               [LiveController::class, 'store'])->name('lives.store');
});
Route::whereUuid('id')->group(function () {
    Route::get('/lives/{id}',                     [LiveController::class, 'show'])->name('lives.show');
    Route::post('/lives/{id}/viewer-token',       [LiveActions::class, 'viewerToken'])->name('lives.viewer-token');
    Route::get('/lives/{id}/comments',            [LiveActions::class, 'comments'])->name('lives.comments');
    Route::get('/lives/{id}/stats',               [LiveActions::class, 'stats'])->name('lives.stats');
    Route::get('/lives/{id}/stage',               [LiveActions::class, 'stage'])->name('lives.stage');
    Route::get('/lives/{id}/viewers',             [LiveActions::class, 'viewers'])->middleware('throttle:30,1')->name('lives.viewers');
    // Présence d'un spectateur (direct YouTube, sans LiveKit) : invités compris
    Route::post('/lives/{id}/heartbeat',          [LiveActions::class, 'heartbeat'])->middleware('throttle:12,1')->name('lives.heartbeat');

    Route::middleware('auth')->group(function () {
        Route::get('/lives/{id}/studio',                  [LiveController::class, 'studio'])->name('lives.studio');
        Route::post('/lives/{id}/host-token',             [LiveActions::class, 'hostToken'])->name('lives.host-token');
        Route::post('/lives/{id}/go-live',                [LiveActions::class, 'goLive'])->name('lives.go-live');
        Route::post('/lives/{id}/end',                    [LiveActions::class, 'end'])->name('lives.end');
        Route::post('/lives/{id}/comments',               [LiveActions::class, 'storeComment'])->name('lives.comments.store');
        Route::delete('/lives/{id}/comments/{commentId}', [LiveActions::class, 'hideComment'])->name('lives.comments.hide');
        Route::post('/lives/{id}/comments/{commentId}/pin', [LiveActions::class, 'pinComment'])->name('lives.comments.pin');
        Route::delete('/lives/{id}/pin',                  [LiveActions::class, 'unpinComment'])->name('lives.comments.unpin');
        Route::post('/lives/{id}/bans',                   [LiveActions::class, 'ban'])->name('lives.bans');
        Route::post('/lives/{id}/reactions',              [LiveActions::class, 'react'])->name('lives.reactions');
        // Intervenants : docs/fonctionnalites/lives-intervenants.md
        Route::post('/lives/{id}/stage/requests',         [LiveActions::class, 'requestStage'])->name('lives.stage.request');
        Route::delete('/lives/{id}/stage/requests/mine',  [LiveActions::class, 'withdrawStage'])->name('lives.stage.withdraw');
        Route::post('/lives/{id}/stage/accept',           [LiveActions::class, 'acceptStage'])->name('lives.stage.accept');
        Route::post('/lives/{id}/stage/settings',         [LiveActions::class, 'stageSettings'])->name('lives.stage.settings');
        Route::post('/lives/{id}/stage/{speakerId}/invite',  [LiveActions::class, 'inviteSpeaker'])->name('lives.stage.invite');
        Route::post('/lives/{id}/stage/{speakerId}/decline', [LiveActions::class, 'declineSpeaker'])->name('lives.stage.decline');
        Route::post('/lives/{id}/stage/{speakerId}/remove',  [LiveActions::class, 'removeSpeaker'])->name('lives.stage.remove');
    });
});

// ── Événements ─ docs/fonctionnalites/evenements.md ───────────────────────
// Pages et actions : Web\EventController (règles métier : App\Services\EventService, partagé avec l'API).
Route::get('/evenements', [EventController::class, 'index'])->name('events.index');
// Événements terminés, du plus récent au plus ancien, avec leurs témoignages
Route::get('/evenements/passes', [EventController::class, 'past'])->name('events.past');
// Cartes / liste compacte (cookie event_layout, aussi sans compte)
Route::post('/evenements/affichage', [EventController::class, 'layout'])->middleware('throttle:30,1')->name('events.layout');
Route::middleware('auth')->group(function () {
    Route::get('/evenements/creer', [EventController::class, 'create'])->name('events.create');
    Route::post('/evenements',      [EventController::class, 'store'])->middleware('throttle:20,1')->name('events.store');
    // Modération des événements signalés / non confirmés / masqués
    Route::middleware('role:moderateur,administrateur')->group(function () {
        Route::get('/evenements/moderation', [EventController::class, 'moderation'])->name('events.moderation.index');
        Route::whereUuid('id')->group(function () {
            Route::post('/evenements/moderation/{id}/masquer',  [EventController::class, 'hide'])->name('events.moderation.hide');
            Route::post('/evenements/moderation/{id}/retablir', [EventController::class, 'restore'])->name('events.moderation.restore');
            Route::post('/evenements/moderation/{id}/classer',  [EventController::class, 'dismiss'])->name('events.moderation.dismiss');
        });
    });
});
Route::whereUuid(['id', 'comment', 'image', 'user'])->group(function () {
    Route::get('/evenements/{id}', [EventController::class, 'show'])->name('events.show');

    Route::middleware('auth')->group(function () {
        Route::get('/evenements/{id}/modifier',     [EventController::class, 'edit'])->name('events.edit');
        Route::put('/evenements/{id}',              [EventController::class, 'update'])->middleware('throttle:30,1')->name('events.update');
        Route::delete('/evenements/{id}',           [EventController::class, 'destroy'])->name('events.destroy');
        Route::get('/evenements/{id}/participants', [EventController::class, 'participants'])->name('events.participants');
        // Images de couverture (carrousel)
        Route::post('/evenements/{id}/images',                  [EventController::class, 'storeImage'])->middleware('throttle:30,1')->name('events.images.store');
        Route::delete('/evenements/{id}/images/{image}',        [EventController::class, 'destroyImage'])->name('events.images.destroy');
        Route::post('/evenements/{id}/images/{image}/premiere', [EventController::class, 'coverImage'])->name('events.images.cover');
        // Participation : « Je participe » / « Je ne participe pas »
        Route::post('/evenements/{id}/participation',   [EventController::class, 'participate'])->middleware('throttle:30,1')->name('events.participate');
        Route::delete('/evenements/{id}/participation', [EventController::class, 'cancelParticipation'])->middleware('throttle:30,1')->name('events.participation.cancel');
        // Commentaires (témoignages des participants)
        Route::post('/evenements/{id}/commentaires',                        [EventController::class, 'storeComment'])->middleware('throttle:10,1')->name('events.comments.store');
        Route::delete('/evenements/{id}/commentaires/{comment}',            [EventController::class, 'destroyComment'])->name('events.comments.destroy');
        Route::post('/evenements/{id}/commentaires/{comment}/temoignage',   [EventController::class, 'promoteComment'])->middleware('throttle:30,1')->name('events.comments.promote');
        // Co-gestionnaires (2 au plus) : ajout par un responsable ; retrait par un responsable ou soi-même
        Route::post('/evenements/{id}/co-gestionnaires',          [EventController::class, 'addManager'])->middleware('throttle:20,1')->name('events.managers.store');
        Route::delete('/evenements/{id}/co-gestionnaires/{user}', [EventController::class, 'removeManager'])->middleware('throttle:20,1')->name('events.managers.destroy');
        // Report / annulation (gestionnaires), signalement, sondage d'après l'événement
        Route::post('/evenements/{id}/reporter',  [EventController::class, 'postpone'])->middleware('throttle:20,1')->name('events.postpone');
        Route::post('/evenements/{id}/annuler',   [EventController::class, 'cancel'])->middleware('throttle:20,1')->name('events.cancel');
        Route::post('/evenements/{id}/signaler',  [EventController::class, 'report'])->middleware('throttle:20,1')->name('events.report');
        Route::post('/evenements/{id}/avis',      [EventController::class, 'review'])->middleware('throttle:20,1')->name('events.review');
    });
});

// ── Requêtes et sessions de prière ─ docs/fonctionnalites/requetes-de-priere.md, sessions-de-priere.md
// Pages : Web\PrayerRequestController, Web\PrayerSessionController (règles : App\Services\PrayerRequests,
// PrayerSessions). La salle d'une session est un direct : studio et page de visionnage des directs.
Route::get('/priere/requetes', [\App\Http\Controllers\Web\PrayerRequestController::class, 'index'])->name('prayer.requests.index');
Route::get('/priere/sessions', [\App\Http\Controllers\Web\PrayerSessionController::class, 'index'])->name('prayer.sessions.index');
Route::middleware('auth')->group(function () {
    Route::get('/priere/requetes/nouvelle', [\App\Http\Controllers\Web\PrayerRequestController::class, 'create'])->name('prayer.requests.create');
    Route::post('/priere/requetes',         [\App\Http\Controllers\Web\PrayerRequestController::class, 'store'])->middleware('throttle:20,1')->name('prayer.requests.store');
    Route::get('/priere/sessions/nouvelle', [\App\Http\Controllers\Web\PrayerSessionController::class, 'create'])->name('prayer.sessions.create');
    Route::post('/priere/sessions',         [\App\Http\Controllers\Web\PrayerSessionController::class, 'store'])->middleware('throttle:20,1')->name('prayer.sessions.store');
    // Modération des requêtes signalées
    Route::middleware('role:moderateur,administrateur')->whereUuid('id')->group(function () {
        Route::get('/priere/moderation',               [\App\Http\Controllers\Web\PrayerRequestController::class, 'moderation'])->name('prayer.moderation.index');
        Route::post('/priere/moderation/{id}/retirer', [\App\Http\Controllers\Web\PrayerRequestController::class, 'hide'])->name('prayer.moderation.hide');
        Route::post('/priere/moderation/{id}/retablir', [\App\Http\Controllers\Web\PrayerRequestController::class, 'restore'])->name('prayer.moderation.restore');
    });
});
Route::whereUuid(['id', 'message'])->group(function () {
    Route::get('/priere/requetes/{id}',     [\App\Http\Controllers\Web\PrayerRequestController::class, 'show'])->name('prayer.requests.show');
    Route::get('/priere/sessions/{id}',     [\App\Http\Controllers\Web\PrayerSessionController::class, 'show'])->name('prayer.sessions.show');
    Route::get('/priere/sessions/{id}/salle', [\App\Http\Controllers\Web\PrayerSessionController::class, 'room'])->name('prayer.sessions.room');

    Route::middleware('auth')->group(function () {
        Route::delete('/priere/requetes/{id}',                     [\App\Http\Controllers\Web\PrayerRequestController::class, 'destroy'])->name('prayer.requests.destroy');
        Route::post('/priere/requetes/{id}/je-prie',               [\App\Http\Controllers\Web\PrayerRequestController::class, 'pray'])->middleware('throttle:60,1')->name('prayer.requests.pray');
        Route::post('/priere/requetes/{id}/je-prie/commencer',     [\App\Http\Controllers\Web\PrayerRequestController::class, 'startPrayer'])->middleware('throttle:30,1')->name('prayer.requests.pray.start');
        Route::post('/priere/requetes/{id}/exaucee',               [\App\Http\Controllers\Web\PrayerRequestController::class, 'answered'])->name('prayer.requests.answered');
        Route::delete('/priere/requetes/{id}/exaucee',             [\App\Http\Controllers\Web\PrayerRequestController::class, 'reopen'])->name('prayer.requests.reopen');
        Route::post('/priere/requetes/{id}/messages',              [\App\Http\Controllers\Web\PrayerRequestController::class, 'storeMessage'])->middleware('throttle:20,1')->name('prayer.requests.messages.store');
        Route::delete('/priere/requetes/{id}/messages/{message}',  [\App\Http\Controllers\Web\PrayerRequestController::class, 'destroyMessage'])->name('prayer.requests.messages.destroy');
        Route::post('/priere/requetes/{id}/signaler',              [\App\Http\Controllers\Web\PrayerRequestController::class, 'report'])->middleware('throttle:20,1')->name('prayer.requests.report');

        Route::get('/priere/sessions/{id}/modifier',      [\App\Http\Controllers\Web\PrayerSessionController::class, 'edit'])->name('prayer.sessions.edit');
        Route::put('/priere/sessions/{id}',               [\App\Http\Controllers\Web\PrayerSessionController::class, 'update'])->middleware('throttle:30,1')->name('prayer.sessions.update');
        Route::delete('/priere/sessions/{id}',            [\App\Http\Controllers\Web\PrayerSessionController::class, 'destroy'])->name('prayer.sessions.destroy');
        Route::post('/priere/sessions/{id}/annuler',      [\App\Http\Controllers\Web\PrayerSessionController::class, 'cancel'])->name('prayer.sessions.cancel');
        Route::post('/priere/sessions/{id}/inscription',  [\App\Http\Controllers\Web\PrayerSessionController::class, 'join'])->middleware('throttle:30,1')->name('prayer.sessions.join');
        Route::delete('/priere/sessions/{id}/inscription', [\App\Http\Controllers\Web\PrayerSessionController::class, 'leave'])->middleware('throttle:30,1')->name('prayer.sessions.leave');
        Route::post('/priere/sessions/{id}/ouvrir',       [\App\Http\Controllers\Web\PrayerSessionController::class, 'open'])->middleware('throttle:10,1')->name('prayer.sessions.open');
    });
});

// ── Authenticated ─────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    // Mon fil : comptes suivis et suggestions (App\Services\Recommendations::personalFeed)
    Route::get('/mon-fil',               [FeedController::class, 'personal'])->name('feed.personal');

    // Recherche de personnes pour désigner un gestionnaire (JSON, components/user-picker)
    Route::get('/personnes/recherche',   UserSearchController::class)->middleware('throttle:60,1')->name('users.search');
    // Mentions « @ » des commentaires (JSON, components/mention-input) ─ docs/fonctionnalites/mentions.md
    Route::get('/personnes/mentions',    \App\Http\Controllers\Web\MentionSearchController::class)->middleware('throttle:120,1')->name('users.mentions');

    // Testimonies
    Route::get('/publish',               [TestimonyController::class, 'create'])->name('publish');
    Route::post('/testimonies',          [TestimonyController::class, 'store'])->name('testimonies.store');
    // Vidéo envoyée par le navigateur à Bunny Stream (session + CSRF ; mêmes règles que l'API) ─ docs/fonctionnalites/videos-bunny-stream.md
    Route::post('/testimonies/{id}/video/signature', [\App\Http\Controllers\Api\VideoUploadController::class, 'signature'])->middleware('throttle:30,1')->name('testimonies.video.signature');
    Route::post('/testimonies/{id}/video/complete',  [\App\Http\Controllers\Api\VideoUploadController::class, 'complete'])->middleware('throttle:30,1')->name('testimonies.video.complete');
    Route::get('/mes-temoignages',       [TestimonyController::class, 'myTestimonies'])->name('testimonies.mine');
    Route::post('/testimonies/{id}/report',    [TestimonyController::class, 'report'])->name('testimonies.report');
    // Supprimer : l'auteur ou un administrateur (jamais un modérateur).
    Route::delete('/testimonies/{id}',          [TestimonyController::class, 'destroy'])->name('testimonies.destroy');
    Route::post('/testimonies/{id}/reactions', [TestimonyController::class, 'storeReaction'])->name('testimonies.reactions.store');
    Route::post('/testimonies/{id}/priere/commencer', [TestimonyController::class, 'startPrayer'])->middleware('throttle:30,1')->name('testimonies.prayer.start');
    Route::put('/testimonies/{id}/save',       [TestimonyController::class, 'saveTestimony'])->name('testimonies.save');
    Route::post('/testimonies/{id}/comments',  [TestimonyController::class, 'storeComment'])->name('testimonies.comments.store');
    // Partage interne « X a partagé » ─ docs/fonctionnalites/partage-interne.md
    Route::post('/testimonies/{id}/repost',    [\App\Http\Controllers\Web\RepostController::class, 'store'])->middleware('throttle:30,1')->name('testimonies.repost');
    Route::delete('/testimonies/{id}/repost',  [\App\Http\Controllers\Web\RepostController::class, 'destroy'])->name('testimonies.unrepost');

    // Carnet privé : visible de son seul auteur ─ docs/fonctionnalites/carnet-prive.md
    Route::get('/carnet',                       [JournalController::class, 'index'])->name('journal.index');
    Route::whereUuid('id')->middleware('throttle:30,1')->group(function () {
        Route::post('/carnet/{id}/partager',    [JournalController::class, 'share'])->name('journal.share');
        Route::delete('/carnet/{id}',           [JournalController::class, 'destroy'])->name('journal.destroy');
        Route::post('/testimonies/{id}/carnet', [JournalController::class, 'store'])->name('journal.store');
    });

    // Paroles prophétiques du carnet ─ docs/fonctionnalites/paroles-prophetiques.md
    Route::get('/carnet/paroles',               [ProphecyController::class, 'index'])->name('prophecies.index');
    Route::get('/carnet/paroles/nouvelle',      [ProphecyController::class, 'create'])->name('prophecies.create');
    Route::post('/carnet/paroles',              [ProphecyController::class, 'store'])->middleware('throttle:30,1')->name('prophecies.store');
    Route::whereUuid('id')->group(function () {
        Route::get('/carnet/paroles/{id}',             [ProphecyController::class, 'show'])->name('prophecies.show');
        Route::get('/carnet/paroles/{id}/modifier',    [ProphecyController::class, 'edit'])->name('prophecies.edit');
        Route::put('/carnet/paroles/{id}',             [ProphecyController::class, 'update'])->middleware('throttle:30,1')->name('prophecies.update');
        Route::delete('/carnet/paroles/{id}',          [ProphecyController::class, 'destroy'])->name('prophecies.destroy');
        Route::post('/carnet/paroles/{id}/accomplie',  [ProphecyController::class, 'fulfill'])->name('prophecies.fulfill');
        Route::post('/carnet/paroles/{id}/en-attente', [ProphecyController::class, 'reopen'])->name('prophecies.reopen');
        // Journal de prière
        Route::post('/carnet/paroles/{id}/prieres',            [ProphecyController::class, 'pray'])->middleware('throttle:60,1')->name('prophecies.pray');
        Route::post('/carnet/paroles/{id}/prieres/commencer',  [ProphecyController::class, 'startPrayer'])->middleware('throttle:30,1')->name('prophecies.pray.start'); // temps de prière
        Route::delete('/carnet/paroles/{id}/prieres/{prayer}', [ProphecyController::class, 'deletePrayer'])->whereNumber('prayer')->name('prophecies.prayers.destroy');
    });

    // Profile
    // Administrateurs de compte : agir au nom d'un compte ─ docs/fonctionnalites/administrateurs-de-compte.md
    Route::post('/comptes/{id}/agir', [\App\Http\Controllers\Web\AccountSwitchController::class, 'switch'])->whereUuid('id')->middleware('throttle:30,1')->name('accounts.switch');
    Route::get('/profile/edit',          [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile',               [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/saved',         [ProfileController::class, 'savedTestimonies'])->name('profile.saved');
    Route::get('/profile/abonnements',   [CommunityController::class, 'following'])->name('profile.following'); // Mes abonnements
    Route::get('/profile/abonnes',       [CommunityController::class, 'followers'])->name('profile.followers'); // Mes abonnés
    Route::get('/profile/settings',      [ProfileController::class, 'settings'])->name('profile.settings');
    Route::put('/profile/settings',      [ProfileController::class, 'updateSettings'])->name('profile.settings.update');
    // Localisation privée pour classer les fils par zone ─ docs/fonctionnalites/localisation.md
    Route::put('/profile/localisation',    [\App\Http\Controllers\Web\LocationController::class, 'update'])->middleware('throttle:20,1')->name('profile.location.update');
    Route::delete('/profile/localisation', [\App\Http\Controllers\Web\LocationController::class, 'destroy'])->name('profile.location.destroy');
    // Vérification des comptes personnels ─ docs/fonctionnalites/verification-des-comptes.md
    Route::get('/profile/verification',  [\App\Http\Controllers\Web\AccountVerificationController::class, 'show'])->name('profile.verification');
    Route::post('/profile/verification', [\App\Http\Controllers\Web\AccountVerificationController::class, 'store'])->middleware('throttle:10,60')->name('profile.verification.store');
    // Gestionnaires d'une organisation (2 au plus) et organisations gérées ─ docs/fonctionnalites/evenements.md
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/profile/gestionnaires',                         [ProfileController::class, 'addOrganizationManager'])->name('profile.managers.store');
        Route::delete('/profile/gestionnaires/{user}',                [ProfileController::class, 'removeOrganizationManager'])->whereUuid('user')->name('profile.managers.destroy');
        Route::delete('/profile/organisations-gerees/{organization}', [ProfileController::class, 'leaveOrganization'])->whereUuid('organization')->name('profile.managed.leave');
    });

    // Notifications
    Route::get('/notifications',         [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
});

// ── Moderation ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:moderateur,administrateur'])->prefix('moderation')->name('moderation.')->group(function () {
    // Back-office des témoignages : onglets, actions groupées, fiche, statistiques (docs/fonctionnalites/gestion-des-temoignages.md)
    Route::get('/',                  [ModerationController::class, 'index'])->name('index');
    Route::get('/tableau-de-bord',   [ModerationController::class, 'dashboard'])->name('dashboard');
    Route::post('/actions-groupees', [ModerationController::class, 'bulk'])->name('bulk');
    Route::get('/{id}',              [ModerationController::class, 'show'])->name('show');
    Route::get('/{id}/statistiques', [ModerationController::class, 'stats'])->name('stats');
    Route::post('/{id}/approve',     [ModerationController::class, 'approve'])->name('approve');
    Route::post('/{id}/reject',      [ModerationController::class, 'reject'])->name('reject');
    Route::post('/{id}/hide-proofs', [ModerationController::class, 'hideProofs'])->name('hide-proofs'); // docs/fonctionnalites/preuves.md
    Route::post('/{id}/label',       [ModerationController::class, 'verify'])->name('verify');
    Route::post('/{id}/une',         [ModerationController::class, 'feature'])->name('feature');
    Route::delete('/{id}',           [ModerationController::class, 'trash'])->name('trash');
    Route::post('/{id}/restaurer',   [ModerationController::class, 'restore'])->name('restore');
    Route::delete('/{id}/definitif', [ModerationController::class, 'forceDelete'])->name('force-delete');
});

// ── Admin ──────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:administrateur'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/',                          [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/users',                     [AdminController::class, 'users'])->name('users.index');
    Route::get('/users/{id}',                [AdminController::class, 'showUser'])->name('users.show');
    Route::put('/users/{id}',                [AdminController::class, 'updateUser'])->name('users.update');
    // Suppression définitive d'un compte (super-administrateurs) ─ docs/fonctionnalites/confidentialite-et-suppression.md
    Route::delete('/users/{id}',             [AdminController::class, 'deleteUser'])->whereUuid('id')->name('users.destroy');
    // Types de témoignages autorisés pour ce profil ─ docs/fonctionnalites/types-de-temoignages.md
    Route::put('/users/{id}/publication',    [AdminController::class, 'updatePublishing'])->whereUuid('id')->name('users.publishing.update');
    // Comptes organisation ─ docs/fonctionnalites/comptes-organisation.md
    Route::post('/users/{id}/verify',        [AdminController::class, 'verifyOrganization'])->name('users.verify');
    Route::post('/users/{id}/reject-verification', [AdminController::class, 'rejectOrganization'])->name('users.reject-verification');
    // Comptes personnels ─ docs/fonctionnalites/verification-des-comptes.md
    Route::post('/users/{id}/revoke-verification', [\App\Http\Controllers\Web\AccountVerificationController::class, 'revoke'])->name('users.revoke-verification');
    // Notification push de test vers les appareils d'un membre (super-administrateurs) ─ docs/fonctionnalites/notifications-push.md
    Route::post('/users/{id}/push-test', [AdminController::class, 'pushTest'])->whereUuid('id')->middleware('throttle:10,1')->name('users.push-test');
    // Badge « Approuvé » manuel ─ docs/fonctionnalites/badge-approuve.md
    Route::post('/users/{id}/badge',   [\App\Http\Controllers\Web\AccountApprovalController::class, 'grant'])->name('users.approval.grant');
    Route::delete('/users/{id}/badge', [\App\Http\Controllers\Web\AccountApprovalController::class, 'revoke'])->name('users.approval.revoke');
    Route::get('/verifications',                   [\App\Http\Controllers\Web\AccountVerificationController::class, 'index'])->name('verifications.index');
    Route::get('/verifications/{id}',              [\App\Http\Controllers\Web\AccountVerificationController::class, 'adminShow'])->whereUuid('id')->name('verifications.show');
    Route::get('/verifications/{id}/pieces/{index}', [\App\Http\Controllers\Web\AccountVerificationController::class, 'document'])->whereUuid('id')->whereNumber('index')->name('verifications.document');
    Route::post('/verifications/{id}/approve',     [\App\Http\Controllers\Web\AccountVerificationController::class, 'approve'])->whereUuid('id')->name('verifications.approve');
    Route::post('/verifications/{id}/reject',      [\App\Http\Controllers\Web\AccountVerificationController::class, 'reject'])->whereUuid('id')->name('verifications.reject');
    Route::get('/content',                   [AdminController::class, 'content'])->name('content.index');
    Route::get('/categories',                [AdminController::class, 'categories'])->name('categories.index');
    Route::post('/categories',               [AdminController::class, 'storeCategory'])->name('categories.store');
    Route::put('/categories/{id}',           [AdminController::class, 'updateCategory'])->name('categories.update');
    Route::delete('/categories/{id}',        [AdminController::class, 'deleteCategory'])->name('categories.destroy');
    // Types d'événements ─ docs/fonctionnalites/types-evenements.md
    Route::get('/types-evenements',              [\App\Http\Controllers\Web\EventTypeController::class, 'index'])->name('event-types.index');
    Route::post('/types-evenements',             [\App\Http\Controllers\Web\EventTypeController::class, 'store'])->name('event-types.store');
    Route::put('/types-evenements/ordre',        [\App\Http\Controllers\Web\EventTypeController::class, 'reorder'])->name('event-types.reorder');
    Route::put('/types-evenements/{id}',         [\App\Http\Controllers\Web\EventTypeController::class, 'update'])->whereNumber('id')->name('event-types.update');
    Route::post('/types-evenements/{id}/move',   [\App\Http\Controllers\Web\EventTypeController::class, 'move'])->whereNumber('id')->name('event-types.move');
    Route::post('/types-evenements/{id}/toggle', [\App\Http\Controllers\Web\EventTypeController::class, 'toggle'])->whereNumber('id')->name('event-types.toggle');
    Route::delete('/types-evenements/{id}',      [\App\Http\Controllers\Web\EventTypeController::class, 'destroy'])->whereNumber('id')->name('event-types.destroy');
    // Demandes de suppression de compte faites sans connexion ─ docs/fonctionnalites/confidentialite-et-suppression.md
    Route::get('/demandes-suppression',                 [\App\Http\Controllers\Web\AccountDeletionRequestController::class, 'index'])->name('deletion-requests.index');
    Route::delete('/demandes-suppression/{id}/compte',  [\App\Http\Controllers\Web\AccountDeletionRequestController::class, 'deleteAccount'])->whereUuid('id')->name('deletion-requests.delete-account');
    Route::post('/demandes-suppression/{id}/traitee',   [\App\Http\Controllers\Web\AccountDeletionRequestController::class, 'markDone'])->whereUuid('id')->name('deletion-requests.done');
    Route::post('/demandes-suppression/{id}/classer',   [\App\Http\Controllers\Web\AccountDeletionRequestController::class, 'reject'])->whereUuid('id')->name('deletion-requests.reject');
    Route::get('/settings',                  [AdminController::class, 'settings'])->name('settings');
    Route::put('/settings',                  [AdminController::class, 'updateSettings'])->name('settings.update');
    Route::get('/documentation/{page?}',     [DocumentationController::class, 'show'])
         ->where('page', '[A-Za-z0-9\-_/]+')->name('documentation');
});
