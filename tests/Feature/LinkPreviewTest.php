<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LinkPreviews;
use App\Services\Mentions;
use App\Support\Linkify;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Liens cliquables et aperçus de liens (docs/fonctionnalites/liens-et-apercus.md). */
class LinkPreviewTest extends TestCase
{
    use RefreshDatabase;

    private const PAGE = '<html><head><meta charset="utf-8"><title>Titre balise</title>'
        . '<meta property="og:title" content="Culte de louange &amp; prière">'
        . '<meta property="og:description" content="Rejoignez-nous dimanche.">'
        . '<meta property="og:image" content="/images/culte.jpg">'
        . '<meta property="og:site_name" content="Église Exemple"></head><body></body></html>';

    protected function setUp(): void
    {
        parent::setUp();
        // Pas de DNS dans les tests : *.example.org public, interne.test privé.
        LinkPreviews::$resolveUsing = fn (string $host) => match ($host) {
            'interne.test' => ['10.0.0.5'],
            default        => ['93.184.215.14'],
        };
    }

    protected function tearDown(): void
    {
        LinkPreviews::$resolveUsing = null;
        parent::tearDown();
    }

    public function test_les_adresses_deviennent_des_liens_echappes(): void
    {
        $html = (string) Linkify::html('Voir https://exemple.org/a?b=1&c=2. Et (www.site.fr/page) <script>');

        $this->assertStringContainsString('<a href="https://exemple.org/a?b=1&amp;c=2" target="_blank" rel="nofollow noopener noreferrer ugc"', $html);
        $this->assertStringContainsString('>https://exemple.org/a?b=1&amp;c=2</a>. Et (', $html);
        $this->assertStringContainsString('<a href="https://www.site.fr/page"', $html);
        $this->assertStringContainsString('>www.site.fr/page</a>)', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_pas_de_lien_pour_ce_qui_n_est_pas_une_adresse(): void
    {
        $this->assertSame('javascript:alert(1) et http://localhost', (string) Linkify::html('javascript:alert(1) et http://localhost'));
        $this->assertStringNotContainsString('<a', (string) Linkify::html('écrire à paul@www.site.fr ou wwwfoo'));
        $this->assertStringNotContainsString('<a', (string) Linkify::html('http://user:pass@site.fr'));
        $this->assertSame([], Linkify::urls('Aucune adresse ici.'));
    }

    public function test_lien_interne_dans_le_meme_onglet_et_libelle_raccourci(): void
    {
        config(['app.url' => 'https://temoignage.as-krea.com']);
        $html = (string) Linkify::html('https://temoignage.as-krea.com/testimonies/1');
        $this->assertStringNotContainsString('target="_blank"', $html);

        $long = 'https://exemple.org/' . str_repeat('a', 100);
        $html = (string) Linkify::html($long);
        $this->assertStringContainsString('href="' . $long . '"', $html);
        $this->assertStringContainsString('...</a>', $html);
    }

    public function test_mentions_et_liens_ensemble(): void
    {
        $id   = '0199aaaa-bbbb-7ccc-8ddd-eeeeffff0001';
        $html = (string) Mentions::html("Merci @[Paul](user:{$id}) https://exemple.org !");

        $this->assertStringContainsString('data-mention-link="' . $id . '">@Paul</a>', $html);
        $this->assertStringContainsString('<a href="https://exemple.org"', $html);
    }

    public function test_premiere_adresse_hors_images_du_site(): void
    {
        config(['app.url' => 'https://temoignage.as-krea.com']);
        $text = "![photo](https://temoignage.as-krea.com/storage/testimony-images/a.jpg)\nVoir [le site](https://exemple.org/x).";

        $this->assertSame('https://exemple.org/x', Linkify::firstUrl($text));
        $this->assertNull(Linkify::firstUrl(null));
    }

    public function test_apercu_open_graph_lu_et_mis_en_cache(): void
    {
        Http::fake(['exemple.org/*' => Http::response(self::PAGE, 200, ['Content-Type' => 'text/html; charset=utf-8'])]);

        $preview = LinkPreviews::get('https://exemple.org/culte');
        $this->assertSame('Culte de louange & prière', $preview['title']);
        $this->assertSame('Rejoignez-nous dimanche.', $preview['description']);
        $this->assertSame('https://exemple.org/images/culte.jpg', $preview['image']);
        $this->assertSame('Église Exemple', $preview['siteName']);
        $this->assertSame('exemple.org', $preview['host']);

        LinkPreviews::get('https://exemple.org/culte');
        Http::assertSentCount(1);
        $this->assertSame($preview, LinkPreviews::cached('https://exemple.org/culte'));
    }

    public function test_adresses_internes_jamais_lues(): void
    {
        Http::fake();

        foreach ([
            'http://127.0.0.1/admin',
            'http://169.254.169.254/latest/meta-data',
            'http://[::1]/',
            'http://interne.test/',
            'https://exemple.org:8080/',
            'ftp://exemple.org/',
            'file:///etc/passwd',
        ] as $url) {
            $this->assertNull(LinkPreviews::get($url), $url);
        }
        Http::assertNothingSent();
    }

    public function test_redirection_vers_une_adresse_interne_refusee(): void
    {
        Http::fake([
            'exemple.org/*' => Http::response('', 302, ['Location' => 'http://interne.test/secret']),
            'interne.test/*' => Http::response(self::PAGE, 200, ['Content-Type' => 'text/html']),
        ]);

        $this->assertNull(LinkPreviews::get('https://exemple.org/court'));
        Http::assertSentCount(1);
        $this->assertFalse(LinkPreviews::cached('https://exemple.org/court'));
    }

    public function test_page_sans_aperçu_ou_non_html(): void
    {
        Http::fake([
            'exemple.org/vide'  => Http::response('<html><body>rien</body></html>', 200, ['Content-Type' => 'text/html']),
            'exemple.org/x.pdf' => Http::response('%PDF', 200, ['Content-Type' => 'application/pdf']),
        ]);

        $this->assertNull(LinkPreviews::get('https://exemple.org/vide'));
        $this->assertNull(LinkPreviews::get('https://exemple.org/x.pdf'));
    }

    public function test_image_http_ignoree(): void
    {
        Http::fake(['exemple.org/*' => Http::response(str_replace('/images/culte.jpg', 'http://cdn.exemple.org/a.jpg', self::PAGE), 200, ['Content-Type' => 'text/html'])]);

        $preview = LinkPreviews::get('https://exemple.org/culte');
        $this->assertNull($preview['image']);
        $this->assertSame('Culte de louange & prière', $preview['title']);
    }

    public function test_youtube_miniature_et_titre_oembed(): void
    {
        Http::fake(['www.youtube.com/oembed*' => Http::response(['title' => 'Louange live', 'author_name' => 'Krea'])]);

        $preview = LinkPreviews::get('https://youtu.be/dQw4w9WgXcQ');
        $this->assertSame('Louange live', $preview['title']);
        $this->assertSame('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $preview['image']);
        $this->assertSame('YouTube · Krea', $preview['siteName']);
    }

    public function test_route_du_site_et_api(): void
    {
        Http::fake(['exemple.org/*' => Http::response(self::PAGE, 200, ['Content-Type' => 'text/html'])]);

        $this->getJson(route('link-preview', ['url' => 'https://exemple.org/culte']))
            ->assertOk()->assertJsonPath('data.title', 'Culte de louange & prière');
        $this->getJson(route('link-preview', ['url' => 'http://127.0.0.1/']))
            ->assertOk()->assertJsonPath('data', null);
        $this->getJson(route('link-preview'))->assertStatus(422);

        $this->getJson('/api/v1/link-preview?url=' . urlencode('https://exemple.org/culte'))->assertUnauthorized();
        $user = User::create([
            'display_name' => 'Paul', 'email' => 'paul@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active', 'phone' => '+22990000001',
        ]);
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/link-preview?url=' . urlencode('https://exemple.org/culte'))
            ->assertOk()->assertJsonPath('data.image', 'https://exemple.org/images/culte.jpg');
    }

    public function test_composant_rendu_depuis_le_cache_ou_a_charger(): void
    {
        $this->assertSame('', trim(Blade::render('<x-link-preview :text="$t" />', ['t' => 'Pas de lien'])));

        $pending = Blade::render('<x-link-preview :text="$t" />', ['t' => 'Voir https://exemple.org/culte']);
        $this->assertStringContainsString('data-link-preview-url="https://exemple.org/culte"', $pending);
        $this->assertStringContainsString(' hidden', $pending);

        Http::fake(['exemple.org/*' => Http::response(self::PAGE, 200, ['Content-Type' => 'text/html'])]);
        LinkPreviews::get('https://exemple.org/culte');
        $ready = Blade::render('<x-link-preview :text="$t" />', ['t' => 'Voir https://exemple.org/culte']);
        $this->assertStringNotContainsString('data-link-preview-url', $ready);
        $this->assertStringContainsString('Culte de louange &amp; prière', $ready);
        $this->assertStringContainsString('src="https://exemple.org/images/culte.jpg"', $ready);

        // Page sans aperçu : rien.
        Http::fake(['autre.example.org/*' => Http::response('<p>x</p>', 200, ['Content-Type' => 'text/html'])]);
        LinkPreviews::get('https://autre.example.org/');
        $this->assertSame('', trim(Blade::render('<x-link-preview url="https://autre.example.org/" />')));
    }
}
