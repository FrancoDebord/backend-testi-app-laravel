<?php

namespace Tests\Feature;

use App\Models\BibleBook;
use App\Models\BibleVerse;
use App\Support\BibleReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Liens partageables vers un passage de la Bible : /bible/{traduction}/{livre}/{chapitre}/{versets}.
 * Voir docs/fonctionnalites/liens-bible.md
 */
class BibleLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.share_url' => 'https://temoignage.as-krea.com']);

        BibleBook::create(['translation' => 'LSG', 'number' => 43, 'name' => 'Jean', 'abbreviation' => 'Jn', 'testament' => 'NT', 'chapters_count' => 21]);
        BibleBook::create(['translation' => 'LSG', 'number' => 46, 'name' => '1 Corinthiens', 'abbreviation' => '1 Co', 'testament' => 'NT', 'chapters_count' => 16]);
        BibleBook::create(['translation' => 'KJV', 'number' => 43, 'name' => 'John', 'abbreviation' => 'Jn', 'testament' => 'NT', 'chapters_count' => 21]);
        foreach ([15 => 'Comme Moïse éleva le serpent', 16 => 'Car Dieu a tant aimé le monde', 17 => 'Dieu, en effet, n\'a pas envoyé son Fils', 18 => 'Celui qui croit en lui'] as $v => $text) {
            BibleVerse::create(['translation' => 'LSG', 'book' => 43, 'chapter' => 3, 'verse' => $v, 'text' => $text]);
        }
        BibleVerse::create(['translation' => 'KJV', 'book' => 43, 'chapter' => 3, 'verse' => 16, 'text' => 'For God so loved the world']);
        BibleVerse::create(['translation' => 'LSG', 'book' => 46, 'chapter' => 13, 'verse' => 4, 'text' => 'La charité est patiente']);
    }

    public function test_les_liens_sont_construits_et_les_versets_lus(): void
    {
        $this->assertSame('/bible/LSG/jean/3/16', BibleReference::passagePath('lsg', 43, 3, [[16, 16]]));
        $this->assertSame('/bible/KJV/john/3/16-18', BibleReference::passagePath('KJV', 43, 3, [[16, 18]]));
        $this->assertSame('/bible/LSG/1-corinthiens/13', BibleReference::passagePath('LSG', 46, 13));
        $this->assertSame('https://temoignage.as-krea.com/bible/LSG/jean/3/16', BibleReference::passageUrl('LSG', 43, 3, [[16, 16]]));

        $this->assertSame([[16, 18], [20, 20]], BibleReference::parseVerses('20,17-18,16'));
        $this->assertNull(BibleReference::parseVerses('18-16'));
        $this->assertNull(BibleReference::parseVerses('a'));
        $this->assertNull(BibleReference::parseVerses(implode(',', range(1, 21, 2))));

        $this->assertSame(43, BibleReference::bookFromSegment('jean'));
        $this->assertSame(43, BibleReference::bookFromSegment('John'));
        $this->assertSame(43, BibleReference::bookFromSegment('43'));
        $this->assertSame(46, BibleReference::bookFromSegment('1-co'));
        $this->assertSame(23, BibleReference::bookFromSegment('ésaïe'));
        $this->assertNull(BibleReference::bookFromSegment('67'));
        $this->assertNull(BibleReference::bookFromSegment('inconnu'));
    }

    public function test_un_lien_ouvre_le_passage_avec_les_versets_mis_en_avant(): void
    {
        $this->get('/bible/LSG/jean/3/16-17')
            ->assertOk()
            ->assertSee('Jean 3:16-17', false)
            ->assertSee('leading-relaxed -mx-5 bg-sun-50', false)
            ->assertSee('aria-pressed="true" aria-label="Sélectionner le verset 16"', false)
            ->assertSee('aria-pressed="false" aria-label="Sélectionner le verset 18"', false)
            ->assertSee('<meta property="og:title" content="Jean 3:16-17 (LSG)">', false)
            ->assertSee('<meta property="og:url" content="https://temoignage.as-krea.com/bible/LSG/jean/3/16-17">', false)
            ->assertSee('Car Dieu a tant aimé le monde Dieu, en effet', false)
            ->assertSee('data-selection-share', false);

        // Chapitre entier : aucun verset mis en avant, barre de sélection masquée.
        $page = $this->get('/bible/LSG/jean/3')->assertOk()
            ->assertDontSee('leading-relaxed -mx-5 bg-sun-50', false)
            ->assertDontSee('aria-pressed="true" aria-label="Sélectionner', false);
        $this->assertMatchesRegularExpression('/id="verse-actions" class="[^"]*"\s+hidden/', $page->getContent());
    }

    public function test_un_ensemble_de_versets_epars(): void
    {
        $this->get('/bible/LSG/jean/3/15,17-18')
            ->assertOk()
            ->assertSee('Jean 3:15, 17-18', false)
            ->assertSee('aria-pressed="true" aria-label="Sélectionner le verset 15"', false)
            ->assertSee('aria-pressed="false" aria-label="Sélectionner le verset 16"', false)
            ->assertSee('aria-pressed="true" aria-label="Sélectionner le verset 18"', false)
            ->assertSee('<meta property="og:url" content="https://temoignage.as-krea.com/bible/LSG/jean/3/15,17-18">', false);

        // Versets contigus fusionnés, ordre rétabli.
        $this->get('/bible/LSG/jean/3/18,15,16')->assertRedirect('/bible/LSG/jean/3/15-16,18');
        // 10 plages au plus, comme l'application.
        $this->get('/bible/LSG/jean/3/' . implode(',', range(1, 19, 2)))->assertOk();
        $this->get('/bible/LSG/jean/3/' . implode(',', range(1, 21, 2)))->assertNotFound();
    }

    public function test_les_autres_ecritures_sont_redirigees_vers_le_lien_canonique(): void
    {
        $this->get('/bible/lsg/43/3/16')->assertRedirect('/bible/LSG/jean/3/16')->assertStatus(301);
        $this->get('/bible/LSG/john/3/17,16')->assertRedirect('/bible/LSG/jean/3/16-17');
        $this->get('/bible/KJV/jean/3/16')->assertRedirect('/bible/KJV/john/3/16');
        $this->get('/bible/LSG/1-corinthians/13/4')->assertRedirect('/bible/LSG/1-corinthiens/13/4');
        // Ancienne adresse du lecteur.
        $this->get('/bible?translation=LSG&book=43&chapter=3')->assertRedirect('/bible/LSG/jean/3');
        // Versets absents du chapitre : le chapitre entier.
        $this->get('/bible/LSG/jean/3/90')->assertRedirect('/bible/LSG/jean/3')->assertSessionHas('warning');
    }

    public function test_changer_de_version_garde_le_passage_ou_le_plus_proche(): void
    {
        // Sélection gardée : le script envoie le numéro du livre, le serveur donne le nom de la version.
        $this->get('/bible/KJV/43/3/16-18,20')->assertRedirect('/bible/KJV/john/3/16-18,20');
        $this->get('/bible/KJV/john/3/16-18,20')->assertOk()->assertSee('John 3:16-18, 20', false);

        // Livre absent de la version choisie : même passage en Louis Segond.
        $this->get('/bible/KJV/1-corinthians/13/4')->assertRedirect('/bible/LSG/1-corinthiens/13/4')
            ->assertSessionHas('warning', "Ce livre n'existe pas dans la version KJV : passage affiché dans la version LSG.");
        // Version absente (lien ancien ou version retirée) : Louis Segond.
        $this->get('/bible/XYZ/jean/3/16')->assertRedirect('/bible/LSG/jean/3/16')->assertSessionHas('warning');
        // Chapitre au-delà du dernier de cette version : dernier chapitre.
        $this->get('/bible/LSG/jean/22/1')->assertRedirect('/bible/LSG/jean/21')
            ->assertSessionHas('warning', 'Ce livre compte 21 chapitres dans la version LSG : dernier chapitre affiché.');
        // Versets absents dans cette version : chapitre entier, message affiché.
        $this->get('/bible/KJV/john/3/17')->assertRedirect('/bible/KJV/john/3');
        $this->followingRedirects()->get('/bible/KJV/john/3/17')->assertOk()
            ->assertSee("Ces versets n'existent pas dans ce chapitre de la version KJV");
    }

    public function test_code_de_version_avec_tiret_bas_non_declare(): void
    {
        // Version importée sous « EN_NLT », absente de config/bible.php : anglaise d'après ses noms de livres.
        BibleBook::create(['translation' => 'EN_NLT', 'number' => 1, 'name' => 'Genesis', 'abbreviation' => 'Gen', 'testament' => 'OT', 'chapters_count' => 50]);
        foreach (range(1, 8) as $v) {
            BibleVerse::create(['translation' => 'EN_NLT', 'book' => 1, 'chapter' => 1, 'verse' => $v, 'text' => "Genesis verse {$v}"]);
        }

        $this->get('/bible/EN_NLT/1/1/2,4-7')->assertRedirect('/bible/EN_NLT/genesis/1/2,4-7');
        $this->get('/bible/EN_NLT/genesis/1/2,4-7')->assertOk()->assertSee('Genesis 1:2, 4-7', false);
        $this->get('/bible/en_nlt/genese/1/2')->assertRedirect('/bible/EN_NLT/genesis/1/2');
        $this->getJson('/api/v1/bible/books?translation=EN_NLT')->assertJsonPath('data.0.slug', 'genesis');
        $this->getJson('/api/v1/bible/translations')->assertJsonFragment(['code' => 'EN_NLT', 'language' => 'en']);
    }

    public function test_un_lien_invalide_renvoie_404(): void
    {
        $this->get('/bible/LSG/inconnu/3/16')->assertNotFound();
        $this->get('/bible/LSG/jean/3/18-16')->assertNotFound();
        $this->get('/bible/LSG/genese/1')->assertNotFound();       // livre présent dans aucune version
    }

    public function test_l_api_donne_les_liens_et_le_nom_des_livres_dans_les_liens(): void
    {
        $this->getJson('/api/v1/bible/books?translation=LSG')->assertOk()
            ->assertJsonPath('data.0.slug', 'jean')
            ->assertJsonPath('data.1.slug', '1-corinthiens');
        $this->getJson('/api/v1/bible/books?translation=KJV')->assertJsonPath('data.0.slug', 'john');

        $this->getJson('/api/v1/bible/43/3/16?translation=LSG')
            ->assertJsonPath('data.shareUrl', 'https://temoignage.as-krea.com/bible/LSG/jean/3/16');
        $this->getJson('/api/v1/bible/43/3?translation=LSG')
            ->assertJsonPath('data.shareUrl', 'https://temoignage.as-krea.com/bible/LSG/jean/3');
        $this->getJson('/api/v1/bible/43/3/16/share?translation=KJV')
            ->assertJsonPath('data.shareUrl', 'https://temoignage.as-krea.com/bible/KJV/john/3/16');
    }

    public function test_les_liens_bible_s_ouvrent_dans_l_application(): void
    {
        $this->assertContains('/bible/*', config('applinks.paths'));
    }
}
