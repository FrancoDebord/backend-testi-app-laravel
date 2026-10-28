<?php

namespace Tests\Feature;

use App\Models\BibleBook;
use App\Models\BibleVerse;
use App\Models\DailyVerse;
use App\Models\User;
use App\Services\VerseDesignOfTheDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verset du jour dans la langue de l'application ou du site, et langue par défaut = langue du système
 * (Accept-Language). Voir docs/fonctionnalites/verset-du-jour.md, docs/fonctionnalites/langues.md
 */
class DailyVerseLanguageTest extends TestCase
{
    use RefreshDatabase;

    private DailyVerse $verse;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([['LSG', 'Jean'], ['KJV', 'John']] as [$code, $name]) {
            BibleBook::create(['translation' => $code, 'number' => 43, 'name' => $name, 'abbreviation' => 'Jn', 'testament' => 'NT', 'chapters_count' => 21]);
        }
        BibleVerse::create(['translation' => 'KJV', 'book' => 43, 'chapter' => 3, 'verse' => 16, 'text' => 'For God so loved the world']);
        $this->verse = DailyVerse::create([
            'verse_text' => 'Car Dieu a tant aimé le monde', 'reference' => 'Jean 3:16',
            'scheduled_date' => VerseDesignOfTheDay::todayDate(), 'is_active' => true,
        ]);
    }

    public function test_l_api_renvoie_le_verset_dans_la_langue_de_l_application(): void
    {
        // Français (langue choisie, ou sans en-tête) : texte du jour.
        $this->getJson('/api/v1/daily-verse', ['Accept-Language' => 'fr-BJ,fr;q=0.9'])->assertOk()
            ->assertJsonPath('data.verseText', 'Car Dieu a tant aimé le monde')
            ->assertJsonPath('data.reference', 'Jean 3:16')
            ->assertJsonPath('data.language', 'fr')
            ->assertJsonPath('data.textTranslation', null);

        // Anglais (langue choisie ou langue du système envoyée par l'application) : traduction anglaise.
        $this->getJson('/api/v1/daily-verse', ['Accept-Language' => 'en-US,en;q=0.9'])->assertOk()
            ->assertJsonPath('data.verseText', 'For God so loved the world')
            ->assertJsonPath('data.reference', 'John 3:16')
            ->assertJsonPath('data.language', 'en')
            ->assertJsonPath('data.textTranslation', 'KJV')
            ->assertJsonPath('data.originalText', 'Car Dieu a tant aimé le monde')
            ->assertJsonPath('data.originalReference', 'Jean 3:16');

        $this->getJson("/api/v1/daily-verse/{$this->verse->id}", ['Accept-Language' => 'en'])->assertJsonPath('data.verseText', 'For God so loved the world');
        $this->getJson('/api/v1/daily-verse/history', ['Accept-Language' => 'en'])->assertOk()
            ->assertJsonPath('data.0.verseText', 'For God so loved the world')
            ->assertJsonPath('data.0.reference', 'John 3:16');
    }

    public function test_sans_traduction_anglaise_le_texte_du_jour_est_garde(): void
    {
        BibleVerse::where('translation', 'KJV')->delete();

        $this->getJson('/api/v1/daily-verse', ['Accept-Language' => 'en'])->assertOk()
            ->assertJsonPath('data.verseText', 'Car Dieu a tant aimé le monde')
            ->assertJsonPath('data.language', 'fr');
    }

    public function test_le_site_suit_la_langue_du_navigateur_puis_le_choix(): void
    {
        // Visiteur, navigateur en anglais : page en anglais, verset en anglais.
        $this->get('/verset-du-jour', ['Accept-Language' => 'en-GB,en;q=0.8'])->assertOk()
            ->assertSee('<html lang="en"', false)
            ->assertSee('For God so loved the world');
        // Navigateur en français : texte du jour.
        $this->get('/verset-du-jour', ['Accept-Language' => 'fr'])->assertOk()
            ->assertSee('<html lang="fr"', false)
            ->assertSee('Car Dieu a tant aimé le monde');

        // Le choix fait avec le sélecteur l'emporte sur la langue du navigateur.
        $this->withSession(['locale' => 'fr'])->get('/', ['Accept-Language' => 'en'])->assertSee('<html lang="fr"', false);
    }

    public function test_un_nouveau_compte_prend_la_langue_de_la_requete(): void
    {
        app()->setLocale('en');
        $english = User::create(['display_name' => 'Ann', 'email' => 'ann@example.com', 'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active']);
        $this->assertSame('en', $english->fresh()->locale);

        app()->setLocale('fr');
        $french = User::create(['display_name' => 'Ama', 'email' => 'ama@example.com', 'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active']);
        $this->assertSame('fr', $french->fresh()->locale);

        // Langue du compte : prioritaire sur le navigateur pour un membre connecté.
        $this->actingAs($french)->get('/', ['Accept-Language' => 'en'])->assertSee('<html lang="fr"', false);
    }
}
