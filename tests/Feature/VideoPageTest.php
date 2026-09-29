<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Comment;
use App\Models\LiveSession;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Page Vidéos : liste, lecture, vues, commentaires et réponses. docs/fonctionnalites/videos.md */
class VideoPageTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private User $viewer;
    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author    = $this->user('Marie Auteur', 'utilisateur');
        $this->viewer    = $this->user('Jean Dupont', 'utilisateur');
        $this->moderator = $this->user('Modératrice', 'moderateur');
        Category::create(['name' => 'Guérison', 'slug' => 'guerison', 'is_active' => true]);
    }

    private function user(string $name, string $role): User
    {
        return User::create([
            'display_name' => $name, 'email' => str()->slug($name) . '@example.com', 'password' => 'secret123',
            'role' => $role, 'status' => 'active',
        ]);
    }

    private function testimony(string $title, array $attrs = []): Testimony
    {
        return Testimony::create(array_merge([
            'user_id' => $this->author->id, 'title' => $title, 'type' => 'video', 'category_slug' => 'guerison',
            'media_url' => 'https://example.com/v.mp4', 'duration_sec' => 300,
            'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ], $attrs))->fresh();
    }

    // ─── Liste ───────────────────────────────────────────────────────────

    public function test_index_lists_only_published_content(): void
    {
        $this->testimony('Vidéo publiée');
        $this->testimony('Vidéo en attente', ['status' => 'pending']);
        $this->testimony('Vidéo privée', ['visibility' => 'private']);

        $this->get('/videos')->assertOk()
            ->assertSee('Vidéo publiée')
            ->assertDontSee('Vidéo en attente')
            ->assertDontSee('Vidéo privée');
    }

    public function test_tabs_separate_videos_shorts_audio_text_and_replays(): void
    {
        $this->testimony('Longue vidéo', ['duration_sec' => 600]);
        $this->testimony('Petit short', ['duration_sec' => 45]);
        $this->testimony('Écoute audio', ['type' => 'audio']);
        $this->testimony('Récit écrit', ['type' => 'text', 'body_text' => 'Mon témoignage', 'media_url' => null]);
        $replay = $this->testimony('Rediffusion du direct');
        LiveSession::create([
            'host_id' => $this->moderator->id, 'title' => 'Direct', 'room_name' => 'r1', 'status' => 'ended', 'testimony_id' => $replay->id,
        ]);

        $this->get('/videos?tab=shorts')->assertSee('Petit short')->assertDontSee('Longue vidéo');
        $this->get('/videos?tab=videos')->assertSee('Longue vidéo')->assertDontSee('Petit short')->assertDontSee('Écoute audio');
        $this->get('/videos?tab=audio')->assertSee('Écoute audio')->assertDontSee('Longue vidéo');
        $this->get('/videos?tab=text')->assertSee('Récit écrit')->assertDontSee('Écoute audio');
        $this->get('/videos?tab=lives')->assertSee('Rediffusion du direct')->assertDontSee('Longue vidéo');
    }

    public function test_search_by_title_author_or_category_and_empty_state(): void
    {
        $this->testimony('Délivrance à Cotonou');
        $other = $this->user('Paul Autre', 'utilisateur');
        $this->testimony('Autre histoire', ['user_id' => $other->id, 'category_slug' => 'famille']);

        $this->get('/videos?q=Cotonou')->assertSee('Délivrance à Cotonou')->assertDontSee('Autre histoire');
        $this->get('/videos?q=Paul')->assertSee('Autre histoire')->assertDontSee('Délivrance à Cotonou');
        $this->get('/videos?q=Guérison')->assertSee('Délivrance à Cotonou')->assertDontSee('Autre histoire');
        $this->get('/videos?q=introuvable')->assertSee('Aucune vidéo ne correspond à votre recherche.');
    }

    public function test_empty_page_message(): void
    {
        $this->get('/videos')->assertOk()->assertSee('Aucune vidéo disponible pour le moment.');
    }

    public function test_load_more_returns_next_cards_as_json(): void
    {
        foreach (range(1, 26) as $i) {
            $this->testimony("Vidéo {$i}");
        }

        $this->getJson('/videos?page=2')->assertOk()
            ->assertJsonPath('next', null)
            ->assertJsonStructure(['html', 'next']);
    }

    // ─── Lecture et vues ─────────────────────────────────────────────────

    public function test_unpublished_content_is_hidden_except_for_author_and_moderation(): void
    {
        $pending = $this->testimony('En attente', ['status' => 'pending']);

        $this->get("/videos/{$pending->id}")->assertNotFound();
        $this->actingAs($this->viewer)->get("/videos/{$pending->id}")->assertNotFound();
        $this->actingAs($this->author)->get("/videos/{$pending->id}")->assertOk()->assertSee('Aperçu');
        $this->actingAs($this->moderator)->get("/videos/{$pending->id}")->assertOk();
    }

    public function test_private_journal_entry_is_visible_to_its_author_only(): void
    {
        $entry = $this->testimony('Carnet', ['visibility' => 'private', 'status' => 'draft']);

        $this->actingAs($this->author)->get("/videos/{$entry->id}")->assertOk();
        $this->actingAs($this->moderator)->get("/videos/{$entry->id}")->assertNotFound();
        $this->actingAs($this->moderator)->postJson("/videos/{$entry->id}/comments", ['body' => 'Bonjour'])->assertNotFound();
    }

    public function test_journal_entry_on_testimony_page_is_hidden_and_not_counted(): void
    {
        $entry = $this->testimony('Carnet', ['visibility' => 'private', 'status' => 'draft']);

        $this->actingAs($this->moderator)->get("/testimonies/{$entry->id}")->assertNotFound();
        $this->actingAs($this->author)->get("/testimonies/{$entry->id}")->assertOk()
            ->assertSee('carnet privé')->assertDontSee('data-share=', false);

        $this->assertSame(0, $entry->fresh()->views_count, "les lectures de l'auteur ne sont pas des vues");
    }

    public function test_private_testimony_created_on_the_web_goes_to_the_journal(): void
    {
        $this->actingAs($this->author)->post('/testimonies', [
            'title' => 'Pour moi', 'type' => 'text', 'category' => 'guerison', 'body_text' => 'Merci',
            'visibility' => 'private', 'consent_given' => '1',
        ])->assertSessionHas('success', 'Témoignage enregistré dans votre carnet privé.');

        $this->assertSame('draft', Testimony::where('title', 'Pour moi')->first()->status->value);
    }

    public function test_watch_page_shows_player_and_recommendations(): void
    {
        $video = $this->testimony('Vidéo principale');
        $this->testimony('Suggestion');

        $this->get("/videos/{$video->id}")->assertOk()
            ->assertSee('<video', false)
            ->assertSee('https://example.com/v.mp4', false)
            ->assertSee('À regarder également')
            ->assertSee('Suggestion');
    }

    public function test_video_view_is_counted_once_per_viewer_and_fills_unknown_duration(): void
    {
        $video = $this->testimony('Vidéo', ['duration_sec' => 0]);

        $this->actingAs($this->viewer)->postJson("/videos/{$video->id}/view", ['duration' => 42.4])
            ->assertOk()->assertJsonPath('counted', true)->assertJsonPath('label', '1 vue');
        $this->actingAs($this->viewer)->postJson("/videos/{$video->id}/view", ['duration' => 99])
            ->assertJsonPath('counted', false);

        $video->refresh();
        $this->assertSame(1, $video->views_count);
        $this->assertSame(42, $video->duration_sec, 'la durée lue par le lecteur est retenue une seule fois');
        $this->assertTrue($video->isShort());
    }

    public function test_text_view_is_counted_on_open_once(): void
    {
        $text = $this->testimony('Texte', ['type' => 'text', 'body_text' => 'Bonjour', 'media_url' => null]);

        $this->actingAs($this->viewer)->get("/videos/{$text->id}")->assertOk();
        $this->actingAs($this->viewer)->get("/videos/{$text->id}")->assertOk();

        $this->assertSame(1, $text->fresh()->views_count);
    }

    // ─── Commentaires ────────────────────────────────────────────────────

    public function test_guest_cannot_comment(): void
    {
        $video = $this->testimony('Vidéo');

        $this->post("/videos/{$video->id}/comments", ['body' => 'Bonjour'])->assertRedirect('/login');
        $this->assertSame(0, Comment::count());
    }

    public function test_comment_reply_update_and_delete(): void
    {
        $video = $this->testimony('Vidéo');

        // Publication : HTML échappé renvoyé pour affichage immédiat.
        $root = $this->actingAs($this->viewer)
            ->postJson("/videos/{$video->id}/comments", ['body' => '<script>alert(1)</script> Très bonne vidéo !'])
            ->assertCreated()->assertJsonPath('count', 1);
        $this->assertStringNotContainsString('<script>', $root->json('html'));
        $rootId = Comment::whereNull('parent_id')->value('id');

        // Réponse, puis réponse à une réponse : rattachée au commentaire principal (un seul niveau).
        $this->actingAs($this->author)->postJson("/videos/{$video->id}/comments", ['body' => "Je suis d'accord !", 'parent_id' => $rootId])
            ->assertCreated()->assertJsonPath('parentId', $rootId)->assertJsonPath('replies', 1);
        $replyId = Comment::whereNotNull('parent_id')->value('id');
        $this->actingAs($this->viewer)->postJson("/videos/{$video->id}/comments", ['body' => 'Merci', 'parent_id' => $replyId])
            ->assertCreated()->assertJsonPath('parentId', $rootId)->assertJsonPath('replies', 2)->assertJsonPath('count', 3);

        $this->getJson("/comments/{$rootId}/replies")->assertOk()->assertSee("Je suis d&#039;accord !", false);

        // Modification : seulement par l'auteur du commentaire.
        $this->actingAs($this->author)->putJson("/comments/{$rootId}", ['body' => 'Piraté'])->assertForbidden();
        $this->actingAs($this->viewer)->putJson("/comments/{$rootId}", ['body' => 'Excellente vidéo'])
            ->assertOk()->assertJsonPath('body', 'Excellente vidéo');

        // Suppression : pas par un tiers ; supprimer le commentaire principal retire ses réponses.
        $this->actingAs($this->author)->deleteJson("/comments/{$rootId}")->assertForbidden();
        $this->actingAs($this->viewer)->deleteJson("/comments/{$rootId}")->assertOk()->assertJsonPath('count', 0);
        $this->assertSame(0, Comment::count());
        $this->assertSame(0, $video->fresh()->comment_count);
    }

    public function test_moderator_can_delete_a_reply_and_counters_follow(): void
    {
        $video = $this->testimony('Vidéo');
        $this->actingAs($this->viewer)->postJson("/videos/{$video->id}/comments", ['body' => 'Principal']);
        $rootId = Comment::value('id');
        $this->actingAs($this->author)->postJson("/videos/{$video->id}/comments", ['body' => 'Réponse', 'parent_id' => $rootId]);
        $replyId = Comment::whereNotNull('parent_id')->value('id');

        $this->actingAs($this->moderator)->deleteJson("/comments/{$replyId}")
            ->assertOk()->assertJsonPath('parentId', $rootId)->assertJsonPath('replies', 0)->assertJsonPath('count', 1);
    }

    public function test_cannot_comment_hidden_content_or_empty_body(): void
    {
        $pending = $this->testimony('En attente', ['status' => 'pending']);
        $video   = $this->testimony('Vidéo');

        $this->actingAs($this->viewer)->postJson("/videos/{$pending->id}/comments", ['body' => 'Bonjour'])->assertNotFound();
        $this->actingAs($this->viewer)->postJson("/videos/{$video->id}/comments", ['body' => '   '])->assertStatus(422);
    }

    public function test_like_reuses_existing_reactions(): void
    {
        $video = $this->testimony('Vidéo');

        $this->actingAs($this->viewer)->postJson("/testimonies/{$video->id}/reactions", ['type' => 'like'], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJsonPath('reacted', true)->assertJsonPath('like_count', 1);
        $this->actingAs($this->viewer)->postJson("/testimonies/{$video->id}/reactions", ['type' => 'like'], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertJsonPath('reacted', false)->assertJsonPath('like_count', 0);
    }

    // ─── Pages restylées (mêmes URL) ─────────────────────────────────────

    public function test_home_shows_featured_and_shorts_shelves(): void
    {
        $this->testimony('Grande histoire', ['duration_sec' => 600, 'is_featured' => true]);
        $this->testimony('Petit short', ['duration_sec' => 30]);

        $this->get('/')->assertOk()
            ->assertSee('À la une')
            ->assertSee('Shorts')
            ->assertSee('Petit short')
            ->assertSee(route('testimonies.show', Testimony::where('title', 'Grande histoire')->value('id')), false);

        // Avec un filtre : seulement le fil.
        $this->get('/?type=text')->assertOk()->assertDontSee('home-shorts', false);
    }

    public function test_testimony_page_uses_the_watch_layout(): void
    {
        $video = $this->testimony('Vidéo principale');
        $other = $this->testimony('Suggestion');

        $this->actingAs($this->viewer)->get("/testimonies/{$video->id}")->assertOk()
            ->assertSee('<video', false)
            ->assertSee('À regarder également')
            ->assertSee(route('testimonies.show', $other->id), false)
            ->assertSee('data-comment-form', false)
            ->assertSee('report-modal', false);
    }

    public function test_explore_and_profile_render_new_cards(): void
    {
        $this->testimony('Carte explorée');

        $this->get('/explore?type=video')->assertOk()->assertSee('Carte explorée')->assertSee('data-preview-frame', false);
        $this->get("/profiles/{$this->author->id}")->assertOk()->assertSee('Marie Auteur')->assertSee('Carte explorée');
    }
}
