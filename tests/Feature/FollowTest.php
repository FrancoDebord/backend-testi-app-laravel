<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Testimony;
use App\Models\User;
use App\Services\FollowerNotifications;
use App\Services\FollowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** « Suivre » et page Communauté. docs/fonctionnalites/abonnements.md */
class FollowTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => $name, 'email' => str()->slug($name) . '@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    private function org(string $name, string $status = 'verified', array $attrs = []): User
    {
        return $this->user($name, array_merge([
            'account_type' => 'organization', 'organization_name' => $name, 'verification_status' => $status,
        ], $attrs));
    }

    // ─── Règles ────────────────────────────────────────────────────────────

    public function test_nobody_can_follow_themselves(): void
    {
        $me = $this->user('Awa');

        $this->actingAs($me)->postJson("/api/v1/users/{$me->id}/follow")->assertStatus(422);
        $this->actingAs($me)->postJson("/profiles/{$me->id}/follow")->assertStatus(422)
            ->assertJsonPath('message', 'Vous ne pouvez pas vous suivre vous-même.');
        $this->assertSame(0, DB::table('follows')->count());
        $this->assertSame(0, $me->fresh()->follower_count);
    }

    public function test_following_twice_counts_once_and_notifies_once(): void
    {
        $me = $this->user('Awa');
        $church = $this->org('Église de la Grâce');

        $this->actingAs($me)->postJson("/api/v1/users/{$church->id}/follow")->assertOk()
            ->assertJsonPath('data.following', true)
            ->assertJsonPath('data.followerCount', 1);
        $this->actingAs($me)->postJson("/api/v1/users/{$church->id}/follow")->assertOk();

        $this->assertSame(1, DB::table('follows')->count());
        $this->assertSame(1, $church->fresh()->follower_count);
        $this->assertSame(1, $me->fresh()->following_count);
        $this->assertSame(1, AppNotification::where('recipient_id', $church->id)->where('type', 'follow')->count());
    }

    public function test_unfollow_never_makes_counters_negative(): void
    {
        $me = $this->user('Awa');
        $church = $this->org('Église');

        $this->actingAs($me)->postJson("/api/v1/users/{$church->id}/follow")->assertOk();
        $this->actingAs($me)->deleteJson("/api/v1/users/{$church->id}/unfollow")->assertOk()
            ->assertJsonPath('data.following', false)
            ->assertJsonPath('data.followerCount', 0);
        $this->actingAs($me)->deleteJson("/api/v1/users/{$church->id}/unfollow")->assertOk();

        $this->assertSame(0, $church->fresh()->follower_count);
        $this->assertSame(0, $me->fresh()->following_count);
    }

    public function test_inactive_account_cannot_be_followed(): void
    {
        $me = $this->user('Awa');
        $banned = $this->user('Banni', ['status' => 'banned']);

        $this->actingAs($me)->postJson("/api/v1/users/{$banned->id}/follow")->assertNotFound();
    }

    public function test_recount_repairs_drifted_counters(): void
    {
        $a = $this->user('A');
        $b = $this->user('B', ['follower_count' => 42]);
        DB::table('follows')->insert(['follower_id' => $a->id, 'following_id' => $b->id, 'created_at' => now()]);

        FollowService::recountAll();

        $this->assertSame(1, $b->fresh()->follower_count);
        $this->assertSame(1, $a->fresh()->following_count);
    }

    // ─── Site ──────────────────────────────────────────────────────────────

    public function test_web_follow_button_toggles_and_guests_are_sent_to_login(): void
    {
        $me = $this->user('Awa');
        $church = $this->org('Église');

        $this->post("/profiles/{$church->id}/follow")->assertRedirect('/login');

        $this->actingAs($me)->get("/profiles/{$church->id}")->assertOk()
            ->assertSee('data-follow-form', false)
            ->assertSee('Suivre');
        $this->actingAs($me)->get("/profiles/{$me->id}")->assertOk()->assertDontSee('data-follow-form', false);

        $this->actingAs($me)->postJson("/profiles/{$church->id}/follow")->assertOk()
            ->assertJsonPath('following', true)
            ->assertJsonPath('followerCount', 1);
        $this->actingAs($me)->get("/profiles/{$church->id}")->assertSee('Abonné');

        // Sans JavaScript : formulaire classique, retour à la page.
        $this->actingAs($me)->from("/profiles/{$church->id}")->delete("/profiles/{$church->id}/follow")
            ->assertRedirect("/profiles/{$church->id}");
        $this->assertSame(0, $church->fresh()->follower_count);
    }

    public function test_watch_page_offers_to_follow_the_author(): void
    {
        $me = $this->user('Awa');
        $author = $this->user('Auteur');
        $t = Testimony::create([
            'user_id' => $author->id, 'title' => 'Titre', 'type' => 'text', 'category_slug' => 'autre',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ]);

        $this->actingAs($me)->get("/videos/{$t->id}")->assertOk()
            ->assertSee('data-follow-user="' . $author->id . '"', false);
    }

    public function test_other_testimonies_are_offered_in_compact_format(): void
    {
        $author = $this->user('Auteur');
        $make = fn (string $title) => Testimony::create([
            'user_id' => $author->id, 'title' => $title, 'type' => 'text', 'category_slug' => 'autre',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ]);
        $current = $make('Témoignage lu');
        $other = $make('Autre témoignage');

        $this->get("/testimonies/{$current->id}")->assertOk()
            ->assertSee('data-layout="compact"', false)
            ->assertSee('row-details-' . $other->id, false)
            ->assertSee('Autre témoignage');
    }

    // ─── Communauté ────────────────────────────────────────────────────────

    public function test_community_lists_organizations_then_people(): void
    {
        $me = $this->user('Moi', ['testimony_count' => 3]);
        $pending = $this->org('Ministère en attente', 'pending', ['follower_count' => 50]);
        $verified = $this->org('Église vérifiée', 'verified', ['follower_count' => 2]);
        $this->org('Organisation refusée', 'rejected');
        $witness = $this->user('Témoin actif');
        Testimony::create([
            'user_id' => $witness->id, 'title' => 'Titre', 'type' => 'text', 'category_slug' => 'autre',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ]);
        $this->user('Compte sans témoignage');

        $html = $this->actingAs($me)->get('/communaute')->assertOk()
            ->assertSee('Église vérifiée')
            ->assertSee('Ministère en attente')
            ->assertDontSee('Organisation refusée')
            ->assertDontSee('Témoin actif')
            ->getContent();
        // Vérifiées d'abord, même moins suivies.
        $this->assertLessThan(strpos($html, 'Ministère en attente'), strpos($html, 'Église vérifiée'));

        $this->actingAs($me)->get('/communaute?tab=people')->assertOk()
            ->assertSee('Témoin actif')
            ->assertDontSee('Compte sans témoignage')
            ->assertDontSee('data-follow-user="' . $me->id . '"', false);

        $this->actingAs($me)->get('/communaute?q=vérifiée')->assertOk()
            ->assertSee('Église vérifiée')->assertDontSee('Ministère en attente');
    }

    public function test_community_api_returns_follow_state(): void
    {
        $me = $this->user('Moi');
        $church = $this->org('Église');
        $other = $this->org('Autre église');
        app(FollowService::class)->follow($me, $church);

        $data = $this->actingAs($me)->getJson('/api/v1/community?tab=organizations')->assertOk()->json('data');
        $byId = collect($data)->keyBy('id');
        $this->assertTrue($byId[$church->id]['is_following']);
        $this->assertFalse($byId[$other->id]['is_following']);
        $this->assertNull($byId[$church->id]['email'], 'Coordonnées privées.');

        $this->actingAs($me)->getJson("/api/v1/users/{$church->id}")->assertOk()->assertJsonPath('data.is_following', true);
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/users/{$church->id}")->assertOk()->assertJsonMissingPath('data.is_following');
    }

    public function test_app_gets_the_ids_it_follows(): void
    {
        $me = $this->user('Moi');
        $a = $this->org('A');
        $this->org('B');
        app(FollowService::class)->follow($me, $a);

        $this->actingAs($me)->getJson('/api/v1/users/me/following-ids')->assertOk()->assertExactJson([
            'success' => true, 'data' => [$a->id], 'message' => '',
        ]);
    }

    // ─── Notifications des abonnés ─────────────────────────────────────────

    public function test_followers_are_told_about_published_testimonies_but_never_the_journal(): void
    {
        $author = $this->user('Auteur');
        $fan = $this->user('Fan');
        app(FollowService::class)->follow($fan, $author);

        $public = Testimony::create([
            'user_id' => $author->id, 'title' => 'Public', 'type' => 'text', 'category_slug' => 'autre',
            'body_text' => 'x', 'visibility' => 'followers', 'status' => 'approved', 'approved_at' => now(),
        ]);
        $journal = Testimony::create([
            'user_id' => $author->id, 'title' => 'Carnet', 'type' => 'text', 'category_slug' => 'autre',
            'body_text' => 'x', 'visibility' => 'private', 'status' => 'approved',
        ]);

        $this->assertSame(1, FollowerNotifications::newTestimony($public->load('user')));
        $this->assertSame(0, FollowerNotifications::newTestimony($journal->load('user')));
        $this->assertSame(1, AppNotification::where('recipient_id', $fan->id)->where('type', 'new_followed_testimony')->count());
    }
}
