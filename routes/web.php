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
| Web Routes — TestiApp
|--------------------------------------------------------------------------
*/

// ── Auth ─────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',            [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',           [AuthController::class, 'login']);
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

// ── Public ────────────────────────────────────────────────────────────────
Route::get('/',                     [HomeController::class, 'index'])->name('home');
Route::get('/explore',              [ExploreController::class, 'index'])->name('explore');
Route::get('/bible',                [BibleController::class, 'reader'])->name('bible.reader');
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
Route::middleware('auth')->group(function () {
    Route::get('/evenements/creer', [EventController::class, 'create'])->name('events.create');
    Route::post('/evenements',      [EventController::class, 'store'])->middleware('throttle:20,1')->name('events.store');
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

    // Testimonies
    Route::get('/publish',               [TestimonyController::class, 'create'])->name('publish');
    Route::post('/testimonies',          [TestimonyController::class, 'store'])->name('testimonies.store');
    Route::get('/mes-temoignages',       [TestimonyController::class, 'myTestimonies'])->name('testimonies.mine');
    Route::post('/testimonies/{id}/report',    [TestimonyController::class, 'report'])->name('testimonies.report');
    Route::post('/testimonies/{id}/reactions', [TestimonyController::class, 'storeReaction'])->name('testimonies.reactions.store');
    Route::put('/testimonies/{id}/save',       [TestimonyController::class, 'saveTestimony'])->name('testimonies.save');
    Route::post('/testimonies/{id}/comments',  [TestimonyController::class, 'storeComment'])->name('testimonies.comments.store');

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
        Route::delete('/carnet/paroles/{id}/prieres/{prayer}', [ProphecyController::class, 'deletePrayer'])->whereNumber('prayer')->name('prophecies.prayers.destroy');
    });

    // Profile
    Route::get('/profile/edit',          [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile',               [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/saved',         [ProfileController::class, 'savedTestimonies'])->name('profile.saved');
    Route::get('/profile/abonnements',   [CommunityController::class, 'following'])->name('profile.following'); // Mes abonnements
    Route::get('/profile/abonnes',       [CommunityController::class, 'followers'])->name('profile.followers'); // Mes abonnés
    Route::get('/profile/settings',      [ProfileController::class, 'settings'])->name('profile.settings');
    Route::put('/profile/settings',      [ProfileController::class, 'updateSettings'])->name('profile.settings.update');
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
    Route::get('/',                  [ModerationController::class, 'index'])->name('index');
    Route::get('/{id}',              [ModerationController::class, 'show'])->name('show');
    Route::post('/{id}/approve',     [ModerationController::class, 'approve'])->name('approve');
    Route::post('/{id}/reject',      [ModerationController::class, 'reject'])->name('reject');
    Route::post('/{id}/hide-proofs', [ModerationController::class, 'hideProofs'])->name('hide-proofs'); // docs/fonctionnalites/preuves.md
});

// ── Admin ──────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:administrateur'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/',                          [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/users',                     [AdminController::class, 'users'])->name('users.index');
    Route::get('/users/{id}',                [AdminController::class, 'showUser'])->name('users.show');
    Route::put('/users/{id}',                [AdminController::class, 'updateUser'])->name('users.update');
    // Comptes organisation ─ docs/fonctionnalites/comptes-organisation.md
    Route::post('/users/{id}/verify',        [AdminController::class, 'verifyOrganization'])->name('users.verify');
    Route::post('/users/{id}/reject-verification', [AdminController::class, 'rejectOrganization'])->name('users.reject-verification');
    Route::get('/content',                   [AdminController::class, 'content'])->name('content.index');
    Route::get('/categories',                [AdminController::class, 'categories'])->name('categories.index');
    Route::post('/categories',               [AdminController::class, 'storeCategory'])->name('categories.store');
    Route::put('/categories/{id}',           [AdminController::class, 'updateCategory'])->name('categories.update');
    Route::delete('/categories/{id}',        [AdminController::class, 'deleteCategory'])->name('categories.destroy');
    Route::get('/settings',                  [AdminController::class, 'settings'])->name('settings');
    Route::put('/settings',                  [AdminController::class, 'updateSettings'])->name('settings.update');
    Route::get('/documentation/{page?}',     [DocumentationController::class, 'show'])
         ->where('page', '[A-Za-z0-9\-_/]+')->name('documentation');
});
