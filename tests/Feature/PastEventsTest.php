<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventImage;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Événements passés : GET /events?when=past (terminés, plus récent d'abord, testimonies_count, vignette),
 * GET /events/{id}/testimonies (approuvés), pages du site. Voir docs/fonctionnalites/evenements.md
 */
class PastEventsTest extends TestCase
{
    use RefreshDatabase;

    private User $org;
    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = User::create([
            'display_name' => 'Église', 'email' => 'org@example.com', 'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
            'account_type' => 'organization', 'organization_name' => 'Église de la Grâce', 'organization_type' => 'church', 'verification_status' => 'verified',
        ]);
        $this->author = User::create(['display_name' => 'Marie', 'email' => 'marie@example.com', 'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active']);
    }

    private function event(string $title, int $endedDaysAgo, array $attrs = []): Event
    {
        return Event::create(array_merge([
            'organizer_id' => $this->org->id, 'title' => $title, 'type' => 'crusade', 'status' => 'published',
            'starts_at' => now()->subDays($endedDaysAgo + 1), 'ends_at' => now()->subDays($endedDaysAgo),
        ], $attrs));
    }

    private function testimony(Event $event, string $status, array $attrs = []): Testimony
    {
        return Testimony::create(array_merge([
            'user_id' => $this->author->id, 'event_id' => $event->id, 'title' => "Témoignage {$status}", 'type' => 'text',
            'category_slug' => 'autre', 'body_text' => 'Texte', 'visibility' => 'public', 'status' => $status,
            'approved_at' => $status === 'approved' ? now() : null,
        ], $attrs));
    }

    public function test_past_events_are_finished_most_recent_first_with_testimony_count_and_thumbnail(): void
    {
        $older  = $this->event('Croisade 2025', 30);
        $recent = $this->event('Conférence', 2);
        $this->event('Annulé', 5, ['status' => 'cancelled']);
        $this->event('À venir', -10, ['starts_at' => now()->addDays(9)]);
        $this->event('En cours', -1, ['starts_at' => now()->subHour()]);
        $this->event('Masqué', 3, ['hidden_at' => now()]);
        $this->event('Brouillon', 3, ['status' => 'draft']);
        EventImage::create(['event_id' => $recent->id, 'url' => 'https://cdn.example.com/conf.jpg', 'position' => 0]);
        $this->testimony($recent, 'approved');
        $this->testimony($recent, 'approved', ['title' => 'Deuxième']);
        $this->testimony($recent, 'pending');
        $this->testimony($recent, 'approved', ['visibility' => 'private', 'title' => 'Carnet']);

        $response = $this->getJson('/api/v1/events?when=past&limit=1')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $recent->id)
            ->assertJsonPath('data.0.testimonies_count', 2)
            ->assertJsonPath('data.0.thumbnail_url', 'https://cdn.example.com/conf.jpg')
            ->assertJsonPath('data.0.phase', 'past')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.lastPage', 2);
        $this->assertArrayHasKey('currentPage', $response->json('meta'));

        $this->getJson('/api/v1/events?when=past&limit=1&page=2')->assertOk()
            ->assertJsonPath('data.0.id', $older->id)
            ->assertJsonPath('data.0.testimonies_count', 0)
            ->assertJsonPath('data.0.thumbnail_url', null);
    }

    public function test_event_testimonies_lists_approved_testimonies_only(): void
    {
        $event = $this->event('Conférence', 2);
        $approved = $this->testimony($event, 'approved');
        $this->testimony($event, 'pending');
        $this->testimony($event, 'rejected');

        $this->getJson("/api/v1/events/{$event->id}/testimonies")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $approved->id)->assertJsonPath('data.0.eventId', $event->id);
    }

    public function test_website_past_events_page_and_detail_with_testimonies(): void
    {
        $event = $this->event('Grande conférence de Parakou', 2);
        $this->event('Annulée', 4, ['status' => 'cancelled']);
        $testimony = $this->testimony($event, 'approved', ['title' => 'Dieu m\'a relevé']);
        $this->testimony($event, 'approved', ['title' => 'Autre témoignage']);

        $this->get('/evenements/passes')->assertOk()
            ->assertSee('Événements passés')
            ->assertSee('Grande conférence de Parakou')
            ->assertSee('2 témoignages')
            ->assertDontSee('Annulée')
            ->assertSee(route('events.show', ['id' => $event->id, 'section' => 'temoignages']), false);

        // Ancien onglet « Passés » de la liste : page dédiée.
        $this->get('/evenements?onglet=past&type=crusade')->assertRedirect(route('events.past', ['type' => 'crusade']));

        // Détail : ses témoignages.
        $this->get("/evenements/{$event->id}?section=temoignages")->assertOk()->assertSee('Dieu m&#039;a relevé', false);

        $this->from('/')->post('/langue', ['locale' => 'en']);
        $this->get('/evenements/passes')->assertOk()->assertSee('Past events')->assertSee('2 testimonies');
    }
}
