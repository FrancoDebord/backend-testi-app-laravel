<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Category;
use App\Models\Prophecy;
use App\Models\Testimony;
use App\Models\TestimonyRepost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * « Mon fil » comme l'accueil (tous les témoignages, comptes suivis d'abord), éléments insérés
 * (GET /feed/inserts) et partage interne (« X a partagé »).
 * Voir docs/fonctionnalites/recommandations.md et partage-interne.md
 */
class PersonalFeedAndRepostTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $followed;
    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['slug' => 'guerison', 'name' => 'Guérison']);
        $this->user     = $this->makeUser('marie');
        $this->followed = $this->makeUser('paul');
        $this->stranger = $this->makeUser('inconnu');
        $this->user->following()->attach($this->followed->id);
    }

    private function makeUser(string $name): User
    {
        return User::create([
            'display_name' => ucfirst($name), 'email' => "{$name}@example.com",
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ]);
    }

    private function published(User $author, string $title, int $daysAgo = 0, array $attrs = []): Testimony
    {
        return Testimony::create(array_merge([
            'user_id' => $author->id, 'title' => $title, 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Récit', 'visibility' => 'public', 'status' => 'approved',
            'approved_at' => now()->subDays($daysAgo)->subMinutes(5),
        ], $attrs));
    }

    private function feed(int $page = 1, int $limit = 20): array
    {
        return $this->actingAs($this->user)->getJson("/api/v1/feed?page={$page}&limit={$limit}")->assertOk()->json();
    }

    // ─── Mon fil ─────────────────────────────────────────────────────────────

    public function test_followed_recent_first_then_every_other_published_testimony(): void
    {
        $this->published($this->stranger, 'Inconnu récent');
        $this->published($this->followed, 'Suivi ancien', 90);
        $this->published($this->followed, 'Suivi 1', 2);
        $this->published($this->followed, 'Suivi 2', 1);
        $this->published($this->stranger, 'Inconnu ancien', 60);
        $this->published($this->user, 'Le mien');
        $this->published($this->stranger, 'Réservé aux abonnés', 0, ['visibility' => 'followers']);

        $res = $this->feed();
        $titles = array_column($res['data'], 'title');

        // Les témoignages récents des comptes suivis et les siens d'abord, du plus récent au plus ancien.
        $this->assertSame(['Le mien', 'Suivi 2', 'Suivi 1'], array_slice($titles, 0, 3));
        // Puis tous les autres, sans doublon : non suivis (« Suggestion ») et anciens des comptes suivis.
        $this->assertEqualsCanonicalizing(['Le mien', 'Suivi 2', 'Suivi 1', 'Inconnu récent', 'Suivi ancien', 'Inconnu ancien'], $titles);
        $this->assertSame(6, $res['meta']['total']);
        $reasons = array_column($res['data'], 'feedReason', 'title');
        $this->assertSame('suggested', $reasons['Inconnu récent']);
        $this->assertSame('following', $reasons['Suivi ancien']);
    }

    public function test_pagination_is_stable_and_without_duplicates(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $this->published($this->followed, "Suivi {$i}", $i);
            $this->published($this->stranger, "Autre {$i}", $i);
        }

        $ids = [];
        for ($page = 1; $page <= 3; $page++) {
            $ids = [...$ids, ...array_column($this->feed($page, 3)['data'], 'id')];
        }

        $this->assertCount(8, $ids);
        $this->assertCount(8, array_unique($ids));
    }

    // ─── Éléments insérés ────────────────────────────────────────────────────

    private function createSession(User $host, string $title): void
    {
        $this->actingAs($host)->postJson('/api/v1/prayer/sessions', [
            'title' => $title, 'starts_at' => now()->addDays(2)->toIso8601String(),
        ])->assertCreated();
    }

    private function prophecy(User $author, string $text, bool $public = true, string $status = 'approved'): void
    {
        $t = $this->published($author, "Accomplissement : {$text}", 1, ['status' => $status]);
        Prophecy::create([
            'user_id' => $author->id, 'body_text' => $text, 'received_on' => now()->subYear()->toDateString(),
            'status' => 'fulfilled', 'fulfilled_on' => now()->toDateString(), 'testimony_id' => $t->id, 'is_public' => $public,
        ]);
    }

    public function test_inserts_followed_accounts_first_then_others(): void
    {
        $this->createSession($this->followed, 'Veillée suivie');
        $this->createSession($this->stranger, 'Veillée inconnue');
        $this->prophecy($this->followed, 'Parole suivie');
        $this->prophecy($this->stranger, 'Parole inconnue');
        $this->prophecy($this->stranger, 'Parole privée', public: false);
        $this->prophecy($this->stranger, 'Parole non publiée', status: 'pending');
        $this->prophecy($this->user, 'Ma parole');

        $data = $this->actingAs($this->user)->getJson('/api/v1/feed/inserts')->assertOk()->json('data');

        $this->assertSame(['Veillée suivie'], array_column($data['following']['prayerSessions'], 'title'));
        $this->assertSame(['Veillée inconnue'], array_column($data['others']['prayerSessions'], 'title'));
        // Ses propres paroles publiques figurent aussi dans le groupe « suivis ».
        $this->assertEqualsCanonicalizing(['Ma parole', 'Parole suivie'], array_column($data['following']['prophecies'], 'bodyText'));
        $this->assertSame(['Parole inconnue'], array_column($data['others']['prophecies'], 'bodyText'));
        $this->assertSame('Paul', collect($data['following']['prophecies'])->firstWhere('bodyText', 'Parole suivie')['user']['display_name']);
        $this->assertArrayNotHasKey('reminder', $data['others']['prophecies'][0]);
        $this->assertArrayHasKey('events', $data['others']);
        $this->assertArrayHasKey('prayerRequests', $data['others']);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/feed/inserts')->assertUnauthorized();
    }

    public function test_web_personal_feed_shows_sessions_and_public_prophecies(): void
    {
        $this->createSession($this->stranger, 'Veillée inconnue');
        $this->prophecy($this->followed, 'Parole suivie');
        $this->prophecy($this->stranger, 'Parole privée', public: false);

        $this->actingAs($this->user)->get(route('feed.personal'))
            ->assertOk()
            ->assertSee('Veillée inconnue')
            ->assertSee('Parole suivie')
            ->assertSee('Parole prophétique accomplie')
            ->assertSee('« Parole suivie', false)
            ->assertDontSee('« Parole privée', false); // son témoignage reste listé, pas la parole
    }

    public function test_web_home_lists_upcoming_prayer_sessions(): void
    {
        $this->createSession($this->stranger, 'Veillée pour la nation');
        $this->app['auth']->forgetGuards();

        $this->get(route('home'))->assertOk()
            ->assertSee('Sessions de prière à venir')
            ->assertSee('Veillée pour la nation');
    }

    // ─── Partage interne ─────────────────────────────────────────────────────

    public function test_repost_is_unique_notifies_author_and_counts(): void
    {
        $t = $this->published($this->stranger, 'Guéri du cancer');

        $this->actingAs($this->followed)->postJson("/api/v1/testimonies/{$t->id}/repost", ['comment' => 'À lire !'])
            ->assertCreated()
            ->assertJsonPath('data.comment', 'À lire !')
            ->assertJsonPath('data.repostsCount', 1);
        // Un seul partage par personne : un nouvel envoi change le commentaire.
        $this->actingAs($this->followed)->postJson("/api/v1/testimonies/{$t->id}/repost", ['comment' => 'Magnifique'])
            ->assertOk()->assertJsonPath('data.repostsCount', 1);

        $this->assertSame(1, TestimonyRepost::count());
        $this->assertSame('Magnifique', TestimonyRepost::first()->comment);
        $notification = AppNotification::where('recipient_id', $this->stranger->id)->sole();
        $this->assertSame('share', $notification->type instanceof \BackedEnum ? $notification->type->value : $notification->type);
        $this->assertStringContainsString('Paul a partagé votre témoignage', $notification->message);

        $this->actingAs($this->followed)->getJson("/api/v1/testimonies/{$t->id}")
            ->assertJsonPath('data.stats.repostsCount', 1)
            ->assertJsonPath('data.isRepostedByMe', true);

        // Annulation.
        $this->actingAs($this->followed)->deleteJson("/api/v1/testimonies/{$t->id}/repost")->assertOk()->assertJsonPath('data.repostsCount', 0);
        $this->actingAs($this->followed)->deleteJson("/api/v1/testimonies/{$t->id}/repost")->assertNotFound();
        $this->assertSame(0, TestimonyRepost::count());
    }

    public function test_repost_refused_for_own_or_non_public_testimony(): void
    {
        $own = $this->published($this->followed, 'Le mien');
        $followersOnly = $this->published($this->stranger, 'Abonnés', 0, ['visibility' => 'followers']);
        $pending = $this->published($this->stranger, 'En attente', 0, ['status' => 'pending']);

        $this->actingAs($this->followed)->postJson("/api/v1/testimonies/{$own->id}/repost")->assertStatus(422);
        $this->actingAs($this->followed)->postJson("/api/v1/testimonies/{$followersOnly->id}/repost")->assertNotFound();
        $this->actingAs($this->followed)->postJson("/api/v1/testimonies/{$pending->id}/repost")->assertNotFound();
        $this->actingAs($this->followed)->postJson('/api/v1/testimonies/' . str_repeat('a', 8) . '/repost')->assertNotFound();
        $this->assertSame(0, TestimonyRepost::count());
    }

    public function test_repost_appears_in_followers_feed_with_original_author_and_disappears_when_hidden(): void
    {
        $t = $this->published($this->stranger, 'Guéri du cancer', 20);
        $this->published($this->followed, 'Témoignage de Paul', 3);
        $this->actingAs($this->followed)->postJson("/api/v1/testimonies/{$t->id}/repost", ['comment' => 'À lire !'])->assertCreated();

        $first = $this->feed()['data'][0];
        $this->assertSame('Guéri du cancer', $first['title']);
        $this->assertSame('repost', $first['feedReason']);
        $this->assertSame($this->stranger->id, $first['userId']); // l'auteur d'origine reste affiché
        $this->assertSame($this->followed->id, $first['repostedBy']['id']);
        $this->assertSame('À lire !', $first['repostComment']);
        $this->assertNotNull($first['repostedAt']);

        // Profil du partageur : section « Partages ».
        $this->getJson("/api/v1/users/{$this->followed->id}/reposts")->assertOk()
            ->assertJsonPath('data.0.title', 'Guéri du cancer')->assertJsonPath('data.0.feedReason', 'repost');

        // Le témoignage n'est plus public : le partage disparaît.
        $t->update(['visibility' => 'followers']);
        Cache::flush();
        $this->assertNotContains('Guéri du cancer', array_column($this->feed()['data'], 'title'));
        $this->getJson("/api/v1/users/{$this->followed->id}/reposts")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_web_repost_button_and_personal_feed_card(): void
    {
        $t = $this->published($this->stranger, 'Guéri du cancer', 20);

        $this->actingAs($this->followed)->get(route('testimonies.show', $t->id))->assertOk()->assertSee('Partager sur My Testimony');
        $this->actingAs($this->stranger)->get(route('testimonies.show', $t->id))->assertOk()->assertDontSee('Partager sur My Testimony');

        $this->actingAs($this->followed)->from(route('testimonies.show', $t->id))
            ->post(route('testimonies.repost', $t->id), ['comment' => 'Dieu est fidèle'])
            ->assertRedirect(route('testimonies.show', $t->id))->assertSessionHas('success');

        $this->actingAs($this->user)->get(route('feed.personal'))->assertOk()
            ->assertSeeInOrder(['Paul', 'a partagé', 'Dieu est fidèle', 'Guéri du cancer']);
        $this->get(route('profiles.show', $this->followed->id))->assertOk()->assertSee('Partages')->assertSee('Guéri du cancer');

        $this->actingAs($this->followed)->delete(route('testimonies.unrepost', $t->id))->assertRedirect();
        $this->assertSame(0, TestimonyRepost::count());
        $this->assertSame(0, $t->fresh()->repost_count);
    }
}
