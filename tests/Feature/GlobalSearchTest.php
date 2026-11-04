<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\PrayerRequest;
use App\Models\PrayerSession;
use App\Models\Testimony;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recherche globale du site (/recherche) : témoignages, comptes, requêtes de prière, événements, sessions de prière,
 * un onglet par rubrique, mêmes règles de visibilité que les listes. Voir docs/fonctionnalites/recherche.md
 */
class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['name' => 'Guérison', 'slug' => 'guerison', 'is_active' => true, 'display_order' => 1]);
        $this->author = $this->user('Marie Moisson');
    }

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => $name, 'email' => str($name)->slug() . '@example.com',
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    private function testimony(string $title, array $attrs = []): Testimony
    {
        return Testimony::create(array_merge([
            'user_id' => $this->author->id, 'title' => $title, 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ], $attrs));
    }

    public function test_header_bar_searches_every_resource_with_one_tab_each(): void
    {
        $this->testimony('Moisson de guérisons');
        $this->testimony('Moisson en attente', ['status' => 'pending']);
        $this->user('Église de la Moisson', ['account_type' => 'organization', 'organization_name' => 'Église de la Moisson', 'organization_type' => 'church']);
        $private = $this->user('Paul Moisson');
        UserSetting::create(['user_id' => $private->id, 'is_private_account' => true]);
        PrayerRequest::create(['user_id' => $this->author->id, 'body' => 'Priez pour la moisson', 'visibility' => 'public', 'status' => 'open']);
        PrayerRequest::create(['user_id' => $this->author->id, 'body' => 'Moisson pour mes abonnés', 'visibility' => 'followers', 'status' => 'open']);
        Event::create(['organizer_id' => $this->author->id, 'title' => 'Croisade de la moisson', 'type' => 'crusade',
            'status' => 'published', 'starts_at' => now()->addDays(10), 'ends_at' => now()->addDays(12)]);
        PrayerSession::create(['host_id' => $this->author->id, 'title' => 'Veillée de la moisson', 'starts_at' => now()->addDay(),
            'duration_minutes' => 60, 'ends_at' => now()->addDay()->addHour(), 'visibility' => 'public']);

        // Barre de l'en-tête : envoie vers /recherche.
        $this->get('/')->assertOk()->assertSee('action="' . route('search') . '"', false);

        $page = $this->get('/recherche?q=moisson')->assertOk()
            ->assertSee('Résultats pour « moisson »')
            ->assertViewHas('counts', ['temoignages' => 1, 'comptes' => 2, 'prieres' => 1, 'evenements' => 1, 'sessions' => 1])
            ->assertViewHas('tab', 'temoignages') // première rubrique avec des résultats
            ->assertSee('Moisson de guérisons')->assertDontSee('Moisson en attente');
        foreach (['Témoignages', 'Comptes', 'Requêtes de prière', 'Événements', 'Sessions de prière'] as $label) {
            $page->assertSee($label);
        }

        $this->get('/recherche?q=moisson&onglet=comptes')->assertOk()
            ->assertSee('Église de la Moisson')->assertSee('Marie Moisson')->assertDontSee('Paul Moisson');
        $this->get('/recherche?q=moisson&onglet=prieres')->assertOk()
            ->assertSee('Priez pour la moisson')->assertDontSee('Moisson pour mes abonnés');
        $this->get('/recherche?q=moisson&onglet=evenements')->assertOk()->assertSee('Croisade de la moisson');
        $this->get('/recherche?q=moisson&onglet=sessions')->assertOk()->assertSee('Veillée de la moisson');
    }

    public function test_first_tab_with_results_opens_and_empty_or_short_queries_are_handled(): void
    {
        PrayerSession::create(['host_id' => $this->author->id, 'title' => 'Veillée de Pentecôte', 'starts_at' => now()->addDay(),
            'duration_minutes' => 60, 'ends_at' => now()->addDay()->addHour(), 'visibility' => 'public']);

        $this->get('/recherche?q=pentec')->assertOk()->assertViewHas('tab', 'sessions')->assertSee('Veillée de Pentecôte');
        $this->get('/recherche?q=pentec&onglet=temoignages')->assertOk()->assertSee('Aucun résultat dans cette rubrique.');
        $this->get('/recherche')->assertOk()->assertSee('Que cherchez-vous ?');
        $this->get('/recherche?q=a')->assertOk()->assertSee('Saisissez au moins 2 caractères.');
        // Caractères spéciaux de LIKE traités comme du texte.
        $this->get('/recherche?q=%25%25')->assertOk()->assertViewHas('counts', fn ($c) => array_sum($c) === 0);
    }
}
