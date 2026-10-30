<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Testimony;
use App\Models\TestimonyPlaylist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Listes de témoignages (comme les playlists YouTube) : site et API, lecture enchaînée de la liste,
 * témoignage publié sans liste, icône « Retirer ma dernière prière ».
 * docs/fonctionnalites/listes-de-temoignages.md
 */
class TestimonyPlaylistsTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = $this->user('Ama');
        $this->other  = $this->user('Kofi');
        Category::firstOrCreate(['slug' => 'autre'], ['name' => 'Autre']);
    }

    private function user(string $name): User
    {
        return User::create([
            'display_name' => $name, 'email' => str()->slug($name) . '@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active',
        ]);
    }

    private function testimony(string $title, array $attrs = []): Testimony
    {
        return Testimony::create(array_merge([
            'user_id' => $this->author->id, 'title' => $title, 'type' => 'video', 'category_slug' => 'autre',
            'media_url' => 'https://cdn.example.com/' . str()->slug($title) . '.mp4',
            'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ], $attrs));
    }

    public function test_site_creer_une_liste_ajouter_ranger_et_lire_a_la_suite(): void
    {
        [$one, $two, $three] = [$this->testimony('Partie 1'), $this->testimony('Partie 2'), $this->testimony('Partie 3')];

        $this->actingAs($this->author)->post(route('playlists.store'), ['title' => 'Guérison de ma mère'])->assertRedirect();
        $playlist = TestimonyPlaylist::firstOrFail();
        foreach ([$one, $two, $three] as $t) {
            $this->post(route('playlists.items.store', $playlist->id), ['testimony_id' => $t->id])->assertSessionHasNoErrors();
        }
        // Déjà dans la liste : sans effet.
        $this->post(route('playlists.items.store', $playlist->id), ['testimony_id' => $one->id]);
        $this->assertSame([$one->id, $two->id, $three->id], $playlist->testimonies()->pluck('testimonies.id')->all());

        // Monter la partie 3 d'une place.
        $this->post(route('playlists.items.move', [$playlist->id, $three->id]), ['direction' => 'up']);
        $this->assertSame([$one->id, $three->id, $two->id], $playlist->testimonies()->pluck('testimonies.id')->all());

        // Page de lecture, même sans ?list= : panneau de la liste, suivant de la liste en lecture auto.
        $next = route('testimonies.show', ['id' => $three->id, 'list' => $playlist->id]);
        $this->get(route('testimonies.show', $one->id))->assertOk()
            ->assertSee('data-playlist-panel', false)
            ->assertSee('data-up-next-mode="playlist"', false)
            ->assertSee(e(json_encode([['id' => $three->id, 'url' => $next, 'title' => 'Partie 3']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), false)
            ->assertSee(__('playlists.panel.autoplay'));

        // Page publique de la liste, pour une autre personne ; dernier de la liste : retour aux recommandations.
        $this->actingAs($this->other)->get(route('playlists.show', $playlist->id))->assertOk()->assertSee('Partie 2')->assertDontSee(__('playlists.show.add_title'));
        $this->get(route('testimonies.show', ['id' => $two->id, 'list' => $playlist->id]))->assertOk()->assertDontSee('data-up-next-mode="playlist"', false);
    }

    public function test_seuls_l_auteur_et_ses_propres_temoignages(): void
    {
        $playlist = TestimonyPlaylist::create(['user_id' => $this->author->id, 'title' => 'Ma liste']);
        $foreign  = $this->testimony('Autre auteur', ['user_id' => $this->other->id]);
        $mine     = $this->testimony('Le mien');

        $this->actingAs($this->author)->post(route('playlists.items.store', $playlist->id), ['testimony_id' => $foreign->id])->assertForbidden();
        $this->actingAs($this->other)->post(route('playlists.items.store', $playlist->id), ['testimony_id' => $mine->id])->assertForbidden();
        $this->actingAs($this->other)->delete(route('playlists.destroy', $playlist->id))->assertForbidden();
        $this->assertSame(0, $playlist->testimonies()->count());
    }

    public function test_publication_avec_ou_sans_liste(): void
    {
        $base = ['type' => 'text', 'category' => 'autre', 'consent_given' => '1', 'body_text' => 'Merci Seigneur'];
        $this->actingAs($this->author);

        // Sans liste : comme avant.
        $this->post(route('testimonies.store'), [...$base, 'title' => 'Seul'])->assertSessionHasNoErrors();
        $this->assertSame(0, TestimonyPlaylist::count());

        // Nouvelle liste créée à la publication, puis liste existante.
        $this->post(route('testimonies.store'), [...$base, 'title' => 'Épisode 1', 'playlist_title' => 'Ma délivrance'])->assertSessionHasNoErrors();
        $playlist = TestimonyPlaylist::firstOrFail();
        $this->post(route('testimonies.store'), [...$base, 'title' => 'Épisode 2', 'playlist_id' => $playlist->id])->assertSessionHasNoErrors();
        $this->assertSame(['Épisode 1', 'Épisode 2'], $playlist->testimonies()->pluck('title')->all());

        // Liste d'un autre compte : refusée avant toute création.
        $foreign = TestimonyPlaylist::create(['user_id' => $this->other->id, 'title' => 'Pas la mienne']);
        $count = Testimony::count();
        $this->post(route('testimonies.store'), [...$base, 'title' => 'Refusé', 'playlist_id' => $foreign->id])->assertSessionHasErrors('playlist_id');
        $this->assertSame($count, Testimony::count());

        // Carnet privé : jamais mis dans une liste.
        $this->post(route('testimonies.store'), [...$base, 'title' => 'Privé', 'visibility' => 'private', 'playlist_id' => $playlist->id])->assertSessionHasNoErrors();
        $this->assertSame(2, $playlist->testimonies()->count());
    }

    public function test_api_liste_et_contexte_de_lecture(): void
    {
        $one = $this->testimony('Partie 1');
        $two = $this->testimony('Partie 2');
        $pending = $this->testimony('En attente', ['status' => 'pending', 'approved_at' => null]);

        Sanctum::actingAs($this->author);
        $id = $this->postJson('/api/v1/playlists', ['title' => 'Série', 'testimonyIds' => [$two->id, $one->id, $pending->id]])
            ->assertCreated()->assertJsonPath('data.count', 3)->json('data.id');
        $this->putJson("/api/v1/playlists/{$id}", ['testimonyIds' => [$one->id, $two->id]])->assertOk()
            ->assertJsonPath('data.items.0.id', $one->id)->assertJsonPath('data.items.1.id', $two->id);
        $this->getJson('/api/v1/playlists/mine')->assertOk()->assertJsonPath('data.0.count', 3);

        // Une autre personne ne voit pas le témoignage en attente.
        Sanctum::actingAs($this->other);
        $this->getJson("/api/v1/playlists/{$id}")->assertOk()->assertJsonPath('data.count', 2)->assertJsonPath('data.isMine', false);
        $this->getJson("/api/v1/users/{$this->author->id}/playlists")->assertOk()->assertJsonPath('data.0.count', 2);
        $this->getJson("/api/v1/testimonies/{$one->id}?list={$id}")->assertOk()
            ->assertJsonPath('data.playlist.id', $id)
            ->assertJsonPath('data.playlist.position', 1)
            ->assertJsonPath('data.playlist.count', 2)
            ->assertJsonPath('data.playlist.nextId', $two->id)
            ->assertJsonPath('data.playlist.autoplay', true);
        $this->postJson("/api/v1/playlists/{$id}/testimonies", ['testimonyId' => $one->id])->assertForbidden();

        // Hors liste : playlist null ; publication par l'API dans une nouvelle liste.
        $alone = $this->testimony('Seul', ['user_id' => $this->other->id]);
        $this->getJson("/api/v1/testimonies/{$alone->id}")->assertOk()->assertJsonPath('data.playlist', null);

        // Lien de liste partagé ouvert dans l'application (iOS : apple-app-site-association).
        $this->assertContains('/listes/*', config('applinks.paths'));
        $this->postJson('/api/v1/testimonies', ['title' => 'Nouveau', 'type' => 'text', 'category' => 'autre', 'body_text' => 'Gloire', 'playlist_title' => 'Ma série'])->assertCreated();
        $this->assertSame(['Nouveau'], TestimonyPlaylist::where('user_id', $this->other->id)->firstOrFail()->testimonies()->pluck('title')->all());
    }

    public function test_retirer_ma_derniere_priere_est_une_icone(): void
    {
        $testimony = $this->testimony('Prière');
        app(\App\Services\TestimonyReactions::class)->set($this->other, $testimony, \App\Enums\ReactionType::Pray);

        $this->actingAs($this->other)->get(route('testimonies.show', $testimony->id))->assertOk()
            ->assertSee('data-pray-remove-last title="' . __('reactions.prayer_times.remove_last') . '"', false)
            ->assertSee('fa-rotate-left', false)
            ->assertDontSee('>' . __('reactions.prayer_times.remove_last') . '<', false);
    }
}
