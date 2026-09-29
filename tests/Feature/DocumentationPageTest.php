<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentationPageTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'display_name' => ucfirst($role), 'email' => "{$role}@example.com", 'password' => 'secret123',
            'role' => $role, 'status' => 'active',
        ]);
    }

    public function test_only_administrators_can_read_the_documentation(): void
    {
        $this->get('/admin/documentation')->assertRedirect('/login');
        $this->actingAs($this->user('utilisateur'))->get('/admin/documentation')->assertForbidden();
        $this->actingAs($this->user('moderateur'))->get('/admin/documentation')->assertForbidden();
    }

    public function test_index_is_rendered_as_html_with_navigation_and_rewritten_links(): void
    {
        $response = $this->actingAs($this->user('administrateur'))->get('/admin/documentation')->assertOk();

        $response->assertSee('TestiApp — Documentation du serveur', false);
        $response->assertSee('<div class="doc-table"><table>', false);           // tableaux Markdown
        $response->assertSee('id="4-déploiement"', false);                        // ancres des titres
        $response->assertSee(route('admin.documentation', ['page' => 'interface']), false); // lien interne réécrit
        $response->assertSee('Journal des modifications');                        // sommaire
    }

    public function test_feature_pages_and_relative_links_work(): void
    {
        $response = $this->actingAs($this->user('administrateur'))
            ->get('/admin/documentation/fonctionnalites/mise-en-forme-temoignages')->assertOk();

        $response->assertSee('Mise en forme des témoignages');
        // « ../README.md#4-déploiement » pointe vers l'accueil de la documentation.
        $response->assertSee('href="' . route('admin.documentation') . '#4-d', false);
    }

    public function test_unknown_or_outside_paths_are_refused(): void
    {
        $admin = $this->user('administrateur');

        $this->actingAs($admin)->get('/admin/documentation/inexistante')->assertNotFound();
        $this->actingAs($admin)->get('/admin/documentation/../.env')->assertNotFound();
        $this->actingAs($admin)->get('/admin/documentation/..%2F..%2F.env')->assertNotFound();
    }
}
