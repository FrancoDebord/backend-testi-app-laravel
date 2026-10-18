<?php

namespace Tests\Feature;

use App\Models\Follow;
use App\Models\PrayerRequest;
use App\Models\Reaction;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Temps de prière : « Je prie » n'ajoute une prière qu'après 5 min comptées par le serveur depuis …/pray/start
 * (API et site). Voir docs/fonctionnalites/temps-de-priere.md
 */
class PrayerFocusTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private User $reader;
    private PrayerRequest $prayer;
    private Testimony $testimony;

    protected function setUp(): void
    {
        parent::setUp();
        // Désactivé par défaut dans Tests\TestCase : activé ici.
        config(['prayer.focus_seconds' => 300]);
        Carbon::setTestNow('2026-10-18 10:00:00');

        $this->author = $this->makeUser('auteur');
        $this->reader = $this->makeUser('lecteur');
        $this->prayer = PrayerRequest::create([
            'user_id' => $this->author->id, 'body' => 'Priez pour la guérison de ma mère, hospitalisée.',
            'visibility' => PrayerRequest::PUBLIC,
        ]);
        $this->testimony = Testimony::create([
            'user_id' => $this->author->id, 'title' => 'Guéri du paludisme', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Texte du témoignage', 'visibility' => 'public', 'status' => 'approved',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => ucfirst($name), 'email' => "{$name}@example.com",
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    private function wait(int $seconds): void
    {
        Carbon::setTestNow(Carbon::now()->addSeconds($seconds));
    }

    private function prayUrl(): string
    {
        return "/api/v1/prayer/requests/{$this->prayer->id}/pray";
    }

    private function reactUrl(): string
    {
        return "/api/v1/testimonies/{$this->testimony->id}/reactions";
    }

    // ─── Commencer ───────────────────────────────────────────────────────────

    public function test_api_start_endpoints_return_the_timing(): void
    {
        $this->actingAs($this->reader)->postJson("{$this->prayUrl()}/start")->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.requiredSeconds', 300)
            ->assertJsonPath('data.startedAt', Carbon::now()->toIso8601String())
            ->assertJsonPath('data.confirmableAt', Carbon::now()->addSeconds(300)->toIso8601String());

        $this->actingAs($this->reader)->postJson("/api/v1/testimonies/{$this->testimony->id}/pray/start")->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.requiredSeconds', 300)
            ->assertJsonStructure(['success', 'data' => ['startedAt', 'requiredSeconds', 'confirmableAt']]);
    }

    public function test_web_start_routes_return_the_same_json(): void
    {
        $this->actingAs($this->reader)->postJson(route('prayer.requests.pray.start', $this->prayer->id))->assertOk()
            ->assertJsonPath('success', true)->assertJsonPath('data.requiredSeconds', 300)
            ->assertJsonStructure(['data' => ['startedAt', 'requiredSeconds', 'confirmableAt']]);

        $this->actingAs($this->reader)->postJson(route('testimonies.prayer.start', $this->testimony->id))->assertOk()
            ->assertJsonPath('success', true)->assertJsonPath('data.requiredSeconds', 300)
            ->assertJsonStructure(['data' => ['startedAt', 'requiredSeconds', 'confirmableAt']]);
    }

    public function test_start_requires_authentication(): void
    {
        $this->postJson("{$this->prayUrl()}/start")->assertUnauthorized();
        $this->postJson("/api/v1/testimonies/{$this->testimony->id}/pray/start")->assertUnauthorized();
    }

    public function test_cannot_start_on_an_invisible_or_hidden_request(): void
    {
        $private = PrayerRequest::create(['user_id' => $this->author->id, 'body' => "Requête privée de l'auteur.", 'visibility' => PrayerRequest::PRIVATE]);
        $this->actingAs($this->reader)->postJson("/api/v1/prayer/requests/{$private->id}/pray/start")->assertNotFound();
        $this->actingAs($this->reader)->postJson(route('prayer.requests.pray.start', $private->id))->assertNotFound();

        $followers = PrayerRequest::create(['user_id' => $this->author->id, 'body' => 'Requête pour les abonnés.', 'visibility' => PrayerRequest::FOLLOWERS]);
        $this->actingAs($this->reader)->postJson("/api/v1/prayer/requests/{$followers->id}/pray/start")->assertNotFound();
        Follow::create(['follower_id' => $this->reader->id, 'following_id' => $this->author->id]);
        $this->actingAs($this->reader)->postJson("/api/v1/prayer/requests/{$followers->id}/pray/start")->assertOk();

        $this->prayer->forceFill(['hidden_at' => now()])->save();
        $this->actingAs($this->reader)->postJson("{$this->prayUrl()}/start")->assertNotFound();
        // L'auteur la voit encore, mais elle est retirée : pas de temps de prière.
        $this->actingAs($this->author)->postJson("{$this->prayUrl()}/start")->assertStatus(409);
    }

    public function test_cannot_start_on_an_invisible_testimony(): void
    {
        $pending = Testimony::create([
            'user_id' => $this->author->id, 'title' => 'En attente', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'pending',
        ]);
        $this->actingAs($this->reader)->postJson("/api/v1/testimonies/{$pending->id}/pray/start")->assertNotFound();
        $this->actingAs($this->reader)->postJson(route('testimonies.prayer.start', $pending->id))->assertNotFound();
        $this->actingAs($this->reader)->postJson('/api/v1/testimonies/00000000-0000-0000-0000-000000000000/pray/start')->assertNotFound();
    }

    // ─── Requêtes de prière ──────────────────────────────────────────────────

    public function test_praying_without_start_is_refused_with_422(): void
    {
        $this->actingAs($this->reader)->postJson($this->prayUrl())->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('remaining_seconds', 300)
            ->assertJsonStructure(['message', 'errors' => ['prayer_focus']]);

        $this->assertSame(0, (int) $this->prayer->fresh()->prayer_count);
    }

    public function test_praying_too_early_is_refused_with_the_remaining_time(): void
    {
        $this->actingAs($this->reader)->postJson("{$this->prayUrl()}/start")->assertOk();
        $this->wait(100);

        $response = $this->actingAs($this->reader)->postJson($this->prayUrl())->assertStatus(422)
            ->assertJsonPath('remaining_seconds', 200);
        $this->assertStringContainsString('3 min 20 s', $response->json('errors.prayer_focus.0'));
        $this->assertSame(0, (int) $this->prayer->fresh()->prayer_count);
    }

    public function test_praying_after_five_minutes_succeeds_and_consumes_the_start(): void
    {
        $this->actingAs($this->reader)->postJson("{$this->prayUrl()}/start")->assertOk();
        $this->wait(300);

        $this->actingAs($this->reader)->postJson($this->prayUrl())->assertOk()->assertJsonPath('data.hasPrayed', true);
        $this->assertSame(1, (int) $this->prayer->fresh()->prayer_count);

        // Retirer sa prière n'est jamais bloqué…
        $this->actingAs($this->reader)->postJson($this->prayUrl())->assertOk()->assertJsonPath('data.hasPrayed', false);
        $this->assertSame(0, (int) $this->prayer->fresh()->prayer_count);

        // … mais prier de nouveau demande un nouveau temps de prière (le début a été consommé).
        $this->actingAs($this->reader)->postJson($this->prayUrl())->assertStatus(422);
    }

    public function test_tolerance_of_ten_seconds_and_restart(): void
    {
        $this->actingAs($this->reader)->postJson("{$this->prayUrl()}/start")->assertOk();
        $this->wait(250);
        // Recommencer remet le décompte à zéro.
        $this->actingAs($this->reader)->postJson("{$this->prayUrl()}/start")->assertOk();
        $this->wait(289);
        $this->actingAs($this->reader)->postJson($this->prayUrl())->assertStatus(422);
        $this->wait(1); // 290 s : dans la marge de 10 s
        $this->actingAs($this->reader)->postJson($this->prayUrl())->assertOk()->assertJsonPath('data.hasPrayed', true);
    }

    public function test_removing_or_repeating_a_prayer_is_never_blocked(): void
    {
        config(['prayer.focus_seconds' => 0]);
        $this->actingAs($this->reader)->postJson($this->prayUrl())->assertOk();
        config(['prayer.focus_seconds' => 300]);

        // Déjà en prière : « prayed: true » ne bloque pas et ne compte pas deux fois.
        $this->actingAs($this->reader)->postJson($this->prayUrl(), ['prayed' => true])->assertOk();
        $this->assertSame(1, (int) $this->prayer->fresh()->prayer_count);

        $this->actingAs($this->reader)->postJson($this->prayUrl(), ['prayed' => false])->assertOk();
        $this->assertSame(0, (int) $this->prayer->fresh()->prayer_count);
    }

    public function test_focus_seconds_zero_disables_the_rule(): void
    {
        config(['prayer.focus_seconds' => 0]);
        $this->actingAs($this->reader)->postJson($this->prayUrl())->assertOk()->assertJsonPath('data.hasPrayed', true);
        $this->actingAs($this->reader)->postJson($this->reactUrl(), ['type' => 'pray'])->assertCreated();
    }

    public function test_web_pray_form_is_enforced_too(): void
    {
        $show = route('prayer.requests.show', $this->prayer->id);
        $this->actingAs($this->reader)->from($show)->post(route('prayer.requests.pray', $this->prayer->id))
            ->assertRedirect($show)->assertSessionHas('error');
        $this->assertSame(0, (int) $this->prayer->fresh()->prayer_count);

        $this->actingAs($this->reader)->postJson(route('prayer.requests.pray.start', $this->prayer->id))->assertOk();
        $this->wait(300);
        $this->actingAs($this->reader)->from($show)->post(route('prayer.requests.pray', $this->prayer->id))->assertSessionHas('success');
        $this->assertSame(1, (int) $this->prayer->fresh()->prayer_count);

        // Retirer sa prière sur le site : immédiat.
        $this->actingAs($this->reader)->from($show)->post(route('prayer.requests.pray', $this->prayer->id))->assertSessionHas('success');
        $this->assertSame(0, (int) $this->prayer->fresh()->prayer_count);
    }

    // ─── Témoignages ─────────────────────────────────────────────────────────

    public function test_testimony_prayer_reaction_needs_a_completed_prayer_time(): void
    {
        foreach (['pray', 'amen', 'worship'] as $type) {
            $this->actingAs($this->reader)->postJson($this->reactUrl(), ['type' => $type])->assertStatus(422)
                ->assertJsonPath('success', false)->assertJsonPath('remaining_seconds', 300);
        }
        // « J'aime » n'est pas concerné.
        $this->actingAs($this->reader)->postJson($this->reactUrl(), ['type' => 'like'])->assertCreated();

        $this->actingAs($this->reader)->postJson("/api/v1/testimonies/{$this->testimony->id}/pray/start")->assertOk();
        $this->wait(60);
        $this->actingAs($this->reader)->postJson($this->reactUrl(), ['type' => 'pray'])->assertStatus(422)
            ->assertJsonPath('remaining_seconds', 240);
        $this->wait(240);
        $this->actingAs($this->reader)->postJson($this->reactUrl(), ['type' => 'pray'])->assertCreated()
            ->assertJsonPath('data.isPrayedByMe', true)->assertJsonPath('data.prayersCount', 1);

        // Passer de pray à amen (même groupe) n'est pas bloqué et ne compte pas deux fois.
        $this->actingAs($this->reader)->postJson($this->reactUrl(), ['type' => 'amen'])->assertOk()
            ->assertJsonPath('data.myPrayerReaction', 'amen')->assertJsonPath('data.prayersCount', 1);
        // Prier de nouveau (déjà en prière) : pas bloqué.
        $this->actingAs($this->reader)->postJson($this->reactUrl(), ['type' => 'amen'])->assertOk();

        // Retirer n'est jamais bloqué ; ensuite, le début consommé ne permet pas de prier de nouveau.
        $this->actingAs($this->reader)->deleteJson("{$this->reactUrl()}?group=prayer")->assertOk()->assertJsonPath('data.prayersCount', 0);
        $this->actingAs($this->reader)->postJson($this->reactUrl(), ['type' => 'pray'])->assertStatus(422);
    }

    public function test_web_testimony_i_pray_is_enforced_only_when_adding(): void
    {
        $url = route('testimonies.reactions.store', $this->testimony->id);
        $headers = ['X-Requested-With' => 'XMLHttpRequest'];

        $this->actingAs($this->reader)->postJson($url, ['type' => 'pray'], $headers)->assertStatus(422)
            ->assertJsonStructure(['errors' => ['prayer_focus']]);

        $this->actingAs($this->reader)->postJson(route('testimonies.prayer.start', $this->testimony->id))->assertOk();
        $this->wait(300);
        $this->actingAs($this->reader)->postJson($url, ['type' => 'pray'], $headers)->assertOk()
            ->assertJson(['reacted' => true, 'is_prayed' => true, 'prayer_count' => 1]);

        // Déjà en prière : le bouton retire la prière immédiatement.
        $this->actingAs($this->reader)->postJson($url, ['type' => 'pray'], $headers)->assertOk()
            ->assertJson(['reacted' => false, 'is_prayed' => false, 'prayer_count' => 0]);
        $this->assertSame(0, Reaction::where('user_id', $this->reader->id)->count());
    }

    public function test_messages_follow_the_language(): void
    {
        $this->actingAs($this->reader)->postJson($this->prayUrl(), [], ['Accept-Language' => 'en'])->assertStatus(422)
            ->assertJsonPath('message', __('prayer_focus.required', [], 'en'));
        $this->actingAs($this->reader)->postJson($this->prayUrl())->assertStatus(422)
            ->assertJsonPath('message', __('prayer_focus.required', [], 'fr'));
    }
}
