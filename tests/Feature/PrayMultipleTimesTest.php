<?php

namespace Tests\Feature;

use App\Models\DailyVerse;
use App\Models\Event;
use App\Models\PrayerRequest;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Prier plusieurs fois : `again` ajoute une prière, `last` retire la dernière ; le compteur de la publication reste
 * le nombre de personnes ; `myPrayerTimes` et le popup « Qui a réagi ? » donnent le nombre de fois.
 * docs/fonctionnalites/prier-plusieurs-fois.md (temps de prière désactivé ici : voir PrayerFocusTest).
 */
class PrayMultipleTimesTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private User $me;

    protected function setUp(): void
    {
        parent::setUp();
        config(['prayer.focus_seconds' => 0]);
        $this->author = $this->user('Auteur');
        $this->me = $this->user('Ama');
    }

    private function user(string $name): User
    {
        return User::create([
            'display_name' => $name, 'email' => str()->slug($name) . '@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active',
        ]);
    }

    public function test_evenement_prier_encore_puis_retirer_la_derniere(): void
    {
        $event = Event::create([
            'organizer_id' => $this->author->id, 'title' => 'Grande croisade', 'type' => 'crusade',
            'status' => 'published', 'starts_at' => now()->addDays(10), 'ends_at' => now()->addDays(12),
        ]);
        $url = "/api/v1/events/{$event->id}/reactions";
        Sanctum::actingAs($this->me);

        $this->postJson($url, ['type' => 'pray'])->assertCreated()->assertJsonPath('data.reactions.myPrayerTimes', 1);
        // Sans again : idempotent, comme avant.
        $this->postJson($url, ['type' => 'pray'])->assertOk()->assertJsonPath('data.reactions.myPrayerTimes', 1);
        $this->postJson($url, ['type' => 'pray', 'again' => true])->assertOk()
            ->assertJsonPath('data.reactions.myPrayerTimes', 2)->assertJsonPath('data.reactions.prayersCount', 1);
        $this->deleteJson("{$url}?group=prayer&last=1")->assertOk()
            ->assertJsonPath('data.reactions.myPrayerTimes', 1)->assertJsonPath('data.reactions.prayersCount', 1);
        $this->deleteJson("{$url}?group=prayer&last=1")->assertOk()
            ->assertJsonPath('data.reactions.myPrayerTimes', 0)->assertJsonPath('data.reactions.prayersCount', 0)
            ->assertJsonPath('data.reactions.myPrayerReaction', null);
    }

    public function test_requete_de_priere(): void
    {
        $prayer = PrayerRequest::create(['user_id' => $this->author->id, 'body' => 'Pour ma famille', 'visibility' => 'public']);
        $url = "/api/v1/prayer/requests/{$prayer->id}/pray";
        Sanctum::actingAs($this->me);

        $this->postJson($url, ['prayed' => true])->assertOk()->assertJsonPath('data.hasPrayed', true)->assertJsonPath('data.myPrayerTimes', 1);
        $this->postJson($url, ['prayed' => true])->assertOk()->assertJsonPath('data.myPrayerTimes', 1);
        $this->postJson($url, ['again' => true])->assertOk()->assertJsonPath('data.myPrayerTimes', 2)->assertJsonPath('data.prayerCount', 1);
        $this->postJson($url, ['again' => true])->assertOk()->assertJsonPath('data.myPrayerTimes', 3);
        $this->getJson("/api/v1/prayer/requests/{$prayer->id}")->assertJsonPath('data.myPrayerTimes', 3);
        $this->getJson('/api/v1/prayer/requests')->assertJsonPath('data.0.myPrayerTimes', 3);

        $this->postJson($url, ['prayed' => false, 'last' => true])->assertOk()->assertJsonPath('data.myPrayerTimes', 2)->assertJsonPath('data.hasPrayed', true);
        // Sans last : la prière est retirée entièrement (anciennes versions de l'application).
        $this->postJson($url, ['prayed' => false])->assertOk()->assertJsonPath('data.hasPrayed', false)
            ->assertJsonPath('data.myPrayerTimes', 0)->assertJsonPath('data.prayerCount', 0);
    }

    public function test_verset_du_jour(): void
    {
        $verse = DailyVerse::create(['verse_text' => 'Car Dieu a tant aimé le monde', 'reference' => 'Jean 3:16', 'scheduled_date' => now()->toDateString(), 'is_active' => true]);
        $url = "/api/v1/daily-verse/{$verse->id}/react";
        Sanctum::actingAs($this->me);

        $this->postJson($url, ['type' => 'pray', 'active' => true])->assertOk()->assertJsonPath('data.myPrayerTimes', 1);
        $this->postJson($url, ['type' => 'pray', 'again' => true])->assertOk()->assertJsonPath('data.myPrayerTimes', 2)->assertJsonPath('data.counts.prayers', 1);
        $this->postJson($url, ['type' => 'pray', 'active' => false, 'last' => true])->assertOk()->assertJsonPath('data.myPrayerTimes', 1);
        // « J'aime » n'est pas concerné par again.
        $this->postJson($url, ['type' => 'like', 'again' => true])->assertOk()->assertJsonPath('data.counts.likes', 1);
    }

    public function test_le_popup_montre_le_nombre_de_fois(): void
    {
        $testimony = Testimony::create([
            'user_id' => $this->author->id, 'title' => 'Guérison', 'type' => 'text', 'category_slug' => 'autre',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ]);
        Sanctum::actingAs($this->me);
        foreach ([false, true, true] as $again) {
            $this->postJson("/api/v1/testimonies/{$testimony->id}/reactions", ['type' => 'pray', 'again' => $again]);
        }
        $this->app['auth']->forgetGuards();

        $this->getJson("/api/v1/testimonies/{$testimony->id}/reactions/people")
            ->assertJsonPath('data.summary.prayersCount', 1)
            ->assertJsonPath('data.people.0.prayerTimes', 3);
        $this->get("/reactions/testimony/{$testimony->id}")->assertOk()->assertSee('3 fois');
    }
}
