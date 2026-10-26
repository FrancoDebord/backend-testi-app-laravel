<?php

namespace Tests\Feature;

use App\Models\ContentReaction;
use App\Models\DailyVerse;
use App\Models\DailyVerseReaction;
use App\Models\Event;
use App\Models\PrayerSession;
use App\Models\Prophecy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Temps de prière étendu (2026-10-19) : verset du jour (« Je prie »), paroles prophétiques (« J'ai prié »),
 * événements et sessions de prière (réactions du groupe « je prie »). Même règle que les requêtes de prière
 * et les témoignages (tests\Feature\PrayerFocusTest). Voir docs/fonctionnalites/temps-de-priere.md
 */
class PrayerFocusOtherKindsTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private User $reader;

    protected function setUp(): void
    {
        parent::setUp();
        config(['prayer.focus_seconds' => 300]);
        Carbon::setTestNow('2026-10-19 10:00:00');

        $this->author = $this->makeUser('auteur');
        $this->reader = $this->makeUser('lecteur');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(string $name): User
    {
        return User::create([
            'display_name' => ucfirst($name), 'email' => "{$name}@example.com",
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ]);
    }

    private function wait(int $seconds): void
    {
        Carbon::setTestNow(Carbon::now()->addSeconds($seconds));
    }

    private function verse(): DailyVerse
    {
        return DailyVerse::create([
            'verse_text' => 'Car Dieu a tant aimé le monde', 'reference' => 'Jean 3:16',
            'scheduled_date' => now()->toDateString(), 'is_active' => true,
        ]);
    }

    private function prophecy(): Prophecy
    {
        return $this->reader->prophecies()->create(['received_on' => '2026-09-01', 'body_text' => "Je t'ouvrirai des portes."])->refresh();
    }

    private function event(): Event
    {
        return Event::create([
            'organizer_id' => $this->author->id, 'title' => 'Grande croisade', 'type' => 'crusade',
            'status' => 'published', 'starts_at' => now()->addDays(10), 'ends_at' => now()->addDays(12),
        ]);
    }

    private function prayerSession(): PrayerSession
    {
        return PrayerSession::create([
            'host_id' => $this->author->id, 'title' => 'Veillée', 'starts_at' => now()->addDay(),
            'duration_minutes' => 60, 'ends_at' => now()->addDay()->addHour(), 'visibility' => 'public',
        ]);
    }

    private function assertStartJson($response): void
    {
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.requiredSeconds', 300)
            ->assertJsonPath('data.startedAt', Carbon::now()->toIso8601String())
            ->assertJsonPath('data.confirmableAt', Carbon::now()->addSeconds(300)->toIso8601String());
    }

    // ─── Verset du jour ──────────────────────────────────────────────────────

    public function test_daily_verse_i_pray_needs_a_completed_prayer_time(): void
    {
        $verse = $this->verse();
        $prayers = fn () => DailyVerseReaction::where('daily_verse_id', $verse->id)->where('type', 'pray')->count();

        $this->postJson("/api/v1/daily-verse/{$verse->id}/pray/start")->assertUnauthorized();
        $this->actingAs($this->reader)->postJson('/api/v1/daily-verse/999999/pray/start')->assertNotFound();

        // Sans début : 422, toutes les routes qui ajoutent « Je prie ».
        $this->actingAs($this->reader)->postJson('/api/v1/daily-verse/react', ['type' => 'pray'])->assertStatus(422)
            ->assertJsonPath('success', false)->assertJsonPath('remaining_seconds', 300)
            ->assertJsonStructure(['message', 'errors' => ['prayer_focus']]);
        $this->actingAs($this->reader)->postJson("/api/v1/daily-verse/{$verse->id}/react", ['type' => 'pray'])->assertStatus(422);
        $this->actingAs($this->reader)->postJson("/api/v1/daily-verse/{$verse->id}/react", ['type' => 'pray', 'active' => true])->assertStatus(422);
        // « J'aime » et « Amen » ne sont pas concernés.
        $this->actingAs($this->reader)->postJson("/api/v1/daily-verse/{$verse->id}/react", ['type' => 'like', 'active' => true])->assertOk();
        $this->actingAs($this->reader)->postJson("/api/v1/daily-verse/{$verse->id}/react", ['type' => 'amen', 'active' => true])->assertOk();
        $this->assertSame(0, $prayers());

        $this->assertStartJson($this->actingAs($this->reader)->postJson("/api/v1/daily-verse/{$verse->id}/pray/start"));
        $this->wait(100);
        $this->actingAs($this->reader)->postJson("/api/v1/daily-verse/{$verse->id}/react", ['type' => 'pray'])->assertStatus(422)
            ->assertJsonPath('remaining_seconds', 200);
        $this->wait(200);
        $this->actingAs($this->reader)->postJson("/api/v1/daily-verse/{$verse->id}/react", ['type' => 'pray'])->assertOk();
        $this->assertSame(1, $prayers());
        $this->assertSame(1, (int) $verse->fresh()->prayer_count);

        // Déjà en prière : « active: true » n'est pas bloqué ; retirer jamais bloqué.
        $this->actingAs($this->reader)->postJson("/api/v1/daily-verse/{$verse->id}/react", ['type' => 'pray', 'active' => true])->assertOk();
        $this->actingAs($this->reader)->deleteJson('/api/v1/daily-verse/react', ['type' => 'pray'])->assertOk();
        $this->assertSame(0, $prayers());

        // Début consommé : prier de nouveau demande un nouveau temps de prière.
        $this->actingAs($this->reader)->postJson('/api/v1/daily-verse/react', ['type' => 'pray'])->assertStatus(422);
    }

    public function test_daily_verse_web_route_is_enforced(): void
    {
        $verse = $this->verse();
        $page = route('daily-verse.show');

        $this->actingAs($this->reader)->from($page)->post(route('daily-verse.react', $verse->id), ['type' => 'pray'])
            ->assertRedirect($page)->assertSessionHas('error');
        $this->assertSame(0, (int) $verse->fresh()->prayer_count);

        $this->assertStartJson($this->actingAs($this->reader)->postJson(route('daily-verse.pray.start', $verse->id)));
        $this->wait(300);
        $this->actingAs($this->reader)->from($page)->post(route('daily-verse.react', $verse->id), ['type' => 'pray'])->assertSessionHas('success');
        $this->assertSame(1, (int) $verse->fresh()->prayer_count);

        // Prier encore (nouveau temps de prière), puis retirer la dernière prière : docs/fonctionnalites/prier-plusieurs-fois.md
        $this->assertStartJson($this->actingAs($this->reader)->postJson(route('daily-verse.pray.start', $verse->id)));
        $this->wait(300);
        $this->actingAs($this->reader)->from($page)->post(route('daily-verse.react', $verse->id), ['type' => 'pray'])->assertSessionHas('success');
        $this->assertSame(1, (int) $verse->fresh()->prayer_count);
        $this->assertSame(2, app(\App\Services\DailyVerses::class)->prayerTimes($verse, $this->reader));
        $this->actingAs($this->reader)->from($page)->post(route('daily-verse.react', $verse->id), ['type' => 'pray', 'last' => 1])->assertSessionHas('success');
        $this->assertSame(1, app(\App\Services\DailyVerses::class)->prayerTimes($verse, $this->reader));
        $this->actingAs($this->reader)->from($page)->post(route('daily-verse.react', $verse->id), ['type' => 'pray', 'last' => 1])->assertSessionHas('success');
        $this->assertSame(0, (int) $verse->fresh()->prayer_count);
    }

    // ─── Paroles prophétiques ────────────────────────────────────────────────

    public function test_prophecy_i_prayed_needs_a_completed_prayer_time(): void
    {
        $prophecy = $this->prophecy();
        $url = "/api/v1/prophecies/{$prophecy->id}/prayers";

        // Parole d'une autre personne : 404.
        $this->actingAs($this->author)->postJson("/api/v1/prophecies/{$prophecy->id}/pray/start")->assertNotFound();

        $this->actingAs($this->reader)->postJson($url, ['note' => 'Merci Seigneur'])->assertStatus(422)
            ->assertJsonPath('success', false)->assertJsonPath('remaining_seconds', 300);

        $this->assertStartJson($this->actingAs($this->reader)->postJson("/api/v1/prophecies/{$prophecy->id}/pray/start"));
        $this->wait(60);
        $this->actingAs($this->reader)->postJson($url)->assertStatus(422)->assertJsonPath('remaining_seconds', 240);
        $this->wait(240);
        $prayerId = $this->actingAs($this->reader)->postJson($url, ['note' => 'Merci Seigneur'])->assertCreated()
            ->assertJsonPath('data.prayer.note', 'Merci Seigneur')->json('data.prayer.id');
        $this->assertSame(1, (int) $prophecy->fresh()->prayer_count);

        // Une prière par début ; retirer une prière n'est jamais bloqué.
        $this->actingAs($this->reader)->postJson($url)->assertStatus(422);
        $this->actingAs($this->reader)->deleteJson("{$url}/{$prayerId}")->assertOk();
        $this->assertSame(0, (int) $prophecy->fresh()->prayer_count);
    }

    public function test_prophecy_web_form_is_enforced(): void
    {
        $prophecy = $this->prophecy();
        $page = route('prophecies.show', $prophecy->id);

        $this->actingAs($this->reader)->from($page)->post(route('prophecies.pray', $prophecy->id), ['note' => 'Note'])
            ->assertRedirect($page)->assertSessionHas('error');
        $this->assertSame(0, (int) $prophecy->fresh()->prayer_count);

        $this->assertStartJson($this->actingAs($this->reader)->postJson(route('prophecies.pray.start', $prophecy->id)));
        $this->wait(300);
        $this->actingAs($this->reader)->from($page)->post(route('prophecies.pray', $prophecy->id), ['note' => 'Note'])->assertSessionHas('success');
        $this->assertSame(1, (int) $prophecy->fresh()->prayer_count);
    }

    // ─── Événements et sessions de prière (réactions) ────────────────────────

    public function test_event_and_session_prayer_reactions_need_a_completed_prayer_time(): void
    {
        $subjects = ['events' => $this->event(), 'prayer/sessions' => $this->prayerSession()];
        foreach ($subjects as $prefix => $subject) {
            $this->postJson("/api/v1/{$prefix}/{$subject->id}/pray/start")->assertUnauthorized();
        }

        foreach ($subjects as $prefix => $subject) {
            $url = "/api/v1/{$prefix}/{$subject->id}/reactions";
            $start = "/api/v1/{$prefix}/{$subject->id}/pray/start";

            $this->actingAs($this->reader)->postJson("/api/v1/{$prefix}/00000000-0000-0000-0000-000000000000/pray/start")->assertNotFound();

            foreach (['pray', 'amen', 'worship'] as $type) {
                $this->actingAs($this->reader)->postJson($url, ['type' => $type])->assertStatus(422)
                    ->assertJsonPath('success', false)->assertJsonPath('remaining_seconds', 300)
                    ->assertJsonStructure(['message', 'errors' => ['prayer_focus']]);
            }
            // « J'aime » n'est pas concerné.
            $this->actingAs($this->reader)->postJson($url, ['type' => 'like'])->assertCreated();

            $this->assertStartJson($this->actingAs($this->reader)->postJson($start));
            $this->wait(100);
            $this->actingAs($this->reader)->postJson($url, ['type' => 'pray'])->assertStatus(422)->assertJsonPath('remaining_seconds', 200);
            $this->wait(200);
            $this->actingAs($this->reader)->postJson($url, ['type' => 'pray'])->assertCreated()
                ->assertJsonPath('data.reactions.prayersCount', 1)->assertJsonPath('data.reactions.myPrayerReaction', 'pray');

            // Passer à amen (même groupe) : pas bloqué, compté une fois.
            $this->actingAs($this->reader)->postJson($url, ['type' => 'amen'])->assertOk()->assertJsonPath('data.reactions.prayersCount', 1);
            // Retirer : jamais bloqué ; ensuite, nouveau temps de prière exigé.
            $this->actingAs($this->reader)->deleteJson("{$url}?group=prayer")->assertOk()->assertJsonPath('data.reactions.prayersCount', 0);
            $this->actingAs($this->reader)->postJson($url, ['type' => 'pray'])->assertStatus(422);

            $this->assertSame(1, ContentReaction::where('user_id', $this->reader->id)->where('subject_id', $subject->id)->count(), $prefix);
        }
    }

    public function test_focus_seconds_zero_disables_the_rule_for_every_kind(): void
    {
        config(['prayer.focus_seconds' => 0]);
        $verse = $this->verse();
        $prophecy = $this->prophecy();
        $event = $this->event();

        $this->actingAs($this->reader)->postJson("/api/v1/daily-verse/{$verse->id}/react", ['type' => 'pray'])->assertOk();
        $this->actingAs($this->reader)->postJson("/api/v1/prophecies/{$prophecy->id}/prayers")->assertCreated();
        $this->actingAs($this->reader)->postJson("/api/v1/events/{$event->id}/reactions", ['type' => 'pray'])->assertCreated();
        $this->actingAs($this->reader)->postJson("/api/v1/events/{$event->id}/pray/start")->assertOk()->assertJsonPath('data.requiredSeconds', 0);
    }
}
