<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fils : événements à venir des comptes suivis (GET /events?scope=following, « Mon fil » du site).
 * Voir docs/fonctionnalites/evenements.md et recommandations.md
 */
class FeedInsertsTest extends TestCase
{
    use RefreshDatabase;

    private User $followed;
    private User $other;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $org = fn (string $name, string $label) => $this->makeUser($name, [
            'account_type' => 'organization', 'organization_name' => $label,
            'organization_type' => 'church', 'verification_status' => 'verified',
        ]);
        $this->followed = $org('suivie', 'Église suivie');
        $this->other    = $org('autre', 'Église inconnue');
        $this->user     = $this->makeUser('fidele');
        $this->user->following()->attach($this->followed->id);
    }

    private function makeUser(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => ucfirst($name), 'email' => "{$name}@example.com",
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    private function createEvent(User $by, string $title, int $inDays = 10): string
    {
        return $this->actingAs($by)->postJson('/api/v1/events', [
            'title'     => $title,
            'type'      => 'crusade',
            'starts_at' => now()->addDays($inDays)->toIso8601String(),
            'ends_at'   => now()->addDays($inDays + 1)->toIso8601String(),
            'city'      => 'Cotonou',
        ])->assertCreated()->json('data.id');
    }

    public function test_api_following_scope_lists_upcoming_events_of_followed_accounts(): void
    {
        $this->createEvent($this->followed, 'Croisade suivie');
        $this->createEvent($this->other, 'Croisade inconnue');
        $past = $this->createEvent($this->followed, 'Croisade passée');
        Event::whereKey($past)->update(['starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(2)]);

        $titles = $this->actingAs($this->user)->getJson('/api/v1/events?scope=following')
            ->assertOk()->json('data.*.title');

        $this->assertSame(['Croisade suivie'], $titles);
    }

    public function test_api_following_scope_is_empty_for_guests(): void
    {
        $this->createEvent($this->followed, 'Croisade suivie');
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/events?scope=following')->assertOk()->assertJsonCount(0, 'data');
    }

    private function pray(User $by, string $body, array $extra = []): void
    {
        $this->actingAs($by)->postJson('/api/v1/prayer/requests', ['body' => $body] + $extra)->assertCreated();
    }

    public function test_web_personal_feed_shows_prayer_requests_of_followed_accounts_except_anonymous(): void
    {
        $this->pray($this->followed, 'Priez pour notre croisade de décembre.');
        $this->pray($this->followed, 'Requête anonyme de cette église.', ['is_anonymous' => true]);
        $this->pray($this->other, 'Requête d’une église inconnue.');

        $this->actingAs($this->user)->get(route('feed.personal'))
            ->assertOk()
            ->assertSee('Priez pour notre croisade de décembre.')
            ->assertDontSee('Requête anonyme de cette église.')
            ->assertDontSee('Requête d’une église inconnue.');
    }

    public function test_home_inserts_public_prayer_requests_only(): void
    {
        $this->pray($this->other, 'Requête publique pour tous.');
        $this->pray($this->other, 'Requête réservée aux abonnés.', ['visibility' => 'followers']);
        $this->app['auth']->forgetGuards();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Requête publique pour tous.')
            ->assertDontSee('Requête réservée aux abonnés.');
    }

    public function test_web_personal_feed_shows_followed_events_even_when_empty(): void
    {
        $this->createEvent($this->followed, 'Croisade suivie');
        $this->createEvent($this->other, 'Croisade inconnue');

        $this->actingAs($this->user)->get(route('feed.personal'))
            ->assertOk()
            ->assertSee('Croisade suivie')
            ->assertSee('Événement · ', false)
            ->assertDontSee('Croisade inconnue');
    }
}
