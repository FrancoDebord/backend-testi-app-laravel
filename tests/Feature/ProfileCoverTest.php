<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Photo de couverture du profil. docs/fonctionnalites/photo-de-couverture.md */
class ProfileCoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function user(): User
    {
        return User::create([
            'display_name' => 'Jean Dupont', 'email' => 'jean@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active',
        ]);
    }

    private function storedPath(?string $url): string
    {
        return 'profile-covers/' . basename((string) $url);
    }

    public function test_web_profile_update_stores_replaces_and_removes_the_cover(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/profile', [
            'display_name' => 'Jean Dupont',
            'cover'        => UploadedFile::fake()->image('cover.jpg', 1500, 500),
        ])->assertSessionHasNoErrors();

        $first = $user->fresh()->cover_url;
        $this->assertStringContainsString('/storage/profile-covers/', $first);
        Storage::disk('public')->assertExists($this->storedPath($first));

        // Remplacement : l'ancien fichier est supprimé.
        $this->actingAs($user)->put('/profile', [
            'display_name' => 'Jean Dupont',
            'cover'        => UploadedFile::fake()->image('new.png', 1200, 400),
        ])->assertSessionHasNoErrors();
        $second = $user->fresh()->cover_url;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($this->storedPath($first));

        // Une mise à jour sans fichier garde la couverture.
        $this->actingAs($user)->put('/profile', ['display_name' => 'Jean D.'])->assertSessionHasNoErrors();
        $this->assertSame($second, $user->fresh()->cover_url);

        $this->actingAs($user)->put('/profile', ['display_name' => 'Jean D.', 'remove_cover' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->cover_url);
        Storage::disk('public')->assertMissing($this->storedPath($second));
    }

    public function test_cover_must_be_a_large_enough_image(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/profile', [
            'display_name' => 'Jean Dupont',
            'cover'        => UploadedFile::fake()->image('tiny.jpg', 300, 100),
        ])->assertSessionHasErrors(['cover' => 'La photo de couverture doit mesurer au moins 600 × 150 pixels.']);

        $this->actingAs($user)->postJson('/api/v1/users/me/cover', [
            'cover' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('cover');

        $this->actingAs($user)->postJson('/api/v1/users/me/cover', [])->assertStatus(422);
        $this->assertNull($user->fresh()->cover_url);
    }

    public function test_api_uploads_and_deletes_the_cover_and_exposes_it(): void
    {
        $user = $this->user();

        $url = $this->actingAs($user)->postJson('/api/v1/users/me/cover', [
            'cover' => UploadedFile::fake()->image('cover.webp', 1500, 500),
        ])->assertSuccessful()->json('data.cover_url');

        $this->assertSame($url, $user->fresh()->cover_url);
        $this->getJson("/api/v1/users/{$user->id}")->assertJsonPath('data.cover_url', $url);

        $this->actingAs($user)->deleteJson('/api/v1/users/me/cover')
            ->assertSuccessful()->assertJsonPath('data.cover_url', null);
        $this->assertNull($user->fresh()->cover_url);
        Storage::disk('public')->assertMissing($this->storedPath($url));
    }

    public function test_guest_cannot_change_a_cover(): void
    {
        $this->postJson('/api/v1/users/me/cover', [
            'cover' => UploadedFile::fake()->image('cover.jpg', 1500, 500),
        ])->assertUnauthorized();
    }

    public function test_profile_page_shows_the_cover_or_the_default_banner(): void
    {
        $user = $this->user();
        $this->get("/profiles/{$user->id}")->assertOk()->assertDontSee('/storage/profile-covers/', false);

        $user->update(['cover_url' => 'http://localhost/storage/profile-covers/abc.jpg']);
        $this->get("/profiles/{$user->id}")->assertOk()
            ->assertSee('http://localhost/storage/profile-covers/abc.jpg', false);
    }

    public function test_a_cover_outside_the_covers_folder_is_never_deleted(): void
    {
        $user = $this->user();
        Storage::disk('public')->put('avatars/keep.jpg', 'x');
        $user->update(['cover_url' => 'http://localhost/storage/avatars/keep.jpg']);

        $this->actingAs($user)->deleteJson('/api/v1/users/me/cover')->assertSuccessful();
        Storage::disk('public')->assertExists('avatars/keep.jpg');
    }
}
