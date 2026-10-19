<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Secours quand le lien public/storage manque sur l'hébergement. */
class PublicStorageTest extends TestCase
{
    public function test_uploaded_media_is_served_with_range_support(): void
    {
        Storage::disk('public')->put('media/videos/test-lecture.mp4', str_repeat('0123456789', 100));

        try {
            $full = $this->get('/storage/media/videos/test-lecture.mp4');
            $full->assertOk();
            $this->assertSame('1000', (string) $full->headers->get('Content-Length'));
            $this->assertStringContainsString('video/mp4', (string) $full->headers->get('Content-Type'));
            $this->assertNull($full->headers->getCookies() ? 'cookie' : null, 'pas de session pour un fichier');

            // Lecture partielle : indispensable aux lecteurs vidéo et audio.
            $part = $this->withHeaders(['Range' => 'bytes=0-99'])->get('/storage/media/videos/test-lecture.mp4');
            $part->assertStatus(206);
            $this->assertSame('bytes 0-99/1000', $part->headers->get('Content-Range'));
        } finally {
            Storage::disk('public')->delete('media/videos/test-lecture.mp4');
        }
    }

    public function test_uploaded_image_is_served_by_laravel_with_cache(): void
    {
        Storage::disk('public')->put('avatars/test-photo.jpg', 'jpeg');

        try {
            $response = $this->get('/storage/avatars/test-photo.jpg')->assertOk();
            $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
            $this->assertStringContainsString('max-age=604800', (string) $response->headers->get('Cache-Control'));
        } finally {
            Storage::disk('public')->delete('avatars/test-photo.jpg');
        }
    }

    /** Lien refusé par Apache (AH00037) : retiré par défaut au déploiement ; recréé seulement si STORAGE_PUBLIC_LINK=true. */
    public function test_deploy_removes_the_public_storage_link_unless_enabled(): void
    {
        $dir  = storage_path('framework/testing/public-link');
        $link = $dir . '/storage';
        \Illuminate\Support\Facades\File::ensureDirectoryExists($dir);
        @unlink($link);
        @rmdir($link);
        if (!@symlink(storage_path('app/public'), $link)) {
            $this->markTestSkipped('Création de lien symbolique impossible sur ce poste.');
        }

        try {
            config(['filesystems.public_link' => false]);
            $this->assertSame('lien retiré : fichiers servis par Laravel', \App\Support\PublicStorageLink::sync($link));
            $this->assertFalse(is_link($link));
            $this->assertSame('absent : fichiers servis par Laravel', \App\Support\PublicStorageLink::sync($link));

            config(['filesystems.public_link' => true]);
            $this->assertSame('créé', \App\Support\PublicStorageLink::sync($link));
            $this->assertTrue(is_link($link));
            $this->assertSame('présent', \App\Support\PublicStorageLink::sync($link));
        } finally {
            @unlink($link);
            @rmdir($link);
            \Illuminate\Support\Facades\File::deleteDirectory($dir);
        }
    }

    public function test_missing_hidden_or_outside_files_are_refused(): void
    {
        $this->get('/storage/media/videos/absent.mp4')->assertNotFound();
        $this->get('/storage/.gitignore')->assertNotFound();
        $this->get('/storage/../../.env')->assertNotFound();
        $this->get('/storage/media/%2e%2e/%2e%2e/.env')->assertNotFound();
    }
}
