<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Application installable (PWA) : manifeste, icônes, service worker et page hors ligne.
 * Voir docs/fonctionnalites/pwa.md
 */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_valid_and_its_icons_exist(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('My Testimony', $manifest['short_name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('#184797', $manifest['theme_color']);

        $sizes = [];
        foreach ($manifest['icons'] as $icon) {
            $path = public_path(ltrim($icon['src'], '/'));
            $this->assertFileExists($path);
            [$width, $height] = getimagesize($path);
            $this->assertSame($icon['sizes'], "{$width}x{$height}", $icon['src']);
            $sizes[] = $icon['sizes'] . ' ' . $icon['purpose'];
        }
        $this->assertContains('192x192 any', $sizes);
        $this->assertContains('512x512 any', $sizes);
        $this->assertContains('512x512 maskable', $sizes);
    }

    public function test_layouts_link_the_manifest_and_apple_icon(): void
    {
        foreach (['/', '/login'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('<link rel="manifest" href="' . asset('manifest.webmanifest') . '">', false)
                ->assertSee(asset('icons/pwa/apple-touch-icon.png'), false);
        }
        $this->assertFileExists(public_path('icons/pwa/apple-touch-icon.png'));
    }

    public function test_service_worker_never_caches_pages_or_api(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        // Pages : réseau seulement, page hors ligne en secours ; seuls /build/assets et /icons sont mis en cache.
        $this->assertStringContainsString("request.mode === 'navigate'", $sw);
        $this->assertStringContainsString("fetch(request).catch(() => caches.match(OFFLINE_URL))", $sw);
        $this->assertStringContainsString("request.method !== 'GET'", $sw);
        $this->assertStringNotContainsString('/api', $sw);
        $this->assertFileExists(public_path('offline.html'));
        $this->assertStringContainsString('Vous êtes hors ligne', file_get_contents(public_path('offline.html')));
    }
}
