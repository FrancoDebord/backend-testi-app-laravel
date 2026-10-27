<?php

namespace Tests\Feature;

use App\Models\Testimony;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Texte riche (images, liens, mise en forme) et aperçu avant publication sur le site.
 * docs/fonctionnalites/texte-riche-et-apercu.md
 */
class TestimonyComposerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->user = User::create([
            'display_name' => 'Ama', 'email' => 'ama@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active',
        ]);
    }

    public function test_page_de_publication_editeur_et_apercu(): void
    {
        $this->actingAs($this->user)->get(route('publish'))->assertOk()
            ->assertSee('data-rt-image-url="' . route('testimonies.images.store') . '"', false)
            ->assertSee('data-rt-link-toggle', false)
            ->assertSee('data-rt-block="blockquote"', false)
            ->assertSee('data-preview-open', false)
            ->assertSee('id="testimony-preview"', false);
    }

    public function test_envoi_d_une_image_du_texte(): void
    {
        $this->postJson(route('testimonies.images.store'))->assertUnauthorized();

        $response = $this->actingAs($this->user)->postJson(route('testimonies.images.store'), [
            'image' => UploadedFile::fake()->image('photo.jpg', 800, 600),
        ])->assertCreated();
        $url = $response->json('url');
        $this->assertStringContainsString('/storage/' . RichText::IMAGE_DIR . '/', $url);
        Storage::disk('public')->assertExists(RichText::IMAGE_DIR . '/' . basename($url));

        // Affichée dans le texte ; une image d'un autre site ne l'est jamais.
        $this->assertStringContainsString('<figure><img src="' . e($url) . '" alt="Avant" loading="lazy"><figcaption>Avant</figcaption></figure>',
            (string) RichText::toHtml("![Avant]({$url})"));
        $this->assertStringNotContainsString('<img', (string) RichText::toHtml('![x](https://autre-site.com/storage/testimony-images/a.jpg)'));

        $this->actingAs($this->user)->postJson(route('testimonies.images.store'), ['image' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])
            ->assertStatus(422);
    }

    public function test_apercu_du_texte(): void
    {
        $this->actingAs($this->user)->postJson(route('testimonies.preview'), ['body_text' => "# Titre\n**Dieu** est [bon](https://exemple.org)"])
            ->assertOk()
            ->assertJsonPath('html', '<h3>Titre</h3><p><strong>Dieu</strong> est <a href="https://exemple.org" target="_blank" rel="nofollow noopener noreferrer ugc">bon</a></p>');
    }

    public function test_publication_avec_images_limitees(): void
    {
        $url = asset('storage/' . RichText::IMAGE_DIR . '/a.jpg');
        $base = ['title' => 'Guérison', 'type' => 'text', 'category' => 'autre', 'consent_given' => '1'];
        \App\Models\Category::firstOrCreate(['slug' => 'autre'], ['name' => 'Autre']);

        $tooMany = implode("\n", array_fill(0, RichText::MAX_IMAGES + 1, "![]({$url})"));
        $this->actingAs($this->user)->post(route('testimonies.store'), [...$base, 'body_text' => $tooMany])->assertSessionHasErrors('body_text');

        $this->actingAs($this->user)->post(route('testimonies.store'), [...$base, 'body_text' => "Merci Seigneur\n![Guéri]({$url})"])->assertSessionHasNoErrors();
        $testimony = Testimony::latest()->first();
        $this->assertStringContainsString('<figure><img', (string) $testimony->body_html);
        $this->assertSame('Merci Seigneur', $testimony->body_plain);
    }
}
