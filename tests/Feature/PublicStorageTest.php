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

    public function test_missing_hidden_or_outside_files_are_refused(): void
    {
        $this->get('/storage/media/videos/absent.mp4')->assertNotFound();
        $this->get('/storage/.gitignore')->assertNotFound();
        $this->get('/storage/../../.env')->assertNotFound();
        $this->get('/storage/media/%2e%2e/%2e%2e/.env')->assertNotFound();
    }
}
