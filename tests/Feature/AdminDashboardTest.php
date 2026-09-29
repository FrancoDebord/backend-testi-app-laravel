<?php

namespace Tests\Feature;

use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tableau de bord de l'administration (charte ARISE & SHINE Krea). docs/interface.md */
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, string $role = 'utilisateur'): User
    {
        return User::create([
            'display_name' => $name, 'email' => str()->slug($name) . '@example.com', 'password' => 'secret123',
            'role' => $role, 'status' => 'active',
        ]);
    }

    public function test_dashboard_shows_key_figures_activity_moderation_and_lists(): void
    {
        $admin = $this->user('Admin Krea', 'administrateur');
        $author = $this->user('Marie Koffi');
        Testimony::create([
            'user_id' => $author->id, 'title' => 'Guéri du paludisme', 'type' => 'text', 'category_slug' => 'autre',
            'body_text' => 'x', 'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(), 'views_count' => 1200,
        ]);
        Testimony::create([
            'user_id' => $author->id, 'title' => 'Ma délivrance', 'type' => 'audio', 'category_slug' => 'autre',
            'visibility' => 'public', 'status' => 'pending',
        ]);

        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertSee('Bonjour Admin Krea')
            ->assertSee('Actions rapides')
            ->assertSee('Activité des 7 derniers jours')
            ->assertSee('Modération rapide')->assertSee('Ma délivrance')->assertSee('À vérifier')
            ->assertSee('Les plus regardés')->assertSee('1 200 vues')
            ->assertSee('Derniers utilisateurs')->assertSee('Marie Koffi')
            ->assertSee('Inscrit il y a')                    // dates relatives en français
            ->assertSee('Catégories populaires');
    }

    public function test_dashboard_is_reserved_to_administrators(): void
    {
        $this->actingAs($this->user('Paul'))->get('/admin')->assertForbidden();
    }
}
