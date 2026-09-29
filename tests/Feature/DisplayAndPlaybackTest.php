<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Testimony;
use App\Models\User;
use App\Support\FeedLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Affichage des listes (cartes / compact) et réglages de lecture du site. docs/fonctionnalites/affichage-et-lecture.md */
class DisplayAndPlaybackTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::create([
            'display_name' => 'Marie Auteur', 'email' => 'marie@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active',
        ]);
        Category::create(['name' => 'Guérison', 'slug' => 'guerison', 'is_active' => true]);
    }

    private function testimony(string $title, array $attrs = []): Testimony
    {
        return Testimony::create(array_merge([
            'user_id' => $this->author->id, 'title' => $title, 'type' => 'video', 'category_slug' => 'guerison',
            'body_text' => 'Description du témoignage', 'media_url' => 'https://example.com/v.mp4', 'duration_sec' => 300,
            'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ], $attrs))->fresh();
    }

    private const VIDEO_RENDITIONS = [
        ['quality' => '240p', 'height' => 240, 'bitrate' => 400, 'disk' => 'public', 'path' => 'media/videos/a_240p.mp4', 'size_bytes' => 1],
        ['quality' => '720p', 'height' => 720, 'bitrate' => 2500, 'disk' => 'public', 'path' => 'media/videos/a_720p.mp4', 'size_bytes' => 1],
    ];

    // ─── Affichage des listes ────────────────────────────────────────────

    public function test_lists_use_large_cards_by_default(): void
    {
        $this->testimony('Témoignage en carte');

        $this->get('/explore')->assertOk()
            ->assertSee('Témoignage en carte')
            ->assertSee('data-layout="cards"', false)
            ->assertDontSee('data-row-toggle', false)
            ->assertSee('aria-pressed="true" aria-label="Grandes cartes"', false);
    }

    public function test_choosing_compact_sets_a_cookie_and_redirects_back(): void
    {
        $this->from('/explore')
            ->post(route('preferences.layout'), ['layout' => 'compact'])
            ->assertRedirect('/explore')
            ->assertCookie(FeedLayout::COOKIE, 'compact');
    }

    public function test_invalid_layout_is_refused_without_cookie(): void
    {
        $this->from('/explore')
            ->post(route('preferences.layout'), ['layout' => 'mosaic'])
            ->assertRedirect('/explore')
            ->assertSessionHasErrors('layout')
            ->assertCookieMissing(FeedLayout::COOKIE);
    }

    public function test_compact_layout_renders_expandable_rows_on_every_list(): void
    {
        $t = $this->testimony('Témoignage compact');
        $this->author->savedTestimonies()->attach($t->id, ['saved_at' => now()]);

        $pages = ['/', '/explore', '/videos', '/profiles/' . $this->author->id];
        foreach ($pages as $page) {
            $this->withCookie(FeedLayout::COOKIE, 'compact')->get($page)->assertOk()
                ->assertSee('data-layout="compact"', false)
                ->assertSee('aria-controls="row-details-' . $t->id . '"', false)
                ->assertSee('Description du témoignage')
                ->assertSee('Regarder');
        }

        $this->actingAs($this->author);
        foreach (['/profile/saved', '/mes-temoignages'] as $page) {
            $this->withCookie(FeedLayout::COOKIE, 'compact')->get($page)->assertOk()
                ->assertSee('row-details-' . $t->id, false);
        }
    }

    public function test_compact_rows_open_the_right_page_and_show_status(): void
    {
        $t = $this->testimony('En attente de relecture', ['status' => 'pending', 'approved_at' => null]);
        $this->actingAs($this->author);

        $this->withCookie(FeedLayout::COOKIE, 'compact')->get('/mes-temoignages')->assertOk()
            ->assertSee(route('testimonies.show', $t->id), false)
            ->assertSee($t->status->label());
    }

    public function test_load_more_on_videos_follows_the_chosen_layout(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->testimony("Vidéo $i");
        }

        $json = $this->withCredentials()->withCookie(FeedLayout::COOKIE, 'compact')
            ->getJson('/videos?page=2')->assertOk()->json();

        $this->assertStringContainsString('data-row-toggle', $json['html']);
        $this->assertStringContainsString('/videos/', $json['html']);
    }

    // ─── Réglages de lecture ─────────────────────────────────────────────

    public function test_watch_page_offers_qualities_when_renditions_exist(): void
    {
        $t = $this->testimony('Vidéo multi-qualités', ['renditions' => self::VIDEO_RENDITIONS]);

        $html = $this->get('/videos/' . $t->id)->assertOk()
            ->assertSee('data-player-quality', false)
            ->assertSee('Qualité 720p')
            ->assertSee('Qualité 240p')
            ->assertSee("Qualité d'origine", false)
            ->assertSee('data-player-loop', false)
            ->getContent();

        // Les versions sont transmises au lecteur avec leur URL publique ; l'original reste la source par défaut.
        $this->assertStringContainsString(e(json_encode($t->playableRenditions(), JSON_UNESCAPED_SLASHES)), $html);
        $this->assertStringContainsString('storage/media/videos/a_240p.mp4', $html);
        $this->assertStringContainsString('<source src="https://example.com/v.mp4">', $html);
    }

    public function test_audio_qualities_are_labelled_in_kbps(): void
    {
        $t = $this->testimony('Audio multi-qualités', ['type' => 'audio', 'renditions' => [
            ['quality' => '32k', 'bitrate' => 32, 'disk' => 'public', 'path' => 'media/audios/a_32k.m4a', 'size_bytes' => 1],
            ['quality' => '64k', 'bitrate' => 64, 'disk' => 'public', 'path' => 'media/audios/a_64k.m4a', 'size_bytes' => 1],
        ]]);

        $this->get('/videos/' . $t->id)->assertOk()
            ->assertSee('Qualité 64 kbps')
            ->assertSee('Qualité 32 kbps');
    }

    public function test_no_quality_menu_without_renditions_and_no_player_settings_for_text(): void
    {
        $video = $this->testimony('Vidéo sans versions');
        $text  = $this->testimony('Récit écrit', ['type' => 'text', 'media_url' => null]);

        // Le menu Qualité reste visible (désactivé) et explique pourquoi.
        $this->get('/videos/' . $video->id)->assertOk()
            ->assertDontSee('data-player-quality', false)
            ->assertSee('id="player-quality"', false)
            ->assertSee("Seule la qualité d'origine est disponible")
            ->assertSee('data-player-speed', false);

        // Conversion en cours : « en préparation ».
        \App\Models\MediaFile::create([
            'user_id' => $this->author->id, 'disk' => 'public', 'path' => 'media/videos/v.mp4', 'url' => $video->media_url,
            'mime_type' => 'video/mp4', 'type' => 'video', 'processing_status' => 'pending',
        ]);
        $this->get('/videos/' . $video->id)->assertOk()->assertSee('Autres qualités en préparation');
        $this->getJson('/api/v1/testimonies/' . $video->id)->assertOk()->assertJsonPath('data.renditionsStatus', 'pending');

        $this->get('/videos/' . $text->id)->assertOk()
            ->assertDontSee('data-player-speed', false)
            ->assertDontSee('data-player-loop', false);
    }

    public function test_up_next_lists_only_playable_items_of_the_same_type(): void
    {
        $t     = $this->testimony('Vidéo en cours');
        $next  = $this->testimony('Vidéo suivante');
        $audio = $this->testimony('Un audio', ['type' => 'audio']);
        $this->testimony('Un texte', ['type' => 'text', 'media_url' => null]);

        $html = $this->get('/videos/' . $t->id)->assertOk()
            ->assertSee('data-player-autoplay', false)
            ->assertSee('data-up-next-banner', false)
            ->getContent();

        preg_match('/data-up-next="([^"]*)"/', $html, $m);
        $queue = json_decode(html_entity_decode($m[1]), true);
        $this->assertSame([$next->id], array_column($queue, 'id'));
        $this->assertSame(route('videos.show', $next->id), $queue[0]['url']);
        $this->assertNotContains($audio->id, array_column($queue, 'id'));

        // Depuis /testimonies/{id}, le suivant s'ouvre sur la même page.
        $html = $this->get('/testimonies/' . $t->id)->getContent();
        $this->assertStringContainsString(e(json_encode(route('testimonies.show', $next->id), JSON_UNESCAPED_SLASHES)), $html);
    }

    public function test_no_autoplay_control_without_a_next_item(): void
    {
        $t = $this->testimony('Seule vidéo');

        $this->get('/videos/' . $t->id)->assertOk()
            ->assertSee('data-player-loop', false)
            ->assertDontSee('data-player-autoplay', false);
    }
}
