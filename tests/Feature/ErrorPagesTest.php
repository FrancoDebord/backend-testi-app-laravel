<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Pages d'erreur du site (resources/views/errors) et erreurs JSON de l'API.
 * Voir docs/fonctionnalites/pages-erreur.md
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);

        Route::middleware('web')->group(function () {
            Route::get('/_test/boom', fn () => throw new \RuntimeException('SQLSTATE secret /var/www'));
            Route::get('/_test/forbidden', fn () => abort(403, 'Réservé aux modérateurs.'));
            Route::get('/_test/model', fn () => throw (new ModelNotFoundException())->setModel(User::class, [999]));
            Route::get('/_test/throttled', fn () => abort(429));
        });
        Route::middleware('api')->prefix('api')->group(function () {
            Route::get('/_test/boom', fn () => throw new \RuntimeException('SQLSTATE secret /var/www'));
            Route::get('/_test/forbidden', fn () => abort(403, 'Réservé aux modérateurs.'));
            Route::get('/_test/model', fn () => throw (new ModelNotFoundException())->setModel(User::class, [999]));
        });
    }

    public function test_unknown_page_shows_branded_404_with_search_and_links(): void
    {
        $this->get('/cette-page-n-existe-pas')
            ->assertNotFound()
            ->assertSee('Page introuvable')
            ->assertSee('Erreur 404')
            ->assertSee(route('search'), false)
            ->assertSee(route('home'), false)
            ->assertSee(route('explore'), false)
            ->assertDontSee('The route');
    }

    public function test_404_follows_browser_language(): void
    {
        $this->get('/missing-page', ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertNotFound()
            ->assertSee('Page not found');
    }

    public function test_missing_model_shows_404_without_technical_message(): void
    {
        $this->get('/_test/model')
            ->assertNotFound()
            ->assertSee('Page introuvable')
            ->assertDontSee('No query results');
    }

    public function test_server_error_hides_internal_message(): void
    {
        $this->get('/_test/boom')
            ->assertStatus(500)
            ->assertSee('Erreur du serveur')
            ->assertDontSee('SQLSTATE');
    }

    public function test_forbidden_page_shows_application_message_and_login_for_guests(): void
    {
        $this->get('/_test/forbidden')
            ->assertForbidden()
            ->assertSee('Accès refusé')
            ->assertSee('Réservé aux modérateurs.')
            ->assertSee(route('login'), false);
    }

    public function test_too_many_requests_page(): void
    {
        $this->get('/_test/throttled')->assertStatus(429)->assertSee('Trop de requêtes');
    }

    public function test_api_unknown_route_returns_json_even_without_accept_header(): void
    {
        $this->get('/api/route-inconnue')
            ->assertNotFound()
            ->assertExactJson(['success' => false, 'data' => null, 'message' => 'Ressource introuvable', 'errors' => null]);
    }

    public function test_api_errors_use_the_common_format(): void
    {
        $this->getJson('/api/_test/model')->assertNotFound()->assertJson(['success' => false, 'message' => 'Ressource introuvable']);
        $this->getJson('/api/_test/forbidden')->assertForbidden()->assertJson(['success' => false, 'message' => 'Réservé aux modérateurs.']);
        $this->getJson('/api/_test/boom', ['Accept-Language' => 'en'])
            ->assertStatus(500)
            ->assertJson(['success' => false, 'message' => 'Server error'])
            ->assertJsonMissing(['message' => 'SQLSTATE secret /var/www']);
    }
}
