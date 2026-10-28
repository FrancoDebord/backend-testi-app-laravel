<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DailyVerse;
use App\Models\Testimony;
use App\Models\ThemeSemaine;
use App\Models\User;
use App\Services\Sitemaps;
use App\Services\VerseDesignOfTheDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Référencement : pages publiques, confidentialité (404 / 410), anonymat, balises, JSON-LD, sitemaps, robots.
 * Voir docs/fonctionnalites/referencement.md
 */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    private const SHARE = 'https://temoignage.as-krea.com';

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.share_url' => self::SHARE]);
        Category::create(['name' => 'Guérison', 'slug' => 'guerison', 'is_active' => true, 'intro' => 'Dieu guérit encore aujourd\'hui.']);
        $this->author = User::create([
            'display_name' => 'Marie Adjovi', 'email' => 'marie@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active', 'avatar_url' => 'https://cdn.example.com/marie.jpg',
        ]);
    }

    private function testimony(array $attrs = []): Testimony
    {
        return Testimony::create(array_merge([
            'user_id' => $this->author->id, 'title' => 'Guérie d\'un cancer après la prière', 'type' => 'text',
            'category_slug' => 'guerison', 'body_text' => 'Les médecins avaient perdu espoir, mais Dieu a agi.',
            'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ], $attrs));
    }

    /** JSON-LD de la page : liste des objets décodés. */
    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        return collect($m[1])->map(fn ($json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR))
            ->flatMap(fn ($d) => array_is_list($d) ? $d : [$d])->all();
    }

    public function test_temoignage_publie_et_accepte_pour_le_web(): void
    {
        $t = $this->testimony(['resume' => 'Guérie après des mois de prière.']);
        $html = $this->get("/testimonies/{$t->id}")->assertOk()
            // 60 caractères au plus : « – Témoignage » retiré quand le titre est long.
            ->assertSee('<title>' . e("Guérie d'un cancer après la prière | My Testimony") . '</title>', false)
            ->assertSee('<meta name="robots" content="' . \App\Support\Seo::INDEX . '">', false)
            ->assertSee('<link rel="canonical" href="' . self::SHARE . "/testimonies/{$t->id}\">", false)
            ->assertSee('<meta name="description" content="Guérie après des mois de prière.">', false)
            ->assertSee('Guérie après des mois de prière.')
            ->getContent();

        $types = array_column($this->jsonLd($html), '@type');
        $this->assertContains('Organization', $types);
        $this->assertContains('WebSite', $types);
        $this->assertContains('Article', $types);
        $this->assertContains('BreadcrumbList', $types);
        $article = collect($this->jsonLd($html))->firstWhere('@type', 'Article');
        $this->assertSame('Marie Adjovi', $article['author']['name']);
        $this->assertLessThanOrEqual(60, mb_strlen(\App\Support\Seo::title(str_repeat('Très long titre de témoignage ', 5), 'Témoignage')));
        $this->assertSame('Guéri du paludisme – Témoignage | My Testimony', \App\Support\Seo::title('Guéri du paludisme', 'Témoignage'));
    }

    public function test_temoignage_video_bunny_videoobject(): void
    {
        config(['services.bunny_stream.cdn_host' => 'vz-test.b-cdn.net', 'services.bunny_stream.library_id' => '123']);
        $t = $this->testimony(['type' => 'video', 'bunny_video_id' => 'abc-guid', 'video_status' => 'ready', 'duration_sec' => 125, 'transcription' => 'Bonjour, je témoigne.']);

        $html = $this->get("/testimonies/{$t->id}")->assertOk()
            ->assertSee('<meta property="og:type" content="video.other">', false)
            ->getContent();
        $video = collect($this->jsonLd($html))->firstWhere('@type', 'VideoObject');
        $this->assertSame('PT2M5S', $video['duration']);
        $this->assertSame('https://vz-test.b-cdn.net/abc-guid/thumbnail.jpg', $video['thumbnailUrl'][0]);
        $this->assertStringContainsString('/embed/', $video['embedUrl']);
        $this->assertSame('Bonjour, je témoigne.', $video['transcript']);
    }

    public function test_en_attente_refuse_sans_accord_404_supprime_ou_retire_410(): void
    {
        foreach ([['status' => 'pending', 'approved_at' => null], ['status' => 'rejected'], ['publication_web' => false], ['visibility' => 'followers']] as $attrs) {
            $t = $this->testimony($attrs);
            $this->get("/testimonies/{$t->id}")->assertNotFound();
            $this->get("/videos/{$t->id}")->assertNotFound();
        }

        // Supprimé (corbeille) : 410.
        $deleted = $this->testimony();
        $deleted->delete();
        $this->get("/testimonies/{$deleted->id}")->assertStatus(410);

        // Accord retiré par l'auteur : 410 pour les visiteurs, page toujours visible pour l'auteur.
        $withdrawn = $this->testimony();
        $this->actingAs($this->author)->put(route('testimonies.web-publication', $withdrawn->id), ['publication_web' => 0, 'is_anonymous' => 0])->assertRedirect();
        $this->assertNotNull($withdrawn->fresh()->publication_web_revoked_at);
        $this->actingAs($this->author)->get("/testimonies/{$withdrawn->id}")->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        auth()->logout();
        $this->get("/testimonies/{$withdrawn->id}")->assertStatus(410);

        // L'application n'est pas concernée par l'accord pour le web.
        $this->getJson("/api/v1/testimonies/{$withdrawn->id}")->assertOk()->assertJsonPath('data.publicationWeb', false);
    }

    public function test_temoignage_anonyme_n_expose_aucun_nom(): void
    {
        $t = $this->testimony(['is_anonymous' => true]);

        $html = $this->get("/testimonies/{$t->id}")->assertOk()->assertSee('Témoignage anonyme')->getContent();
        $this->assertStringNotContainsString('Marie Adjovi', $html);
        $this->assertStringNotContainsString('marie.jpg', $html);
        $this->assertStringNotContainsString(route('profiles.show', $this->author->id), $html);
        $this->assertSame('Témoignage anonyme', collect($this->jsonLd($html))->firstWhere('@type', 'Article')['author']['name']);

        $this->get('/temoignages/categorie/guerison')->assertOk()->assertSee('Témoignage anonyme')->assertDontSee('Marie Adjovi');
        $this->assertStringNotContainsString('Marie Adjovi', app(Sitemaps::class)->xml('testimonies'));
        // L'application montre toujours l'auteur.
        $this->getJson("/api/v1/testimonies/{$t->id}")->assertOk()->assertJsonPath('data.isAnonymous', true);
    }

    public function test_page_categorie_et_theme(): void
    {
        $t = $this->testimony();
        $this->testimony(['title' => 'Pas sur le web', 'publication_web' => false]);

        $html = $this->get('/temoignages/categorie/guerison')->assertOk()
            ->assertSee('<h1 class="text-xl font-bold text-primary-600 sm:text-2xl">Témoignages de guérison</h1>', false)
            ->assertSee('Dieu guérit encore aujourd')
            ->assertSee($t->title)->assertDontSee('Pas sur le web')
            ->getContent();
        $this->assertContains('CollectionPage', array_column($this->jsonLd($html), '@type'));
        $this->get('/temoignages/categorie/inconnue')->assertNotFound();

        $theme = ThemeSemaine::create(['annee' => 2026, 'numero_semaine' => 40, 'thematique' => 'La guérison divine', 'verset_cle' => 'Exode 15:26',
            'date_debut' => now()->subDays(2)->toDateString(), 'date_fin' => now()->addDays(4)->toDateString()]);
        DailyVerse::create(['verse_text' => 'Je suis l\'Éternel, qui te guérit.', 'reference' => 'Exode 15:26', 'scheduled_date' => VerseDesignOfTheDay::todayDate(),
            'is_active' => true, 'theme_semaine_id' => $theme->id]);
        $this->assertSame('la-guerison-divine', $theme->slug);

        $this->get('/themes/la-guerison-divine')->assertOk()->assertSee('La guérison divine')->assertSee('qui te guérit')->assertSee($t->title);
        // Le témoignage de guérison montre le verset de ce thème.
        $this->get("/testimonies/{$t->id}")->assertSee('Un verset pour ce témoignage')->assertSee('qui te guérit');
        // Thème à venir : 404.
        ThemeSemaine::create(['annee' => 2030, 'numero_semaine' => 1, 'thematique' => 'Un thème futur', 'verset_cle' => 'Jean 1:1', 'date_debut' => '2030-01-01', 'date_fin' => '2030-01-05']);
        $this->get('/themes/un-theme-futur')->assertNotFound();
    }

    public function test_sitemaps_seulement_les_contenus_publies(): void
    {
        $published = $this->testimony();
        $video = $this->testimony(['title' => 'Vidéo', 'type' => 'video', 'youtube_id' => 'dQw4w9WgXcQ', 'duration_sec' => 60]);
        $pending = $this->testimony(['status' => 'pending', 'approved_at' => null]);
        $noWeb = $this->testimony(['publication_web' => false]);
        $private = $this->testimony(['visibility' => 'private', 'status' => 'draft']);
        DailyVerse::create(['verse_text' => 'Demain', 'reference' => 'Jean 3:16', 'scheduled_date' => now()->addDays(3)->toDateString(), 'is_active' => true]);

        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(self::SHARE . '/sitemap-testimonies.xml', false)->assertSee(self::SHARE . '/sitemap-videos.xml', false);

        $xml = $this->get('/sitemap-testimonies.xml')->assertOk()->getContent();
        $this->assertStringContainsString(self::SHARE . "/testimonies/{$published->id}", $xml);
        foreach ([$pending, $noWeb, $private] as $hidden) {
            $this->assertStringNotContainsString($hidden->id, $xml);
        }

        $videos = $this->get('/sitemap-videos.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<video:player_loc>https://www.youtube.com/embed/dQw4w9WgXcQ</video:player_loc>', $videos);
        $this->assertStringContainsString('<video:duration>60</video:duration>', $videos);
        $this->assertStringNotContainsString($published->id, $videos);

        $this->get('/sitemap-categories.xml')->assertSee(self::SHARE . '/temoignages/categorie/guerison', false);
        $this->assertStringNotContainsString(now()->addDays(3)->toDateString(), $this->get('/sitemap-verses.xml')->getContent());
        $this->get('/sitemap-inconnu.xml')->assertNotFound();

        // Retrait du web : le témoignage sort du sitemap aussitôt.
        $published->update(['publication_web' => false]);
        $this->assertStringNotContainsString($published->id, $this->get('/sitemap-testimonies.xml')->getContent());
        $this->artisan('sitemap:generate')->assertSuccessful();
    }

    public function test_robots_et_pages_privees_non_indexees(): void
    {
        $this->get('/robots.txt')->assertOk()
            ->assertSee('Disallow: /admin')->assertSee('Disallow: /api/')->assertSee('Disallow: /explore')
            ->assertSee('Sitemap: ' . self::SHARE . '/sitemap.xml');

        $this->get('/explore?q=guerison')->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
        $this->get(route('profiles.show', $this->author->id))->assertSee('<meta name="robots" content="noindex, follow">', false);
        $this->get('/')->assertOk()->assertSee('<meta name="robots" content="' . \App\Support\Seo::INDEX . '">', false);
    }

    public function test_publication_sur_le_site_avec_accord_et_anonymat(): void
    {
        $this->actingAs($this->author)->get(route('publish'))->assertOk()
            ->assertSee('name="publication_web" value="1"', false)->assertSee('name="is_anonymous" value="1"', false);

        $this->actingAs($this->author)->post(route('testimonies.store'), [
            'title' => 'Délivré', 'type' => 'text', 'category' => 'guerison', 'body_text' => 'Texte', 'consent_given' => '1',
            'publication_web' => '0', 'is_anonymous' => '1',
        ])->assertSessionHasNoErrors();
        $created = Testimony::where('title', 'Délivré')->first();
        $this->assertFalse($created->publication_web);
        $this->assertTrue($created->is_anonymous);

        // Application : champ absent → accord donné (comme l'accord déjà accepté) ; envoyé → respecté.
        \Laravel\Sanctum\Sanctum::actingAs($this->author);
        $this->postJson('/api/v1/testimonies', ['title' => 'Depuis l\'appli', 'type' => 'text', 'category' => 'guerison', 'body_text' => 'Texte'])
            ->assertCreated()->assertJsonPath('data.publicationWeb', true);
        $this->postJson('/api/v1/testimonies', ['title' => 'Sans le web', 'type' => 'text', 'category' => 'guerison', 'body_text' => 'Texte', 'publicationWeb' => false])
            ->assertCreated()->assertJsonPath('data.publicationWeb', false);
    }
}
