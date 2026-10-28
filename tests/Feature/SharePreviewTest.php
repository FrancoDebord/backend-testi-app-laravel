<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\LiveSession;
use App\Models\PrayerRequest;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Aperçus de partage (Open Graph, images 1200 × 630) : docs/fonctionnalites/apercu-de-partage.md
 */
class SharePreviewTest extends TestCase
{
    use RefreshDatabase;

    private const SHARE = 'https://temoignage.example.org';
    private const BUNNY_HOST = 'vz-test.b-cdn.net';
    private const BUNNY_GUID = '11111111-2222-3333-4444-555555555555';

    private string $root;
    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/share-images-' . Str::random(8));
        config([
            'share_images.root' => $this->root,
            'app.share_url' => self::SHARE,
            'app.url' => 'https://app.example.org',
            'services.bunny_stream.cdn_host' => self::BUNNY_HOST,
        ]);
        Category::create(['slug' => 'guerison', 'name' => 'Guérison', 'is_active' => true]);
        $this->author = User::create(['display_name' => 'Marie Adjovi', 'email' => 'marie@example.com', 'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    // ─── Outils ─────────────────────────────────────────────────────────────

    private function testimony(array $attrs = []): Testimony
    {
        return Testimony::create(array_merge([
            'user_id' => $this->author->id, 'category_id' => Category::first()->id, 'category_slug' => 'guerison',
            'title' => 'Guérie après trois ans', 'type' => 'text',
            'body_text' => "Le **Seigneur** m'a guérie.\nMerci @[Paul](user:0d4f5a8e-1111-4111-8111-111111111111) pour ses prières.",
            'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ], $attrs));
    }

    /** Valeurs des balises <meta property|name="…" content="…"> et du lien canonique. */
    private function meta(TestResponse $response): array
    {
        preg_match_all('/<meta (?:property|name)="([^"]+)" content="([^"]*)"/', $response->getContent(), $m, PREG_SET_ORDER);
        $tags = [];
        foreach ($m as [, $key, $value]) {
            $tags[$key] ??= html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (preg_match('/<link rel="canonical" href="([^"]+)"/', $response->getContent(), $c)) {
            $tags['canonical'] = html_entity_decode($c[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $tags;
    }

    /** Chemin relatif de l'image (og:image est absolue sur SHARE_URL). */
    private function imagePath(array $tags): string
    {
        $this->assertStringStartsWith(self::SHARE . '/partage/', $tags['og:image']);

        return substr($tags['og:image'], strlen(self::SHARE));
    }

    private function assertJpeg(TestResponse $response): void
    {
        $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $path = $response->baseResponse->getFile()->getPathname();
        [$w, $h, $type] = getimagesize($path);
        $this->assertSame([1200, 630, IMAGETYPE_JPEG], [$w, $h, $type]);
        $this->assertLessThan(300 * 1024, filesize($path));
    }

    private function jpegBytes(int $w = 1280, int $h = 720): string
    {
        $gd = imagecreatetruecolor($w, $h);
        imagefilledrectangle($gd, 0, 0, $w, $h, imagecolorallocate($gd, 40, 120, 200));
        imagefilledellipse($gd, intdiv($w, 2), intdiv($h, 2), 300, 300, imagecolorallocate($gd, 250, 200, 30));
        ob_start();
        imagejpeg($gd, null, 85);

        return (string) ob_get_clean();
    }

    // ─── Témoignages : balises ──────────────────────────────────────────────

    public function test_text_testimony_page_has_open_graph_tags(): void
    {
        $t = $this->testimony();
        $tags = $this->meta($this->get(route('testimonies.show', $t->id))->assertOk());

        $this->assertSame('article', $tags['og:type']);
        $this->assertSame('My Testimony · ARISE & SHINE Krea', $tags['og:site_name']);
        $this->assertSame('Guérie après trois ans - Marie Adjovi', $tags['og:title']);
        $this->assertSame("Le Seigneur m'a guérie. Merci @Paul pour ses prières.", $tags['og:description']);
        $this->assertSame($t->share_url, $tags['og:url']);
        $this->assertSame($t->share_url, $tags['canonical']);
        $this->assertSame('fr_FR', $tags['og:locale']);
        $this->assertMatchesRegularExpression('#^' . preg_quote(self::SHARE, '#') . '/partage/temoignages/' . $t->id . '\.jpg\?v=[0-9a-f]{10}$#', $tags['og:image']);
        $this->assertSame($tags['og:image'], $tags['og:image:secure_url']);
        $this->assertSame('image/jpeg', $tags['og:image:type']);
        $this->assertSame(['1200', '630'], [$tags['og:image:width'], $tags['og:image:height']]);
        $this->assertNotEmpty($tags['og:image:alt']);
        $this->assertSame('summary_large_image', $tags['twitter:card']);
        $this->assertSame($tags['og:title'], $tags['twitter:title']);
        $this->assertSame($tags['og:description'], $tags['twitter:description']);
        $this->assertSame($tags['og:image'], $tags['twitter:image']);
        $this->assertArrayNotHasKey('og:video:url', $tags);
    }

    public function test_description_is_limited_and_has_a_fallback(): void
    {
        $long = $this->testimony(['body_text' => str_repeat('Gloire à Dieu pour sa fidélité. ', 20)]);
        $desc = $this->meta($this->get(route('testimonies.show', $long->id)))['og:description'];
        $this->assertLessThanOrEqual(160, mb_strlen($desc));
        $this->assertStringEndsWith('…', $desc);

        $empty = $this->testimony(['type' => 'video', 'body_text' => null, 'media_url' => 'https://app.example.org/storage/media/v.mp4']);
        $tags = $this->meta($this->get(route('testimonies.show', $empty->id)));
        $this->assertSame('Témoignage de Marie Adjovi sur My Testimony', $tags['og:description']);
        $this->assertSame('video.other', $tags['og:type']);
    }

    public function test_english_page_uses_en_us_locale(): void
    {
        $t = $this->testimony();
        $tags = $this->meta($this->withSession(['locale' => 'en'])->get(route('testimonies.show', $t->id)));
        $this->assertSame('en_US', $tags['og:locale']);
    }

    public function test_youtube_testimony_has_video_tags_and_thumbnail_with_play_button(): void
    {
        Http::fake([
            'i.ytimg.com/vi/dQw4w9WgXcQ/maxresdefault.jpg' => Http::response('', 404),
            'i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg' => Http::response($this->jpegBytes(480, 360), 200, ['Content-Type' => 'image/jpeg']),
        ]);
        $t = $this->testimony(['type' => 'video', 'youtube_id' => 'dQw4w9WgXcQ', 'body_text' => null]);
        $tags = $this->meta($this->get(route('testimonies.show', $t->id)));

        $this->assertSame('video.other', $tags['og:type']);
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $tags['og:video:url']);
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $tags['og:video:secure_url']);
        $this->assertSame('text/html', $tags['og:video:type']);
        $this->assertSame(['1280', '720'], [$tags['og:video:width'], $tags['og:video:height']]);

        $this->assertJpeg($this->get($this->imagePath($tags)));
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/maxresdefault.jpg'));
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/hqdefault.jpg'));
    }

    // ─── Témoignages : images ───────────────────────────────────────────────

    public function test_bunny_video_thumbnail_is_fetched_with_referer(): void
    {
        $thumb = 'https://' . self::BUNNY_HOST . '/' . self::BUNNY_GUID . '/thumbnail.jpg';
        Http::fake([self::BUNNY_HOST . '/*' => Http::response($this->jpegBytes(), 200, ['Content-Type' => 'image/jpeg'])]);
        $t = $this->testimony([
            'type' => 'video', 'bunny_video_id' => self::BUNNY_GUID, 'video_status' => 'ready', 'duration_sec' => 205,
            'media_url' => 'https://' . self::BUNNY_HOST . '/' . self::BUNNY_GUID . '/playlist.m3u8', 'cover_url' => $thumb,
        ]);

        $response = $this->get(route('share-images.testimony', $t->id));
        $this->assertJpeg($response);
        $this->assertStringContainsString('max-age=2592000', $response->headers->get('Cache-Control'));
        Http::assertSent(fn (HttpRequest $r) => $r->url() === $thumb && $r->header('Referer') === ['https://app.example.org/']);
    }

    public function test_bunny_thumbnail_is_used_even_without_cover_url(): void
    {
        Http::fake([self::BUNNY_HOST . '/*' => Http::response($this->jpegBytes())]);
        $t = $this->testimony(['type' => 'video', 'bunny_video_id' => self::BUNNY_GUID, 'video_status' => 'ready']);

        $this->assertJpeg($this->get(route('share-images.testimony', $t->id)));
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://' . self::BUNNY_HOST . '/' . self::BUNNY_GUID . '/thumbnail.jpg');
    }

    public function test_audio_testimony_with_remote_cover(): void
    {
        Http::fake(['images.example.net/*' => Http::response($this->jpegBytes(1600, 1067))]);
        $t = $this->testimony(['type' => 'audio', 'cover_url' => 'https://images.example.net/cover.jpg', 'media_url' => 'https://app.example.org/storage/media/a.m4a']);

        $this->assertJpeg($this->get(route('share-images.testimony', $t->id)));
        Http::assertSentCount(1);
    }

    public function test_cover_stored_on_the_site_is_read_from_disk_not_over_http(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('covers/c.jpg', $this->jpegBytes(900, 900));
        Http::fake();
        $t = $this->testimony(['cover_url' => 'https://app.example.org/storage/covers/c.jpg']);

        $this->assertJpeg($this->get(route('share-images.testimony', $t->id)));
        Http::assertNothingSent();
    }

    public function test_text_testimony_without_image_gets_a_brand_card_cached_until_it_changes(): void
    {
        Http::fake();
        $t = $this->testimony(['title' => 'Un titre très long ' . str_repeat('qui continue encore et encore ', 8) . '🙏']);
        $path = $this->imagePath($this->meta($this->get(route('testimonies.show', $t->id))));

        $first = $this->get($path);
        $this->assertJpeg($first);
        $file = $first->baseResponse->getFile()->getPathname();
        $this->assertStringStartsWith(realpath($this->root), realpath($file));
        $this->assertSame($file, $this->get($path)->baseResponse->getFile()->getPathname());

        // Titre modifié : nouvelle adresse (?v=) et nouvelle image ; l'ancienne est supprimée.
        $t->update(['title' => 'Nouveau titre']);
        $newPath = $this->imagePath($this->meta($this->get(route('testimonies.show', $t->id))));
        $this->assertNotSame($path, $newPath);
        $second = $this->get($newPath);
        $this->assertJpeg($second);
        $this->assertNotSame($file, $second->baseResponse->getFile()->getPathname());
        $this->assertFileDoesNotExist($file);
        Http::assertNothingSent();
    }

    public function test_unreachable_thumbnail_falls_back_to_brand_card_with_short_cache(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $t = $this->testimony(['type' => 'video', 'youtube_id' => 'dQw4w9WgXcQ']);

        $response = $this->get(route('share-images.testimony', $t->id));
        $this->assertJpeg($response);
        $this->assertStringContainsString('max-age=3600', $response->headers->get('Cache-Control'));
    }

    // ─── Témoignages non publics ────────────────────────────────────────────

    public function test_non_public_testimonies_do_not_leak_in_meta_and_have_no_image(): void
    {
        foreach ([
            ['visibility' => 'followers'],
            ['status' => 'pending', 'approved_at' => null],
        ] as $attrs) {
            $t = $this->testimony(array_merge(['title' => 'Secret de famille', 'body_text' => 'Texte confidentiel'], $attrs));
            // Page introuvable pour un visiteur (docs/fonctionnalites/referencement.md) : rien n'en sort.
            $this->get(route('testimonies.show', $t->id))->assertNotFound()
                ->assertDontSee('Secret de famille')->assertDontSee('confidentiel')->assertDontSee('Marie Adjovi');
            $this->get(route('share-images.testimony', $t->id))->assertNotFound();
        }

        $journal = $this->testimony(['visibility' => 'private']);
        $this->get(route('testimonies.show', $journal->id))->assertNotFound();
        $this->get(route('share-images.testimony', $journal->id))->assertNotFound();
        $this->get('/partage/temoignages/' . Str::uuid() . '.jpg')->assertNotFound();
    }

    public function test_site_image_is_served(): void
    {
        $this->assertJpeg($this->get(route('share-images.site')));
    }

    // ─── Robots des réseaux sociaux ─────────────────────────────────────────

    public function test_crawlers_get_the_page_and_image_without_login_or_cookies(): void
    {
        Http::fake();
        $t = $this->testimony();
        foreach ([
            'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
            'WhatsApp/2.23.20.0 A',
            'Twitterbot/1.0',
            'TelegramBot (like TwitterBot)',
            'LinkedInBot/1.0 (compatible; Mozilla/5.0; Apache-HttpClient +http://www.linkedin.com)',
        ] as $agent) {
            $page = $this->withHeaders(['User-Agent' => $agent])->get(route('testimonies.show', $t->id));
            $page->assertOk();
            $tags = $this->meta($page);
            $this->assertSame('Guérie après trois ans - Marie Adjovi', $tags['og:title'], $agent);

            $image = $this->withHeaders(['User-Agent' => $agent])->get($this->imagePath($tags));
            $this->assertJpeg($image);
            $this->assertEmpty($image->headers->getCookies(), $agent);
        }
        // robots.txt servi par Laravel (docs/fonctionnalites/referencement.md).
        $robots = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('#Disallow:\s*/(partage|testimonies|evenements)?\s*$#m', $robots);
    }

    // ─── Événements ─────────────────────────────────────────────────────────

    private function event(array $attrs = []): Event
    {
        return Event::create(array_merge([
            'organizer_id' => $this->author->id, 'title' => 'Croisade de la victoire', 'type' => 'crusade', 'status' => 'published',
            'description' => 'Trois soirs de **louange** et de prière.', 'city' => 'Cotonou', 'country' => 'Bénin',
            'starts_at' => now()->addDays(5)->setTime(18, 0), 'ends_at' => now()->addDays(5)->setTime(21, 0),
        ], $attrs));
    }

    public function test_event_page_tags_and_brand_card(): void
    {
        Http::fake();
        $event = $this->event();
        $tags = $this->meta($this->get(route('events.show', $event->id))->assertOk());

        $this->assertSame('Croisade de la victoire', $tags['og:title']);
        $this->assertSame(self::SHARE . '/evenements/' . $event->id, $tags['og:url']);
        $this->assertStringContainsString('Cotonou, Bénin', $tags['og:description']);
        $this->assertStringContainsString('Trois soirs de louange et de prière.', $tags['og:description']);
        $this->assertSame('article', $tags['og:type']);
        $this->assertStringContainsString('/partage/evenements/' . $event->id . '.jpg?v=', $tags['og:image']);
        $this->assertJpeg($this->get($this->imagePath($tags)));
        Http::assertNothingSent();
    }

    public function test_event_with_youtube_live_uses_the_youtube_thumbnail(): void
    {
        Http::fake(['i.ytimg.com/*' => Http::response($this->jpegBytes())]);
        $event = $this->event(['youtube_live_id' => 'dQw4w9WgXcQ']);
        $tags = $this->meta($this->get(route('events.show', $event->id)));

        $this->assertSame('video.other', $tags['og:type']);
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $tags['og:video:url']);
        $this->assertJpeg($this->get($this->imagePath($tags)));
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'i.ytimg.com/vi/dQw4w9WgXcQ/'));
    }

    public function test_draft_event_has_no_public_image(): void
    {
        $event = $this->event(['status' => 'draft']);
        $this->get(route('events.show', $event->id))->assertNotFound();
        $this->get(route('share-images.event', $event->id))->assertNotFound();
    }

    // ─── Requêtes de prière ─────────────────────────────────────────────────

    private function prayer(array $attrs = []): PrayerRequest
    {
        return PrayerRequest::create(array_merge([
            'user_id' => $this->author->id, 'body' => 'Priez pour la guérison de ma mère, hospitalisée depuis lundi.',
            'visibility' => 'public', 'is_anonymous' => false, 'status' => 'open',
        ], $attrs));
    }

    public function test_public_prayer_request_tags_and_image(): void
    {
        $prayer = $this->prayer();
        $tags = $this->meta($this->get(route('prayer.requests.show', $prayer->id))->assertOk());

        $this->assertSame('Requête de prière - Marie Adjovi', $tags['og:title']);
        $this->assertSame('Priez pour la guérison de ma mère, hospitalisée depuis lundi.', $tags['og:description']);
        $this->assertSame(self::SHARE . '/priere/requetes/' . $prayer->id, $tags['og:url']);
        $this->assertJpeg($this->get($this->imagePath($tags)));
    }

    public function test_anonymous_prayer_request_never_names_its_author(): void
    {
        $prayer = $this->prayer(['is_anonymous' => true]);
        $tags = $this->meta($this->get(route('prayer.requests.show', $prayer->id))->assertOk());

        $this->assertSame('Requête de prière', $tags['og:title']);
        foreach ($tags as $value) {
            $this->assertStringNotContainsString('Marie', $value);
        }
        $this->assertJpeg($this->get($this->imagePath($tags)));
    }

    public function test_followers_only_and_private_prayer_requests_are_not_shared(): void
    {
        foreach (['followers', 'private'] as $visibility) {
            $prayer = $this->prayer(['visibility' => $visibility]);
            $this->get(route('prayer.requests.show', $prayer->id))->assertNotFound();
            $this->get(route('share-images.prayer-request', $prayer->id))->assertNotFound();
        }
    }

    // ─── Directs ────────────────────────────────────────────────────────────

    public function test_public_live_page_tags_and_image(): void
    {
        $live = LiveSession::create([
            'host_id' => $this->author->id, 'title' => 'Soirée de louange', 'room_name' => 'live-' . Str::random(8),
            'status' => 'live', 'started_at' => now()->subMinutes(10),
        ]);
        $tags = $this->meta($this->get(route('lives.show', $live->id))->assertOk());

        $this->assertSame('Soirée de louange - Marie Adjovi', $tags['og:title']);
        $this->assertSame('Direct de Marie Adjovi sur My Testimony', $tags['og:description']);
        $this->assertSame('video.other', $tags['og:type']);
        $this->assertJpeg($this->get($this->imagePath($tags)));

        $preparing = LiveSession::create([
            'host_id' => $this->author->id, 'title' => 'Bientôt', 'room_name' => 'live-' . Str::random(8), 'status' => 'preparing',
        ]);
        $this->get(route('share-images.live', $preparing->id))->assertNotFound();
    }
}
