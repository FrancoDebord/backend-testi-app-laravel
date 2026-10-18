<?php

namespace Tests\Feature;

use App\Models\Reaction;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * « Je prie » sur le site, aligné sur l'application mobile (InteractionNotifier::togglePray) : un seul bouton qui
 * pose « pray » ou retire la prière (anciennes « Amen » / « Adorer Dieu » comprises), compteur jamais doublé,
 * même état que l'API (isPrayedByMe, prayersCount). Voir docs/fonctionnalites/reactions.md
 */
class WebPrayToggleTest extends TestCase
{
    use RefreshDatabase;

    private User $reader;
    private Testimony $testimony;
    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        $author = User::create(['display_name' => 'Auteur', 'email' => 'auteur@example.com', 'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active']);
        $this->reader = User::create(['display_name' => 'Lecteur', 'email' => 'lecteur@example.com', 'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active']);
        $this->testimony = Testimony::create([
            'user_id' => $author->id, 'title' => 'Témoignage', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'approved',
        ]);
        $this->url = "/testimonies/{$this->testimony->id}/reactions";
    }

    private function press(string $type = 'pray')
    {
        return $this->actingAs($this->reader)->postJson($this->url, ['type' => $type], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
    }

    public function test_i_pray_toggles_like_the_app(): void
    {
        $this->press()->assertJson(['reacted' => true, 'is_prayed' => true, 'prayer_count' => 1, 'my_reactions' => ['pray']]);
        $this->press()->assertJson(['reacted' => false, 'is_prayed' => false, 'prayer_count' => 0, 'my_reactions' => []]);

        // « J'aime » reste indépendant de « Je prie ».
        $this->press('like');
        $this->press()->assertJson(['is_prayed' => true, 'is_liked' => true, 'like_count' => 1, 'prayer_count' => 1]);

        // Même état que celui lu par l'application.
        Sanctum::actingAs($this->reader);
        $this->getJson("/api/v1/testimonies/{$this->testimony->id}")->assertOk()
            ->assertJsonPath('data.isPrayedByMe', true)->assertJsonPath('data.stats.prayersCount', 1);
    }

    public function test_an_old_amen_counts_as_praying_and_is_removed_by_i_pray(): void
    {
        Sanctum::actingAs($this->reader);
        $this->postJson("/api/v1/testimonies/{$this->testimony->id}/reactions", ['type' => 'amen'])->assertCreated();
        $this->app['auth']->forgetGuards();

        // Page : le bouton « Je prie » est allumé.
        $this->actingAs($this->reader)->get("/testimonies/{$this->testimony->id}")->assertOk()
            ->assertSee('class="chip-active" data-reaction="pray" aria-pressed="true"', false);

        // Appui : la prière est retirée (pas remplacée par « pray »), compteur jamais négatif ni doublé.
        $this->press()->assertJson(['reacted' => false, 'is_prayed' => false, 'prayer_count' => 0]);
        $this->assertSame(0, Reaction::where('testimony_id', $this->testimony->id)->count());
    }

    public function test_app_and_site_share_the_same_single_prayer(): void
    {
        // L'application prie, le site retire : un seul compteur, cohérent.
        Sanctum::actingAs($this->reader);
        $this->postJson("/api/v1/testimonies/{$this->testimony->id}/reactions", ['type' => 'pray'])->assertCreated()
            ->assertJsonPath('data.isPrayedByMe', true)->assertJsonPath('data.prayersCount', 1);
        $this->app['auth']->forgetGuards();

        $this->press()->assertJson(['is_prayed' => false, 'prayer_count' => 0]);
        $this->press()->assertJson(['is_prayed' => true, 'prayer_count' => 1]);

        Sanctum::actingAs($this->reader);
        $this->deleteJson("/api/v1/testimonies/{$this->testimony->id}/reactions?group=prayer")->assertOk()
            ->assertJsonPath('data.isPrayedByMe', false)->assertJsonPath('data.prayersCount', 0);
    }

    public function test_cards_show_the_prayer_count(): void
    {
        $this->testimony->update(['prayer_count' => 7]);

        $this->get('/')->assertOk()->assertSee('fa-hands-praying', false)->assertSee('Je prie');
    }
}
