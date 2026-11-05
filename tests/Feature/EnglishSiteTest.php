<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Site en anglais : aucune phrase française codée en dur dans le texte visible des pages principales.
 * Voir docs/fonctionnalites/langues.md
 */
class EnglishSiteTest extends TestCase
{
    use RefreshDatabase;

    /** Mots français fréquents dans l'interface (jamais dans l'anglais du site). */
    private const FRENCH = [
        'Vous ', 'vous ', 'Votre ', 'votre ', 'Aucun', 'Rechercher', 'Enregistrer', 'Supprimer', 'Modifier',
        'Annuler', 'Publier', 'Retour', 'Connectez-vous', 'Témoignage', 'témoignage', 'Prière', 'prière',
        'Requête', 'requête', 'Paramètres', 'Accueil', 'Événement', 'Tous les', 'Toutes les', 'Mes ', ' à ', ' et ',
    ];

    private function visibleText(string $html): string
    {
        $body = preg_replace('#^.*?<body[^>]*>|<script\b.*?</script>|<style\b.*?</style>|<template\b.*?</template>#si', ' ', $html);
        // Attributs lus par les lecteurs d'écran et infobulles : à traduire aussi.
        preg_match_all('/\b(?:aria-label|title|placeholder|alt)="([^"]*)"/', $body, $attributes);

        return html_entity_decode(strip_tags($body) . ' ' . implode(' ', $attributes[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function assertEnglish(string $url, ?User $user = null): void
    {
        $response = ($user ? $this->actingAs($user) : $this)->get($url);
        $this->assertSame(200, $response->getStatusCode(), "Statut HTTP sur {$url}");
        $text = $this->visibleText($response->getContent());

        $found = array_values(array_filter(self::FRENCH, fn ($word) => str_contains($text, $word)));
        $context = array_map(function ($word) use ($text) {
            $pos = strpos($text, $word);
            return '« ' . trim(preg_replace('/\s+/', ' ', substr($text, max(0, $pos - 50), 110))) . ' »';
        }, $found);

        $this->assertSame([], $found, "Français sur {$url} : " . implode(' | ', $context));
    }

    public function test_main_pages_have_no_french_for_an_english_member(): void
    {
        $member = User::create(['display_name' => 'Jane Doe', 'email' => 'jane@example.com', 'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active', 'locale' => 'en']);
        \App\Models\DailyVerse::create([
            'verse_text' => 'For God so loved the world', 'reference' => 'John 3:16',
            'scheduled_date' => \App\Services\VerseDesignOfTheDay::todayDate(), 'is_active' => true,
        ]);

        foreach ([
            '/', '/explore', '/videos', '/evenements', '/lives', '/priere/requetes', '/priere/sessions',
            '/verset-du-jour', '/verset-du-jour/historique', '/communaute', '/notifications', '/carnet', '/carnet/paroles',
            '/carnet/paroles/nouvelle', '/mon-fil', '/profile/settings', '/profile/verification', '/profile/saved', '/profile/edit',
            '/profile/abonnements', '/profile/abonnes', '/recherche?q=paix', '/evenements/passes',
            '/priere/requetes/nouvelle', '/priere/sessions/nouvelle',
        ] as $url) {
            $this->assertEnglish($url, $member);
        }
    }

    public function test_content_pages_have_no_french_for_an_english_member(): void
    {
        $member = User::create(['display_name' => 'Jane Doe', 'email' => 'jane@example.com', 'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active', 'locale' => 'en']);
        $author = User::create(['display_name' => 'John Smith', 'email' => 'john@example.com', 'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active']);
        $testimony = \App\Models\Testimony::create([
            'user_id' => $author->id, 'title' => 'Healed', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'God healed me.', 'visibility' => 'public', 'status' => 'approved',
        ]);
        $request = \App\Models\PrayerRequest::create(['user_id' => $author->id, 'body' => 'Please pray for my family.', 'visibility' => 'public', 'status' => 'open']);
        $session = \App\Models\PrayerSession::create(['host_id' => $author->id, 'title' => 'Evening prayer', 'starts_at' => now()->addDay(),
            'duration_minutes' => 60, 'ends_at' => now()->addDay()->addHour(), 'visibility' => 'public']);

        foreach (["/testimonies/{$testimony->id}", "/priere/requetes/{$request->id}", "/priere/sessions/{$session->id}", "/profiles/{$author->id}"] as $url) {
            $this->assertEnglish($url, $member);
        }
    }

    public function test_admin_pages_have_no_french_for_an_english_admin(): void
    {
        $admin = User::create(['display_name' => 'Admin Doe', 'email' => 'admin@example.com', 'password' => 'secret123', 'role' => 'administrateur', 'status' => 'active', 'locale' => 'en']);

        foreach (['/admin', '/admin/users', '/admin/categories', '/admin/verifications', '/admin/content', '/admin/settings', '/admin/types-evenements', '/admin/demandes-suppression', '/priere/moderation', '/evenements/moderation'] as $url) {
            $this->assertEnglish($url, $admin);
        }
    }

    public function test_guest_pages_follow_the_browser_language(): void
    {
        foreach (['/', '/login', '/register', '/explore', '/verset-du-jour', '/page-inconnue-xyz'] as $url) {
            $response = $this->get($url, ['Accept-Language' => 'en-US,en;q=0.9']);
            $text = $this->visibleText($response->getContent());
            $found = array_values(array_filter(self::FRENCH, fn ($word) => str_contains($text, $word)));
            $this->assertSame([], $found, "Français sur {$url} (visiteur)");
        }
    }
}
