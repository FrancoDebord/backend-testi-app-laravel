<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Event;
use App\Models\Follow;
use App\Models\LiveSession;
use App\Models\PrayerRequest;
use App\Models\PrayerSession;
use App\Models\User;
use App\Services\LiveKit\LiveKitClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Requêtes et sessions de prière (API et site).
 * Voir docs/fonctionnalites/requetes-de-priere.md, sessions-de-priere.md
 */
class PrayerTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private User $friend;   // abonné de l'auteur
    private User $stranger;
    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'livekit.url'        => 'wss://lk.test',
            'livekit.api_key'    => 'devkey',
            'livekit.api_secret' => 'secret-de-test-suffisamment-long',
        ]);
        $this->app->forgetInstance(LiveKitClient::class);
        Http::fake(['lk.test/*' => Http::response(['participants' => []], 200)]);

        $this->author    = $this->makeUser('auteur');
        $this->friend    = $this->makeUser('ami');
        $this->stranger  = $this->makeUser('inconnu');
        $this->moderator = $this->makeUser('modo', ['role' => 'moderateur']);
        Follow::create(['follower_id' => $this->friend->id, 'following_id' => $this->author->id]);
    }

    private function makeUser(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => ucfirst($name), 'email' => "{$name}@example.com",
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    private function guest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    private function postRequest(array $extra = [], ?User $as = null): PrayerRequest
    {
        $id = $this->actingAs($as ?? $this->author)->postJson('/api/v1/prayer/requests', array_merge([
            'body' => 'Priez pour la guérison de ma mère, hospitalisée depuis lundi.',
        ], $extra))->assertCreated()->json('data.id');

        return PrayerRequest::findOrFail($id);
    }

    // ─── Requêtes ────────────────────────────────────────────────────────────

    public function test_une_requete_est_publiee_directement_et_visible_de_tous(): void
    {
        $prayer = $this->postRequest();

        $this->guest()->getJson('/api/v1/prayer/requests?scope=feed')->assertOk()
            ->assertJsonPath('data.0.id', $prayer->id)
            ->assertJsonPath('data.0.type', 'prayer_request')
            ->assertJsonPath('data.0.author.displayName', 'Auteur')
            ->assertJsonPath('data.0.hasPrayed', false)
            ->assertJsonPath('data.0.isAnswered', false);
    }

    public function test_requete_anonyme_masque_le_nom_sauf_pour_l_auteur_et_la_moderation(): void
    {
        $prayer = $this->postRequest(['is_anonymous' => true]);

        $this->actingAs($this->stranger)->getJson("/api/v1/prayer/requests/{$prayer->id}")->assertOk()
            ->assertJsonPath('data.author', null)->assertJsonPath('data.isAnonymous', true);
        $this->actingAs($this->author)->getJson("/api/v1/prayer/requests/{$prayer->id}")
            ->assertJsonPath('data.author.id', $this->author->id);
        $this->actingAs($this->moderator)->getJson("/api/v1/prayer/requests/{$prayer->id}")
            ->assertJsonPath('data.author.id', $this->author->id);
        // « following » : jamais d'anonymes (l'anonymat serait trahi).
        $this->actingAs($this->friend)->getJson('/api/v1/prayer/requests?scope=following')->assertJsonCount(0, 'data');
    }

    public function test_visibilite_abonnes_et_privee(): void
    {
        $followers = $this->postRequest(['visibility' => 'followers']);
        $private   = $this->postRequest(['visibility' => 'private']);

        $this->actingAs($this->friend)->getJson("/api/v1/prayer/requests/{$followers->id}")->assertOk();
        $this->actingAs($this->stranger)->getJson("/api/v1/prayer/requests/{$followers->id}")->assertNotFound();
        $this->actingAs($this->friend)->getJson("/api/v1/prayer/requests/{$private->id}")->assertNotFound();
        $this->actingAs($this->author)->getJson("/api/v1/prayer/requests/{$private->id}")->assertOk();

        $this->actingAs($this->friend)->getJson('/api/v1/prayer/requests?scope=following')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $followers->id);
        $this->actingAs($this->stranger)->getJson('/api/v1/prayer/requests')->assertJsonCount(0, 'data');
        $this->actingAs($this->author)->getJson('/api/v1/prayer/requests?scope=mine')->assertJsonCount(2, 'data');
    }

    public function test_je_prie_bascule_et_compte(): void
    {
        $prayer = $this->postRequest();

        $this->actingAs($this->friend)->postJson("/api/v1/prayer/requests/{$prayer->id}/pray")->assertOk()
            ->assertJsonPath('data.hasPrayed', true)->assertJsonPath('data.prayerCount', 1);
        $this->actingAs($this->friend)->postJson("/api/v1/prayer/requests/{$prayer->id}/pray", ['prayed' => true])
            ->assertJsonPath('data.prayerCount', 1);
        $this->actingAs($this->friend)->postJson("/api/v1/prayer/requests/{$prayer->id}/pray")
            ->assertJsonPath('data.hasPrayed', false)->assertJsonPath('data.prayerCount', 0);
    }

    public function test_je_prie_previent_l_auteur_une_seule_fois(): void
    {
        $prayer = $this->postRequest();
        $notifications = fn () => AppNotification::where('recipient_id', $this->author->id)->where('type', 'prayer_request_prayed');

        // L'auteur qui prie pour sa propre requête n'est pas prévenu.
        $this->actingAs($this->author)->postJson("/api/v1/prayer/requests/{$prayer->id}/pray")->assertOk();
        $this->assertSame(0, $notifications()->count());

        $this->actingAs($this->friend)->postJson("/api/v1/prayer/requests/{$prayer->id}/pray")->assertOk();
        $notification = $notifications()->sole();
        $this->assertSame($this->friend->id, $notification->actor_id);
        $this->assertSame($prayer->id, $notification->payload['prayer_request_id']);
        $this->assertSame('Ami prie pour votre requête de prière', $notification->message);

        // Push : titre, données de navigation, préférence push_prayers.
        $push = \App\Services\PushNotifications::message($notification);
        $this->assertSame('Nouvelle prière', $push['title']);
        $this->assertSame('prayer_request_prayed', $push['data']['type']);
        $this->assertSame($prayer->id, $push['data']['prayer_request_id']);
        \App\Models\UserSetting::updateOrCreate(['user_id' => $this->author->id], ['push_prayers' => false]);
        $this->assertFalse(\App\Services\PushNotifications::allows($this->author->id, 'prayer_request_prayed'));

        // Retirer puis remettre « Je prie » : pas de seconde notification.
        $this->actingAs($this->friend)->postJson("/api/v1/prayer/requests/{$prayer->id}/pray")->assertJsonPath('data.hasPrayed', false);
        $this->actingAs($this->friend)->postJson("/api/v1/prayer/requests/{$prayer->id}/pray")->assertJsonPath('data.hasPrayed', true);
        $this->assertSame(1, $notifications()->count());

        $this->actingAs($this->author)->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('data.0.prayerRequestId', $prayer->id);
    }

    public function test_encouragement_previent_l_auteur_et_peut_etre_supprime(): void
    {
        $prayer = $this->postRequest();

        $id = $this->actingAs($this->friend)->postJson("/api/v1/prayer/requests/{$prayer->id}/messages", [
            'message' => 'Je prie avec vous, courage !', 'bible_reference' => 'Philippiens 4:6-7',
        ])->assertCreated()->json('data.id');

        $this->assertSame(1, $prayer->fresh()->message_count);
        $this->assertTrue(AppNotification::where('recipient_id', $this->author->id)->where('type', 'prayer_encouragement')->exists());
        $this->guest()->getJson("/api/v1/prayer/requests/{$prayer->id}/messages")->assertJsonPath('data.0.bibleReference', 'Philippiens 4:6-7');

        $this->actingAs($this->stranger)->deleteJson("/api/v1/prayer/requests/{$prayer->id}/messages/{$id}")->assertForbidden();
        $this->actingAs($this->author)->deleteJson("/api/v1/prayer/requests/{$prayer->id}/messages/{$id}")->assertOk();
        $this->assertSame(0, $prayer->fresh()->message_count);
    }

    public function test_exaucee_puis_remise_en_attente_par_l_auteur_seul(): void
    {
        $prayer = $this->postRequest();

        $this->actingAs($this->friend)->postJson("/api/v1/prayer/requests/{$prayer->id}/answered")->assertForbidden();
        $this->actingAs($this->author)->postJson("/api/v1/prayer/requests/{$prayer->id}/answered", ['note' => 'Sortie de l\'hôpital !'])
            ->assertOk()->assertJsonPath('data.isAnswered', true)->assertJsonPath('data.answerNote', 'Sortie de l\'hôpital !');
        $this->actingAs($this->author)->deleteJson("/api/v1/prayer/requests/{$prayer->id}/answered")
            ->assertOk()->assertJsonPath('data.status', 'open');
    }

    public function test_trois_signalements_retirent_la_requete_puis_la_moderation_la_retablit(): void
    {
        $prayer = $this->postRequest();
        $this->actingAs($this->author)->postJson("/api/v1/prayer/requests/{$prayer->id}/report", ['reason' => 'spam'])->assertStatus(422);

        foreach ([$this->friend, $this->stranger, $this->makeUser('troisieme')] as $reporter) {
            $this->actingAs($reporter)->postJson("/api/v1/prayer/requests/{$prayer->id}/report", ['reason' => 'spam'])->assertOk();
        }
        $this->assertTrue($prayer->fresh()->isHidden());
        $this->actingAs($this->stranger)->getJson("/api/v1/prayer/requests/{$prayer->id}")->assertNotFound();
        $this->actingAs($this->author)->getJson("/api/v1/prayer/requests/{$prayer->id}")->assertJsonPath('data.isHidden', true);

        $this->actingAs($this->friend)->getJson('/api/v1/prayer/moderation')->assertForbidden();
        $this->actingAs($this->moderator)->getJson('/api/v1/prayer/moderation')->assertOk()->assertJsonPath('data.0.id', $prayer->id);
        $this->actingAs($this->moderator)->postJson("/api/v1/prayer/requests/{$prayer->id}/restore")->assertOk();
        $this->assertFalse($prayer->fresh()->isHidden());
        $this->actingAs($this->moderator)->getJson('/api/v1/prayer/moderation')->assertJsonCount(0, 'data');
    }

    public function test_suppression_par_l_auteur_ou_un_administrateur(): void
    {
        $prayer = $this->postRequest();
        $this->actingAs($this->friend)->deleteJson("/api/v1/prayer/requests/{$prayer->id}")->assertForbidden();
        // Un modérateur ne supprime jamais ce qu'un autre a écrit ; un administrateur, oui.
        $this->actingAs($this->moderator)->deleteJson("/api/v1/prayer/requests/{$prayer->id}")->assertForbidden();
        $this->moderator->update(['role' => 'administrateur']);
        $this->actingAs($this->moderator->fresh())->deleteJson("/api/v1/prayer/requests/{$prayer->id}")->assertOk();
        $this->assertSoftDeleted($prayer);
    }

    public function test_requete_rattachee_a_un_evenement(): void
    {
        $event = Event::create([
            'organizer_id' => $this->moderator->id, 'title' => 'Croisade', 'type' => 'crusade', 'status' => 'published',
            'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2),
        ]);
        $prayer = $this->postRequest(['event_id' => $event->id]);

        $this->guest()->getJson("/api/v1/prayer/requests?scope=event&event_id={$event->id}")
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.event.title', 'Croisade');
        $this->get(route('events.show', $event->id))->assertOk()->assertSee('Se connecter pour prier')->assertSee('Priez pour la guérison');
        $this->actingAs($this->friend)->get(route('events.show', $event->id))->assertOk()->assertSee('Confier une requête');
    }

    public function test_requete_confiee_depuis_le_temoignage_de_quelqu_un(): void
    {
        $testimony = \App\Models\Testimony::create([
            'user_id' => $this->stranger->id, 'title' => 'Guérie du cancer', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Dieu m\'a guérie.', 'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ]);

        // Site : bouton sur le témoignage, formulaire prérempli, publication.
        $create = route('prayer.requests.create', ['temoignage' => $testimony->id]);
        $this->actingAs($this->author)->get(route('testimonies.show', $testimony->id))->assertOk()->assertSee($create, false);
        $this->actingAs($this->author)->get($create)->assertOk()->assertSee('Guérie du cancer')
            ->assertSee('name="source_testimony_id" value="' . $testimony->id . '"', false);
        $this->actingAs($this->author)->post(route('prayer.requests.store'), [
            'body' => 'Priez pour moi aussi : même diagnostic depuis mars.', 'visibility' => 'public', 'source_testimony_id' => $testimony->id,
        ])->assertRedirect();
        $prayer = PrayerRequest::where('source_testimony_id', $testimony->id)->sole();
        $this->assertNull($prayer->testimony_id); // pas le témoignage de l'exaucement
        $this->get(route('prayer.requests.show', $prayer->id))->assertOk()->assertSee('Après le témoignage « Guérie du cancer »');

        // API : champ accepté et renvoyé.
        $this->guest()->getJson("/api/v1/prayer/requests/{$prayer->id}")->assertOk()
            ->assertJsonPath('data.sourceTestimony', ['id' => $testimony->id, 'title' => 'Guérie du cancer']);

        // Témoignage inconnu ou non publié : refusé.
        $pending = \App\Models\Testimony::create([
            'user_id' => $this->stranger->id, 'title' => 'En attente', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Texte.', 'visibility' => 'public', 'status' => 'pending',
        ]);
        foreach ([(string) \Illuminate\Support\Str::uuid(), $pending->id] as $id) {
            $this->actingAs($this->friend)->postJson('/api/v1/prayer/requests', [
                'body' => 'Priez pour ma famille, merci beaucoup.', 'source_testimony_id' => $id,
            ])->assertStatus(422);
        }

        // Témoignage mis à la corbeille : le lien disparaît, la requête reste.
        $testimony->delete();
        $this->guest()->getJson("/api/v1/prayer/requests/{$prayer->id}")->assertOk()->assertJsonPath('data.sourceTestimony', null);
        $this->get(route('prayer.requests.show', $prayer->id))->assertOk()->assertDontSee('Après le témoignage');
    }

    // ─── Sessions ────────────────────────────────────────────────────────────

    private function createSession(array $extra = [], ?User $host = null): PrayerSession
    {
        $id = $this->actingAs($host ?? $this->author)->postJson('/api/v1/prayer/sessions', array_merge([
            'title' => 'Veillée pour les familles', 'starts_at' => now()->addMinutes(10)->toIso8601String(),
            'duration_minutes' => 60, 'topics' => ['Les couples', '', 'Les enfants'],
        ], $extra))->assertCreated()->assertJsonPath('data.topics', ['Les couples', 'Les enfants'])->json('data.id');

        return PrayerSession::findOrFail($id);
    }

    public function test_un_simple_utilisateur_programme_une_session_et_les_fideles_s_inscrivent(): void
    {
        $session = $this->createSession();

        $this->actingAs($this->friend)->postJson("/api/v1/prayer/sessions/{$session->id}/join")->assertOk()
            ->assertJsonPath('data.isRegistered', true)->assertJsonPath('data.participantCount', 1);
        $this->actingAs($this->friend)->postJson("/api/v1/prayer/sessions/{$session->id}/join")->assertJsonPath('data.participantCount', 1);
        $this->guest()->getJson('/api/v1/prayer/sessions')->assertOk()->assertJsonPath('data.0.phase', 'upcoming');
        $this->actingAs($this->friend)->getJson("/api/v1/prayer/sessions/{$session->id}/participants")->assertForbidden();
        $this->actingAs($this->author)->getJson("/api/v1/prayer/sessions/{$session->id}/participants")->assertJsonCount(1, 'data');
    }

    public function test_l_hote_ouvre_la_salle_dans_la_fenetre_et_les_inscrits_sont_prevenus(): void
    {
        $later = $this->createSession(['starts_at' => now()->addHours(3)->toIso8601String()]);
        $this->actingAs($this->author)->postJson("/api/v1/prayer/sessions/{$later->id}/start")->assertStatus(409);

        $session = $this->createSession();
        $this->actingAs($this->friend)->postJson("/api/v1/prayer/sessions/{$session->id}/join")->assertOk();
        $this->actingAs($this->friend)->postJson("/api/v1/prayer/sessions/{$session->id}/start")->assertForbidden();

        $res = $this->actingAs($this->author)->postJson("/api/v1/prayer/sessions/{$session->id}/start")->assertCreated();
        $liveId = $res->json('data.live.id');
        $live = LiveSession::findOrFail($liveId);
        $this->assertSame($session->id, $live->prayer_session_id);
        $this->assertFalse($live->record);
        $this->assertNotEmpty($res->json('data.video.token'));
        $this->assertSame($session->id, $res->json('data.live.prayerSession.id'));
        $this->assertTrue(AppNotification::where('recipient_id', $this->friend->id)->where('type', 'prayer_session_started')->exists());

        // Reprise : même salle, pas de seconde notification.
        $this->actingAs($this->author)->postJson("/api/v1/prayer/sessions/{$session->id}/start")->assertCreated()->assertJsonPath('data.live.id', $liveId);
        $this->assertSame(1, AppNotification::where('type', 'prayer_session_started')->count());

        // À l'antenne : la session est « en cours » et la salle visible des spectateurs.
        $this->actingAs($this->author)->postJson("/api/v1/lives/{$liveId}/go-live")->assertOk();
        $this->actingAs($this->friend)->getJson("/api/v1/prayer/sessions/{$session->id}")->assertJsonPath('data.phase', 'live')->assertJsonPath('data.live.id', $liveId);
        $this->guest()->postJson("/api/v1/lives/{$liveId}/viewer-token")->assertOk();
    }

    public function test_salle_d_une_session_reservee_aux_abonnes(): void
    {
        $session = $this->createSession(['visibility' => 'followers']);
        $liveId = $this->actingAs($this->author)->postJson("/api/v1/prayer/sessions/{$session->id}/start")->json('data.live.id');
        $this->actingAs($this->author)->postJson("/api/v1/lives/{$liveId}/go-live")->assertOk();

        $this->actingAs($this->stranger)->getJson("/api/v1/prayer/sessions/{$session->id}")->assertNotFound();
        $this->actingAs($this->stranger)->postJson("/api/v1/lives/{$liveId}/viewer-token")->assertNotFound();
        $this->actingAs($this->friend)->postJson("/api/v1/lives/{$liveId}/viewer-token")->assertOk();
    }

    public function test_les_directs_classiques_restent_reserves_a_la_moderation(): void
    {
        $session = $this->createSession();
        // prayer_session_id n'est pas accepté par POST /lives : un simple utilisateur reste refusé.
        $this->actingAs($this->author)->postJson('/api/v1/lives', ['title' => 'Mon direct', 'prayer_session_id' => $session->id])->assertForbidden();
    }

    public function test_suppression_par_un_administrateur_ferme_la_salle(): void
    {
        $session = $this->createSession();
        $liveId = $this->actingAs($this->author)->postJson("/api/v1/prayer/sessions/{$session->id}/start")->json('data.live.id');

        $this->actingAs($this->friend)->deleteJson("/api/v1/prayer/sessions/{$session->id}")->assertForbidden();
        // Un modérateur ne supprime jamais ce qu'un autre a écrit ; un administrateur, oui.
        $this->actingAs($this->moderator)->deleteJson("/api/v1/prayer/sessions/{$session->id}")->assertForbidden();
        $this->moderator->update(['role' => 'administrateur']);
        $this->actingAs($this->moderator->fresh())->deleteJson("/api/v1/prayer/sessions/{$session->id}")->assertOk();
        $this->assertSame('ended', LiveSession::find($liveId)->status->value);
    }

    public function test_rappel_quinze_minutes_avant(): void
    {
        $session = $this->createSession();
        $this->actingAs($this->friend)->postJson("/api/v1/prayer/sessions/{$session->id}/join")->assertOk();

        $this->artisan('prayer-sessions:remind')->assertSuccessful();
        $this->artisan('prayer-sessions:remind')->assertSuccessful();

        $this->assertSame(1, AppNotification::where('recipient_id', $this->friend->id)->where('type', 'prayer_session_reminder')->count());
        $this->assertSame(1, AppNotification::where('recipient_id', $this->author->id)->where('type', 'prayer_session_reminder')->count());
    }

    // ─── Site ────────────────────────────────────────────────────────────────

    public function test_pages_web(): void
    {
        $prayer  = $this->postRequest(['is_anonymous' => true]);
        $session = $this->createSession();

        $this->guest()->get(route('prayer.requests.index'))->assertOk()->assertSee('Anonyme')->assertDontSee('Auteur</a>', false);
        $this->get(route('prayer.requests.show', $prayer->id))->assertOk();
        $this->get(route('prayer.sessions.index'))->assertOk()->assertSee('Veillée pour les familles');
        $this->get(route('prayer.sessions.show', $session->id))->assertOk();

        $this->actingAs($this->friend)->get(route('prayer.requests.create'))->assertOk();
        $this->actingAs($this->friend)->post(route('prayer.requests.store'), [
            'body' => 'Priez pour mon examen de vendredi, merci.', 'visibility' => 'public',
        ])->assertRedirect();
        $this->actingAs($this->friend)->post(route('prayer.requests.pray', $prayer->id))->assertRedirect();
        $this->assertSame(1, $prayer->fresh()->prayer_count);
        $this->actingAs($this->friend)->post(route('prayer.sessions.join', $session->id))->assertRedirect();
        $this->actingAs($this->friend)->get(route('prayer.sessions.create'))->assertOk();
        $this->actingAs($this->friend)->post(route('prayer.sessions.store'), [
            'title' => 'Prière du matin', 'date' => now()->addDay()->toDateString(), 'time' => '06:30',
            'duration_minutes' => 30, 'visibility' => 'public', 'topics_text' => "Le pays\nLes malades",
        ])->assertRedirect();
        $this->assertSame(['Le pays', 'Les malades'], PrayerSession::where('title', 'Prière du matin')->firstOrFail()->topics);

        $this->actingAs($this->author)->get(route('prayer.sessions.show', $session->id))->assertOk()->assertSee('Ouvrir la salle');
        $this->actingAs($this->author)->post(route('prayer.sessions.open', $session->id))->assertRedirectContains('/studio');

        $this->actingAs($this->friend)->get(route('prayer.moderation.index'))->assertForbidden();
        $this->actingAs($this->moderator)->get(route('prayer.moderation.index'))->assertOk();
    }

    public function test_l_hote_modifie_une_session_commencee_sans_changer_l_horaire(): void
    {
        $session = $this->createSession();
        $startsAt = $session->starts_at->toIso8601String();
        $this->travel(20)->minutes();
        $url = "/api/v1/prayer/sessions/{$session->id}";

        // L'application renvoie tout le formulaire, horaire passé compris : accepté s'il est inchangé.
        $this->actingAs($this->author)->putJson($url, ['title' => 'Veillée prolongée', 'starts_at' => $startsAt, 'duration_minutes' => 90])
            ->assertOk()->assertJsonPath('data.title', 'Veillée prolongée');
        // Un nouvel horaire doit rester dans le futur.
        $this->actingAs($this->author)->putJson($url, ['starts_at' => now()->subMinutes(5)->toIso8601String()])->assertUnprocessable();
        $this->actingAs($this->author)->putJson($url, ['starts_at' => now()->addDay()->toIso8601String()])->assertOk();
        // À la création, l'horaire passé reste refusé.
        $this->actingAs($this->author)->postJson('/api/v1/prayer/sessions', [
            'title' => 'Trop tard', 'starts_at' => now()->subMinute()->toIso8601String(),
        ])->assertUnprocessable();
    }
}
