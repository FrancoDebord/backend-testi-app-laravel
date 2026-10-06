<?php

namespace Tests\Feature;

use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Accueil selon la maquette « Témoignages de Gloire ». docs/fonctionnalites/accueil.md */
class HomePageTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, string $role = 'utilisateur'): User
    {
        return User::create([
            'display_name' => $name, 'email' => str()->slug($name) . '@example.com', 'password' => 'secret123',
            'role' => $role, 'status' => 'active',
        ]);
    }

    private function testimony(User $author, string $title, string $status = 'approved'): Testimony
    {
        return Testimony::create([
            'user_id' => $author->id, 'title' => $title, 'type' => 'text', 'category_slug' => 'autre',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => $status,
            'approved_at' => $status === 'approved' ? now() : null, 'views_count' => 250,
        ]);
    }

    public function test_visitor_sees_the_public_blocks_only(): void
    {
        $this->testimony($this->user('Marie Koffi'), 'Guéri du paludisme');

        $this->get('/')->assertOk()
            ->assertSee('Témoignages de Gloire')
            ->assertSee('Témoignages publiés')->assertSee('Utilisateurs actifs')->assertSee('Vues totales')
            ->assertSee('Actions rapides')->assertSee('Se connecter')->assertSee('Créer mon compte')
            ->assertSee('Témoignages récents')->assertSee('Guéri du paludisme')
            ->assertSee('Catégories populaires')->assertSee('Statistiques globales')->assertSee('Les plus populaires')
            ->assertDontSee('Modération rapide')->assertDontSee('Gestion des contenus')->assertDontSee('Mes témoignages');
    }

    public function test_member_sees_their_own_testimonies(): void
    {
        $me = $this->user('Samuel');
        $this->testimony($me, 'Mon témoignage en attente', 'pending');

        $this->actingAs($me)->get('/')->assertOk()
            ->assertSee('Ajouter un témoignage')
            ->assertSee('Mes témoignages')->assertSee('Mon témoignage en attente')
            ->assertDontSee('Modération rapide')->assertDontSee('Gestion des contenus');
    }

    public function test_moderator_sees_quick_moderation_and_content_management_by_status(): void
    {
        $author = $this->user('Auteur');
        $this->testimony($author, 'Publié ce matin');
        $pending = $this->testimony($author, 'À relire vite', 'pending');

        $this->actingAs($this->user('Modo', 'moderateur'))->get('/')->assertOk()
            ->assertSee('Modération rapide')->assertSee('À vérifier')
            ->assertSee(route('moderation.show', $pending->id), false)
            ->assertSee('Gestion des contenus')->assertSee('En attente (1)')
            ->assertSee('Voir la file de modération');

        $this->get('/?gestion=pending')->assertOk()
            ->assertSeeInOrder(['Gestion des contenus', 'À relire vite'])
            ->assertDontSee('Publié ce matin</span></span>', false);
    }

    public function test_filters_show_only_the_list(): void
    {
        $this->testimony($this->user('Marie'), 'Un texte');

        $this->get('/?type=text')->assertOk()
            ->assertSee('Résultats')->assertSee('Un texte')
            ->assertDontSee('Actions rapides')->assertDontSee('Statistiques globales');
    }
}
