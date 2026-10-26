<?php

namespace Tests\Feature;

use App\Enums\ReactionType;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Réaction « Adorer Dieu » (worship) : toujours acceptée et comptée comme une prière (API, anciennes réactions).
 * Le site, comme l'application, n'affiche plus qu'un seul bouton « Je prie » (docs/fonctionnalites/reactions.md).
 */
class WorshipReactionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Testimony $testimony;

    protected function setUp(): void
    {
        parent::setUp();
        $author = User::create(['display_name' => 'Auteur', 'email' => 'auteur@example.com', 'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active']);
        $this->user = User::create(['display_name' => 'Lecteur', 'email' => 'lecteur@example.com', 'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active']);
        $this->testimony = Testimony::create([
            'user_id' => $author->id, 'title' => 'Témoignage', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'approved',
        ]);
    }

    public function test_enum_describes_worship(): void
    {
        $this->assertSame('Adorer Dieu', ReactionType::Worship->label());
        $this->assertSame('🙌', ReactionType::Worship->emoji());
        $this->assertSame('prayer_count', ReactionType::Worship->counterField());
        $this->assertSame('like_count', ReactionType::Fire->counterField());
    }

    public function test_web_toggle_counts_worship_as_prayer(): void
    {
        $url = "/testimonies/{$this->testimony->id}/reactions";

        $this->actingAs($this->user)->postJson($url, ['type' => 'worship'], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJson(['reacted' => true, 'prayer_count' => 1, 'like_count' => 0]);
        // Nouvel appui : une prière de plus (docs/fonctionnalites/prier-plusieurs-fois.md), compteur de personnes inchangé.
        $this->actingAs($this->user)->postJson($url, ['type' => 'worship'], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJson(['reacted' => true, 'prayer_count' => 1, 'my_prayer_times' => 2]);
        $this->actingAs($this->user)->postJson($url, ['type' => 'adore'])->assertStatus(422);
    }

    public function test_mobile_api_accepts_worship(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson("/api/v1/testimonies/{$this->testimony->id}/reactions", ['type' => 'worship'])->assertCreated();
        $this->assertSame(1, $this->testimony->fresh()->prayer_count);
        $this->assertDatabaseHas('reactions', ['testimony_id' => $this->testimony->id, 'type' => 'worship']);
    }

    public function test_testimony_page_shows_a_single_i_pray_button(): void
    {
        $this->get("/testimonies/{$this->testimony->id}")->assertOk()
            ->assertSee('data-reaction="pray"', false)->assertSee('Je prie')
            ->assertDontSee('data-reaction="worship"', false)->assertDontSee('data-reaction="amen"', false);
    }
}
