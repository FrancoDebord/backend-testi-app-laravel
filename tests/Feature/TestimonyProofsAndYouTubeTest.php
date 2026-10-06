<?php

namespace Tests\Feature;

use App\Models\Testimony;
use App\Models\User;
use App\Support\YouTube;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Preuves (docs/fonctionnalites/preuves.md) et vidéos YouTube (docs/fonctionnalites/videos-youtube.md). */
class TestimonyProofsAndYouTubeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        \App\Models\Category::create(['name' => 'Guérison', 'slug' => 'guerison', 'is_active' => true, 'display_order' => 1]);
    }

    private function user(string $name, string $role = 'utilisateur'): User
    {
        return User::create([
            'display_name' => $name, 'email' => str()->slug($name) . '@example.com', 'password' => 'secret123',
            'role' => $role, 'status' => 'active',
        ]);
    }

    private function form(array $extra = []): array
    {
        return array_merge([
            'title' => 'Guéri', 'type' => 'text', 'category' => 'guerison', 'body_text' => 'Mon histoire',
            'visibility' => 'public', 'consent_given' => '1',
        ], $extra);
    }

    // ─── Preuves ───────────────────────────────────────────────────────────

    public function test_web_form_stores_two_private_proofs_visible_to_author_and_moderators_only(): void
    {
        $author = $this->user('Marie');
        $this->actingAs($author)->post('/testimonies', $this->form([
            'proof_1' => UploadedFile::fake()->image('certificat.jpg', 800, 600),
            'proof_2' => UploadedFile::fake()->create('attestation.pdf', 200, 'application/pdf'),
        ]))->assertSessionHasNoErrors();

        $t = Testimony::firstOrFail();
        $this->assertCount(2, $t->proofs);
        $proof = $t->proofs->first();
        Storage::disk('local')->assertExists($proof->path);
        $this->assertStringStartsWith('proofs/' . $t->id, $proof->path);

        $url = route('testimonies.proof', [$t->id, $proof->id]);
        $this->actingAs($author)->get($url)->assertOk();
        $this->actingAs($this->user('Modo', 'moderateur'))->get($url)->assertOk();
        $this->actingAs($this->user('Paul'))->get($url)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertForbidden();   // sans accord de l'auteur : jamais public

        // Page de modération : preuves listées
        $this->actingAs($this->user('Modo 2', 'moderateur'))->get("/moderation/{$t->id}")->assertOk()
            ->assertSee('Preuves du témoignage')->assertSee('certificat.jpg');
    }

    public function test_proof_must_be_an_image_or_pdf(): void
    {
        $this->actingAs($this->user('Marie'))->post('/testimonies', $this->form([
            'proof_1' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
        ]))->assertSessionHasErrors(['proof_1' => 'La preuve doit être une image (JPG, PNG, WebP) ou un PDF.']);
    }

    public function test_api_adds_lists_reads_and_removes_proofs_with_a_limit_of_two(): void
    {
        $author = $this->user('Marie');
        $t = Testimony::create(['user_id' => $author->id, 'title' => 'T', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'x', 'visibility' => 'public', 'status' => 'pending']);

        $first = $this->actingAs($author)->postJson("/api/v1/testimonies/{$t->id}/proofs", [
            'file' => UploadedFile::fake()->image('a.png'),
        ])->assertCreated()->json('data');
        $this->actingAs($author)->postJson("/api/v1/testimonies/{$t->id}/proofs", ['file' => UploadedFile::fake()->create('b.pdf', 50, 'application/pdf')])
            ->assertCreated()->assertJsonPath('data.position', 2)->assertJsonPath('data.isPdf', true);
        $this->actingAs($author)->postJson("/api/v1/testimonies/{$t->id}/proofs", ['file' => UploadedFile::fake()->image('c.png')])
            ->assertStatus(422);

        $this->actingAs($author)->getJson("/api/v1/testimonies/{$t->id}")->assertJsonCount(2, 'data.proofs');
        $this->actingAs($this->user('Autre'))->postJson("/api/v1/testimonies/{$t->id}/proofs", ['file' => UploadedFile::fake()->image('d.png')])
            ->assertForbidden();

        $this->actingAs($author)->get("/api/v1/testimonies/{$t->id}/proofs/{$first['id']}")->assertOk();
        $this->actingAs($author)->deleteJson("/api/v1/testimonies/{$t->id}/proofs/{$first['id']}")->assertOk();
        $this->assertCount(1, $t->fresh()->proofs);
    }

    public function test_moderators_read_pending_or_followers_only_testimonies_without_counting_a_view(): void
    {
        $author = $this->user('Marie');
        $t = Testimony::create(['user_id' => $author->id, 'title' => 'T', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'x', 'visibility' => 'followers', 'status' => 'pending']);

        $this->actingAs($this->user('Modo', 'moderateur'))->getJson("/api/v1/testimonies/{$t->id}")->assertOk()->assertJsonPath('data.proofs', []);
        $this->assertSame(0, (int) $t->fresh()->views_count);
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->user('Paul'))->getJson("/api/v1/testimonies/{$t->id}")->assertForbidden();
    }

    public function test_api_hides_proofs_from_other_users(): void
    {
        $author = $this->user('Marie');
        $t = Testimony::create(['user_id' => $author->id, 'title' => 'T', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'x', 'visibility' => 'public', 'status' => 'approved', 'approved_at' => now()]);

        $this->actingAs($this->user('Paul'))->getJson("/api/v1/testimonies/{$t->id}")->assertOk()->assertJsonMissingPath('data.proofs');
    }

    public function test_proofs_become_public_only_with_consent_once_published_and_moderators_can_withdraw(): void
    {
        $author = $this->user('Marie');
        $this->actingAs($author)->post('/testimonies', $this->form([
            'proof_1' => UploadedFile::fake()->image('certificat.jpg', 800, 600),
            'proofs_public' => '1',
        ]))->assertSessionHasNoErrors();
        $t = Testimony::firstOrFail();
        $this->assertTrue($t->proofs_public);
        $url = route('testimonies.proof', [$t->id, $t->proofs->first()->id]);
        $this->app['auth']->forgetGuards();

        // En attente de relecture : pas encore public
        $this->get($url)->assertForbidden();

        $t->update(['status' => 'approved', 'approved_at' => now()]);
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'max-age=300, public');
        $this->get("/testimonies/{$t->id}")->assertOk()->assertSee('Documents partagés par l’auteur pour confirmer ce témoignage.');
        $this->getJson("/api/v1/testimonies/{$t->id}")->assertJsonPath('data.proofsPublic', true)->assertJsonCount(1, 'data.proofs');
        $this->get("/api/v1/testimonies/{$t->id}/proofs/{$t->proofs->first()->id}")->assertOk();

        // La modération retire l'affichage public
        $this->actingAs($this->user('Modo', 'moderateur'))->post("/moderation/{$t->id}/hide-proofs")->assertRedirect();
        $this->assertFalse($t->fresh()->proofs_public);
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertForbidden();
        $this->getJson("/api/v1/testimonies/{$t->id}")->assertJsonMissingPath('data.proofs');
    }

    public function test_api_accepts_the_consent_on_creation_and_update(): void
    {
        $author = $this->user('Marie');
        $id = $this->actingAs($author)->postJson('/api/v1/testimonies', ['title' => 'T', 'type' => 'text', 'category' => 'guerison', 'body_text' => 'x', 'proofs_public' => true])
            ->assertCreated()->assertJsonPath('data.proofsPublic', true)->json('data.id');
        $this->actingAs($author)->putJson("/api/v1/testimonies/{$id}", ['title' => 'T', 'type' => 'text', 'proofs_public' => false])->assertOk()->assertJsonPath('data.proofsPublic', false);
    }

    // ─── YouTube ───────────────────────────────────────────────────────────

    public function test_youtube_links_are_recognised(): void
    {
        foreach ([
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10s', 'https://youtu.be/dQw4w9WgXcQ?si=abc', 'youtube.com/shorts/dQw4w9WgXcQ',
            'https://m.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube.com/live/dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ',
        ] as $link) {
            $this->assertSame('dQw4w9WgXcQ', YouTube::parseId($link), $link);
        }
        foreach (['https://vimeo.com/123', 'https://www.youtube.com/watch?v=court', 'bonjour', ''] as $bad) {
            $this->assertNull(YouTube::parseId($bad), $bad);
        }
    }

    public function test_only_administrators_publish_a_youtube_video_and_it_is_embedded(): void
    {
        $this->actingAs($this->user('Marie'))->post('/testimonies', $this->form(['type' => 'video', 'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ']))
            ->assertSessionHasErrors(['youtube_url' => 'Seuls les administrateurs peuvent publier une vidéo YouTube.']);

        $admin = $this->user('Admin', 'administrateur');
        $this->actingAs($admin)->post('/testimonies', $this->form(['type' => 'video', 'youtube_url' => 'https://vimeo.com/1']))
            ->assertSessionHasErrors('youtube_url');
        $this->actingAs($admin)->post('/testimonies', $this->form(['type' => 'text', 'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ']))
            ->assertSessionHasNoErrors();

        $t = Testimony::firstOrFail();
        $this->assertSame('dQw4w9WgXcQ', $t->youtube_id);
        $this->assertSame('video', $t->type->value);                       // forcé en vidéo
        $this->assertSame(YouTube::thumbnailUrl('dQw4w9WgXcQ'), $t->cover_url);
        $t->update(['status' => 'approved', 'approved_at' => now()]);

        $this->get("/testimonies/{$t->id}")->assertOk()
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('Ouvrir sur YouTube');
        $this->getJson("/api/v1/testimonies/{$t->id}")->assertJsonPath('data.youtubeId', 'dQw4w9WgXcQ')
            ->assertJsonPath('data.youtubeUrl', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
    }

    public function test_publish_form_offers_a_youtube_type_to_administrators_only(): void
    {
        $this->actingAs($this->user('Admin', 'administrateur'))->get('/publish')->assertOk()
            ->assertSee('value="youtube"', false)->assertSee('Lien de la vidéo YouTube');
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->user('Marie'))->get('/publish')->assertOk()
            ->assertDontSee('value="youtube"', false)->assertDontSee('name="youtube_url"', false);

        $this->app['auth']->forgetGuards();
        $admin = $this->user('Admin 2', 'administrateur');
        $this->actingAs($admin)->post('/testimonies', $this->form(['type' => 'youtube']))
            ->assertSessionHasErrors(['youtube_url' => 'Collez le lien de la vidéo YouTube.']);
        $this->actingAs($admin)->post('/testimonies', $this->form(['type' => 'youtube', 'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ']))
            ->assertSessionHasNoErrors();
        $this->assertSame('video', Testimony::firstOrFail()->type->value);
    }

    public function test_api_youtube_publication_is_reserved_to_administrators(): void
    {
        $payload = ['title' => 'Vidéo', 'type' => 'video', 'category' => 'guerison', 'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ'];
        $this->actingAs($this->user('Marie'))->postJson('/api/v1/testimonies', $payload)->assertForbidden();
        $this->actingAs($this->user('Admin', 'administrateur'))->postJson('/api/v1/testimonies', $payload)->assertCreated()
            ->assertJsonPath('data.youtubeId', 'dQw4w9WgXcQ')->assertJsonPath('data.mediaUrl', null);
    }
}
