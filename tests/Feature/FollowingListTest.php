<?php

namespace Tests\Feature;

use App\Models\Testimony;
use App\Models\User;
use App\Services\FollowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** « Mes abonnements » et comptage réel des témoignages de la Communauté. docs/fonctionnalites/abonnements.md */
class FollowingListTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => $name, 'email' => str()->slug($name) . '@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    private function org(string $name): User
    {
        return $this->user($name, ['account_type' => 'organization', 'organization_name' => $name, 'verification_status' => 'verified']);
    }

    private function publish(User $author, string $status = 'approved'): Testimony
    {
        return Testimony::create([
            'user_id' => $author->id, 'title' => 'Titre', 'type' => 'text', 'category_slug' => 'autre',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => $status, 'approved_at' => now(),
        ]);
    }

    public function test_people_tab_counts_published_testimonies_even_when_the_counter_is_stale(): void
    {
        $author = $this->user('Awa Témoin');          // testimony_count reste à 0 (compteur non tenu à jour)
        $this->publish($author);
        $this->publish($author);
        $this->publish($author, 'pending');
        $silent = $this->user('Sans Témoignage', ['testimony_count' => 5]); // compteur faux, aucun témoignage publié

        $this->getJson('/api/v1/community?tab=people')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $author->id)
            ->assertJsonPath('data.0.testimony_count', 2);

        $this->get('/communaute?tab=people')->assertOk()
            ->assertSee('Awa Témoin')->assertSee('2 témoignages')
            ->assertDontSee('Sans Témoignage');

        $this->getJson("/api/v1/users/{$author->id}")->assertJsonPath('data.testimony_count', 2);
        $this->assertNotNull($silent);
    }

    public function test_api_lists_my_following_newest_first_with_search_and_pagination_meta(): void
    {
        $me = $this->user('Moi');
        $church = $this->org('Église Lumière');
        $paul = $this->user('Paul Martin');
        $banned = $this->user('Compte Banni');
        $stranger = $this->user('Inconnu');

        $follows = app(FollowService::class);
        $follows->follow($me, $church);
        $this->travel(1)->minutes();
        $follows->follow($me, $paul);
        $follows->follow($me, $banned);
        $follows->follow($stranger, $me);   // abonné, pas abonnement
        $banned->update(['status' => 'banned']); // banni après l'abonnement : masqué

        $this->actingAs($me)->getJson('/api/v1/users/me/following')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $paul->id)          // le plus récent d'abord
            ->assertJsonPath('data.1.id', $church->id)
            ->assertJsonPath('data.0.is_following', true)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 1);

        $this->actingAs($me)->getJson('/api/v1/users/me/following?q=lumi')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $church->id);
    }

    public function test_api_following_requires_login(): void
    {
        $this->getJson('/api/v1/users/me/following')->assertUnauthorized();
    }

    public function test_web_page_lists_following_and_invites_to_discover_when_empty(): void
    {
        $me = $this->user('Moi');
        $this->actingAs($me)->get('/profile/abonnements')->assertOk()
            ->assertSee('Vous ne suivez encore personne.')
            ->assertSee(route('community.index'), false);

        $church = $this->org('Église Lumière');
        app(FollowService::class)->follow($me, $church);

        $this->actingAs($me)->get('/profile/abonnements')->assertOk()
            ->assertSee('Église Lumière')
            ->assertSee('Vous suivez')
            ->assertSee('data-follow-user="' . $church->id . '"', false);

        // Le nombre d'abonnements du profil mène à la page ; menu « Mes abonnements ».
        $this->actingAs($me)->get("/profiles/{$me->id}")->assertOk()
            ->assertSee(route('profile.following'), false)
            ->assertSee('Mes abonnements');

        $this->forgetGuards();
        $this->get('/profile/abonnements')->assertRedirect('/login');
    }

    private function forgetGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }
}
