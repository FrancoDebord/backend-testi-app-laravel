<?php

namespace Tests\Feature;

use App\Models\Reaction;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * « Je prie » sur le site : chaque appui ajoute une prière (anciennes « Amen » / « Adorer Dieu » comprises),
 * « Retirer ma dernière prière » (remove_last) en retire une ; le compteur reste le nombre de personnes, même état
 * que l'API (isPrayedByMe, myPrayerTimes, prayersCount). Voir docs/fonctionnalites/reactions.md, prier-plusieurs-fois.md
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

    private function press(string $type = 'pray', array $extra = [])
    {
        return $this->actingAs($this->reader)->postJson($this->url, ['type' => $type, ...$extra], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
    }

    public function test_i_pray_adds_a_prayer_each_time(): void
    {
        $this->press()->assertJson(['reacted' => true, 'is_prayed' => true, 'prayer_count' => 1, 'my_prayer_times' => 1, 'my_reactions' => ['pray']]);
        $this->press()->assertJson(['reacted' => true, 'is_prayed' => true, 'prayer_count' => 1, 'my_prayer_times' => 2]);
        $this->press()->assertJson(['my_prayer_times' => 3, 'prayer_count' => 1]);

        // « J'aime » reste indépendant de « Je prie ».
        $this->press('like')->assertJson(['is_prayed' => true, 'is_liked' => true, 'like_count' => 1, 'prayer_count' => 1]);

        // Même état que celui lu par l'application.
        Sanctum::actingAs($this->reader);
        $this->getJson("/api/v1/testimonies/{$this->testimony->id}")->assertOk()
            ->assertJsonPath('data.isPrayedByMe', true)->assertJsonPath('data.myPrayerTimes', 3)->assertJsonPath('data.stats.prayersCount', 1);
        $this->app['auth']->forgetGuards();

        // Page : nombre de fois et retrait de la dernière prière.
        $this->actingAs($this->reader)->get("/testimonies/{$this->testimony->id}")->assertOk()
            ->assertSee('Vous avez prié 3 fois')->assertSee('data-pray-remove-last', false);

        // Retrait : une prière à la fois ; la dernière retire la réaction.
        $this->press('pray', ['remove_last' => true])->assertJson(['is_prayed' => true, 'my_prayer_times' => 2, 'prayer_count' => 1]);
        $this->press('pray', ['remove_last' => true])->assertJson(['my_prayer_times' => 1]);
        $this->press('pray', ['remove_last' => true])->assertJson(['reacted' => false, 'is_prayed' => false, 'my_prayer_times' => 0, 'prayer_count' => 0]);
    }

    public function test_an_old_amen_counts_as_praying_and_is_kept(): void
    {
        Sanctum::actingAs($this->reader);
        $this->postJson("/api/v1/testimonies/{$this->testimony->id}/reactions", ['type' => 'amen'])->assertCreated();
        $this->app['auth']->forgetGuards();

        // Page : le bouton « Je prie » est allumé.
        $this->actingAs($this->reader)->get("/testimonies/{$this->testimony->id}")->assertOk()
            ->assertSee('class="chip-active" data-reaction="pray" aria-pressed="true"', false);

        // Appui : une prière de plus sur la même réaction, compteur jamais doublé.
        $this->press()->assertJson(['reacted' => true, 'is_prayed' => true, 'prayer_count' => 1, 'my_prayer_times' => 2]);
        $this->assertSame(['amen'], Reaction::where('testimony_id', $this->testimony->id)->pluck('type')->map->value->all());
    }

    public function test_app_and_site_share_the_same_single_prayer(): void
    {
        // L'application prie, le site prie encore : un seul compteur de personnes, deux prières.
        Sanctum::actingAs($this->reader);
        $this->postJson("/api/v1/testimonies/{$this->testimony->id}/reactions", ['type' => 'pray'])->assertCreated()
            ->assertJsonPath('data.isPrayedByMe', true)->assertJsonPath('data.prayersCount', 1)->assertJsonPath('data.myPrayerTimes', 1);
        $this->app['auth']->forgetGuards();

        $this->press()->assertJson(['is_prayed' => true, 'prayer_count' => 1, 'my_prayer_times' => 2]);

        // Ancienne application : renvoyer « pray » sans again ne compte rien (idempotent).
        Sanctum::actingAs($this->reader);
        $this->postJson("/api/v1/testimonies/{$this->testimony->id}/reactions", ['type' => 'pray'])->assertOk()
            ->assertJsonPath('data.myPrayerTimes', 2);
        // Application : again = prier encore ; ?last=1 = retirer la dernière ; sans last, tout est retiré.
        $this->postJson("/api/v1/testimonies/{$this->testimony->id}/reactions", ['type' => 'pray', 'again' => true])->assertOk()
            ->assertJsonPath('data.myPrayerTimes', 3)->assertJsonPath('data.prayersCount', 1);
        $this->deleteJson("/api/v1/testimonies/{$this->testimony->id}/reactions?group=prayer&last=1")->assertOk()
            ->assertJsonPath('data.myPrayerTimes', 2)->assertJsonPath('data.isPrayedByMe', true);
        $this->deleteJson("/api/v1/testimonies/{$this->testimony->id}/reactions?group=prayer")->assertOk()
            ->assertJsonPath('data.isPrayedByMe', false)->assertJsonPath('data.myPrayerTimes', 0)->assertJsonPath('data.prayersCount', 0);
    }

    public function test_cards_show_the_prayer_count(): void
    {
        $this->testimony->update(['prayer_count' => 7]);

        $this->get('/')->assertOk()->assertSee('fa-hands-praying', false)->assertSee('Je prie');
    }
}
