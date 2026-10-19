<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Crédit de la vidéo YouTube d'origine — docs/fonctionnalites/videos-youtube.md */
class YouTubeCreditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['name' => 'Guérison', 'slug' => 'guerison', 'is_active' => true, 'display_order' => 1]);
        $this->admin = User::create([
            'display_name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret123',
            'role' => 'administrateur', 'status' => 'active',
        ]);
    }

    private function apiPayload(array $extra = []): array
    {
        return array_merge([
            'title' => 'Vidéo', 'type' => 'video', 'category' => 'guerison', 'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ], $extra);
    }

    private function credit(): array
    {
        return [
            'youtube_credit_author'  => '  Pasteur Jean  ',
            'youtube_credit_channel' => 'Église de la Grâce',
            'youtube_credit_url'     => 'https://www.youtube.com/@eglisedelagrace',
        ];
    }

    public function test_api_stores_and_returns_the_credit_of_a_youtube_testimony(): void
    {
        $res = $this->actingAs($this->admin)->postJson('/api/v1/testimonies', $this->apiPayload($this->credit()))->assertCreated()
            ->assertJsonPath('data.youtubeCredit', [
                'author' => 'Pasteur Jean', 'channel' => 'Église de la Grâce', 'url' => 'https://www.youtube.com/@eglisedelagrace',
            ]);

        // Sans crédit : null.
        $this->actingAs($this->admin)->postJson('/api/v1/testimonies', $this->apiPayload())->assertCreated()
            ->assertJsonPath('data.youtubeCredit', null);

        // Modification : seuls les champs envoyés changent ; vider un champ le retire.
        $id = $res->json('data.id');
        $this->actingAs($this->admin)->putJson("/api/v1/testimonies/{$id}", ['youtube_credit_channel' => 'Nouvelle chaîne', 'youtube_credit_url' => ''])
            ->assertOk()->assertJsonPath('data.youtubeCredit', ['author' => 'Pasteur Jean', 'channel' => 'Nouvelle chaîne', 'url' => null]);
    }

    public function test_credit_is_ignored_for_other_testimonies(): void
    {
        $marie = User::create(['display_name' => 'Marie', 'email' => 'marie@example.com', 'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active']);

        $res = $this->actingAs($marie)->postJson('/api/v1/testimonies', [
            'title' => 'Texte', 'type' => 'text', 'category' => 'guerison', 'body_text' => 'Merci', ...$this->credit(),
        ])->assertCreated()->assertJsonPath('data.youtubeCredit', null);

        $testimony = Testimony::findOrFail($res->json('data.id'));
        $this->assertNull($testimony->youtube_credit_author);

        $this->actingAs($marie)->putJson("/api/v1/testimonies/{$testimony->id}", ['youtube_credit_author' => 'Quelqu’un'])
            ->assertOk()->assertJsonPath('data.youtubeCredit', null);
        $this->assertNull($testimony->fresh()->youtube_credit_author);
    }

    public function test_credit_is_validated_with_translated_messages(): void
    {
        foreach (['ftp://exemple.org/chaine', 'pas une adresse', 'javascript:alert(1)'] as $bad) {
            $this->actingAs($this->admin)->postJson('/api/v1/testimonies', $this->apiPayload(['youtube_credit_url' => $bad]))
                ->assertStatus(422)
                ->assertJsonPath('errors.youtube_credit_url.0', 'Le lien de la chaîne doit être une adresse web valide commençant par http:// ou https://.');
        }
        $this->actingAs($this->admin)->postJson('/api/v1/testimonies', $this->apiPayload(['youtube_credit_author' => str_repeat('a', 151)]))
            ->assertStatus(422)->assertJsonPath('errors.youtube_credit_author.0', 'L’auteur de la vidéo ne doit pas dépasser 150 caractères.');
        $this->actingAs($this->admin)->postJson('/api/v1/testimonies', $this->apiPayload(['youtube_credit_channel' => str_repeat('a', 151)]))
            ->assertStatus(422)->assertJsonValidationErrors(['youtube_credit_channel']);

        $this->actingAs($this->admin)->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/testimonies', $this->apiPayload(['youtube_credit_url' => 'ftp://exemple.org']))
            ->assertStatus(422)
            ->assertJsonPath('errors.youtube_credit_url.0', 'The channel link must be a valid web address starting with http:// or https://.');

        $this->assertSame(0, Testimony::count());
    }

    public function test_web_form_offers_credit_fields_and_page_shows_the_credit(): void
    {
        $this->actingAs($this->admin)->get('/publish')->assertOk()
            ->assertSee('Auteur de la vidéo')->assertSee('Chaîne YouTube')->assertSee('Lien de la chaîne')
            ->assertSee('name="youtube_credit_url"', false);

        $this->actingAs($this->admin)->post('/testimonies', [
            'title' => 'Vidéo', 'type' => 'youtube', 'category' => 'guerison', 'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'visibility' => 'public', 'consent_given' => '1', ...$this->credit(),
        ])->assertRedirect();

        $testimony = Testimony::firstOrFail();
        $this->assertSame('Pasteur Jean', $testimony->youtube_credit_author);

        $this->actingAs($this->admin)->get("/testimonies/{$testimony->id}")->assertOk()
            ->assertSee('Vidéo de Pasteur Jean · Église de la Grâce')
            ->assertSee('href="https://www.youtube.com/@eglisedelagrace" target="_blank" rel="noopener"', false);

        $this->actingAs($this->admin)->post('/testimonies', [
            'title' => 'Mauvais lien', 'type' => 'youtube', 'category' => 'guerison', 'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'visibility' => 'public', 'consent_given' => '1', 'youtube_credit_url' => 'ftp://exemple.org',
        ])->assertSessionHasErrors(['youtube_credit_url' => 'Le lien de la chaîne doit être une adresse web valide commençant par http:// ou https://.']);
    }
}
