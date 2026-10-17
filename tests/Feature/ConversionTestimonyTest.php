<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Testimony;
use App\Models\User;
use App\Services\TestimonyModeration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Témoignage de conversion : compte personnel, un seul par compte, modifiable, même modération, endpoints
 * et pages du site. Voir docs/fonctionnalites/temoignage-de-conversion.md
 */
class ConversionTestimonyTest extends TestCase
{
    use RefreshDatabase;

    private User $person;
    private User $org;
    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['name' => 'Conversion', 'slug' => 'conversion', 'is_active' => true, 'display_order' => 1]);
        Category::create(['name' => 'Guérison', 'slug' => 'guerison', 'is_active' => true, 'display_order' => 2]);
        $this->person = $this->user('marie');
        $this->org = $this->user('eglise', ['account_type' => 'organization', 'organization_name' => 'Église', 'organization_type' => 'church']);
        $this->moderator = $this->user('mod', ['role' => 'moderateur']);
    }

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => ucfirst($name), 'email' => "{$name}@example.com",
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'title' => 'Comment j\'ai rencontré Jésus', 'type' => 'text', 'body_text' => 'Une nuit, à Cotonou…',
            'kind' => 'conversion', 'conversion_year' => 2012, 'conversion_place' => 'Cotonou', 'conversion_verse' => 'Jean 3:16',
        ], $extra);
    }

    private function createConversion(?User $by = null, array $extra = []): Testimony
    {
        Sanctum::actingAs($by ?? $this->person);
        $id = $this->postJson('/api/v1/testimonies', $this->payload($extra))->assertCreated()->json('data.id');

        return Testimony::findOrFail($id);
    }

    public function test_individual_publishes_a_conversion_testimony_with_its_own_fields(): void
    {
        Sanctum::actingAs($this->person);
        $this->postJson('/api/v1/testimonies', $this->payload())->assertCreated()
            ->assertJsonPath('data.kind', 'conversion')
            ->assertJsonPath('data.isConversion', true)
            ->assertJsonPath('data.conversion', ['year' => 2012, 'place' => 'Cotonou', 'verse' => 'Jean 3:16'])
            ->assertJsonPath('data.category', 'conversion') // catégorie par défaut
            ->assertJsonPath('data.status', 'pending');     // même modération

        // Les champs sont facultatifs.
        Testimony::query()->delete();
        $this->postJson('/api/v1/testimonies', ['title' => 'Ma conversion', 'type' => 'text', 'body_text' => 'Texte', 'kind' => 'conversion'])
            ->assertCreated()->assertJsonPath('data.conversion', ['year' => null, 'place' => null, 'verse' => null]);

        // Témoignage standard : kind par défaut, pas de détails.
        $this->postJson('/api/v1/testimonies', ['title' => 'Guérison', 'type' => 'text', 'body_text' => 'Texte', 'category' => 'guerison', 'conversion_year' => 2001])
            ->assertCreated()->assertJsonPath('data.kind', 'standard')->assertJsonPath('data.conversion', null);
        $this->assertNull(Testimony::where('title', 'Guérison')->value('conversion_year'));
    }

    public function test_only_one_conversion_testimony_per_account(): void
    {
        $first = $this->createConversion();

        $this->postJson('/api/v1/testimonies', $this->payload(['title' => 'Encore']))->assertStatus(409)
            ->assertJsonPath('errors.conversion_testimony_id.0', $first->id)
            ->assertJsonPath('message', "Vous avez déjà un témoignage de conversion : modifiez-le plutôt que d'en publier un autre.");
        $this->postJson('/api/v1/testimonies', $this->payload(['title' => 'Encore']), ['Accept-Language' => 'en'])->assertStatus(409)
            ->assertJsonPath('message', 'You already have a conversion story: edit it instead of publishing another one.');

        // Un témoignage standard ne peut pas devenir un second témoignage de conversion.
        $standard = $this->postJson('/api/v1/testimonies', ['title' => 'Autre', 'type' => 'text', 'body_text' => 'Texte', 'category' => 'guerison'])
            ->assertCreated()->json('data.id');
        $this->putJson("/api/v1/testimonies/{$standard}", ['kind' => 'conversion'])->assertStatus(409);

        // Dans la corbeille, il ne compte plus.
        $first->delete();
        $this->postJson('/api/v1/testimonies', $this->payload(['title' => 'Nouveau départ']))->assertCreated();
        $this->assertSame(1, Testimony::conversion()->count());
    }

    public function test_organizations_cannot_publish_a_conversion_testimony(): void
    {
        Sanctum::actingAs($this->org);
        $this->postJson('/api/v1/testimonies', $this->payload())->assertForbidden()
            ->assertJsonPath('message', 'Seul un compte personnel peut publier un témoignage de conversion.');
        $this->assertSame(0, Testimony::count());
    }

    public function test_conversion_fields_are_validated(): void
    {
        Sanctum::actingAs($this->person);
        $this->postJson('/api/v1/testimonies', $this->payload(['conversion_verse' => 'Pas une référence']))->assertUnprocessable()
            ->assertJsonValidationErrors(['conversion_verse' => 'Référence biblique non reconnue (exemple : Jean 3:16).']);
        $this->postJson('/api/v1/testimonies', $this->payload(['conversion_year' => now()->year + 1]))->assertUnprocessable()
            ->assertJsonValidationErrors('conversion_year');
        $this->postJson('/api/v1/testimonies', $this->payload(['kind' => 'autre']))->assertUnprocessable()->assertJsonValidationErrors('kind');
    }

    public function test_author_edits_the_conversion_testimony_and_it_goes_back_to_moderation(): void
    {
        $testimony = $this->createConversion();
        app(TestimonyModeration::class)->approve($testimony, $this->moderator);

        Sanctum::actingAs($this->person);
        $this->putJson("/api/v1/testimonies/{$testimony->id}", ['conversion_place' => 'Porto-Novo', 'conversion_year' => 2013])->assertOk()
            ->assertJsonPath('data.conversion', ['year' => 2013, 'place' => 'Porto-Novo', 'verse' => 'Jean 3:16'])
            ->assertJsonPath('data.status', config('testimonies.require_moderation', true) ? 'pending' : 'approved');

        // Redevenu standard : détails effacés.
        $this->putJson("/api/v1/testimonies/{$testimony->id}", ['kind' => 'standard'])->assertOk()
            ->assertJsonPath('data.kind', 'standard')->assertJsonPath('data.conversion', null);
        $this->assertNull($testimony->fresh()->conversion_place);
    }

    public function test_endpoints_list_and_expose_the_conversion_testimony(): void
    {
        $testimony = $this->createConversion();
        $this->app['auth']->forgetGuards();

        // En attente : rien de public.
        $this->getJson('/api/v1/testimonies?kind=conversion')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/users/{$this->person->id}/conversion-testimony")->assertNotFound();
        $this->getJson("/api/v1/users/{$this->person->id}")->assertJsonPath('data.has_conversion_testimony', false);

        // Mon témoignage, quel que soit son statut.
        Sanctum::actingAs($this->person);
        $this->getJson('/api/v1/me/conversion-testimony')->assertOk()
            ->assertJsonPath('data.id', $testimony->id)->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('can_create', false);

        app(TestimonyModeration::class)->approve($testimony, $this->moderator);
        $standard = Testimony::create(['user_id' => $this->person->id, 'title' => 'Standard', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'approved', 'approved_at' => now()]);
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/testimonies?kind=conversion')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $testimony->id);
        $this->getJson('/api/v1/testimonies?kind=standard')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $standard->id);
        $this->getJson("/api/v1/users/{$this->person->id}/conversion-testimony")->assertOk()
            ->assertJsonPath('data.id', $testimony->id)->assertJsonPath('data.conversion.verse', 'Jean 3:16');
        $this->getJson("/api/v1/users/{$this->person->id}")->assertJsonPath('data.has_conversion_testimony', true);
        $this->getJson('/api/v1/users/00000000-0000-0000-0000-000000000000/conversion-testimony')->assertNotFound();
    }

    public function test_my_conversion_testimony_is_null_until_created(): void
    {
        Sanctum::actingAs($this->person);
        $this->getJson('/api/v1/me/conversion-testimony')->assertOk()
            ->assertJsonPath('data', null)->assertJsonPath('can_create', true);

        Sanctum::actingAs($this->org);
        $this->getJson('/api/v1/me/conversion-testimony')->assertOk()->assertJsonPath('can_create', false);
    }

    public function test_website_section_cards_and_profile_highlight(): void
    {
        $testimony = $this->createConversion();
        app(TestimonyModeration::class)->approve($testimony, $this->moderator);
        $this->app['auth']->forgetGuards();

        $this->get('/temoignages-de-conversion')->assertOk()
            ->assertSee('Témoignages de conversion')
            ->assertSee($testimony->title)
            ->assertSee('Converti en 2012')
            ->assertSee('ring-sun-400', false);

        $this->from('/')->post('/langue', ['locale' => 'en']);
        $this->get('/temoignages-de-conversion')->assertOk()->assertSee('Conversion stories')->assertSee('Saved in 2012');
        $this->post('/langue', ['locale' => 'fr']);

        // Profil de l'auteur : mise en avant.
        $this->get("/profiles/{$this->person->id}")->assertOk()
            ->assertSee('Témoignage de conversion')->assertSee($testimony->title)->assertSee('Jean 3:16');
        // Page du témoignage : badge et détails.
        $this->get("/testimonies/{$testimony->id}")->assertOk()->assertSee('Témoignage de conversion')->assertSee('À Cotonou');
    }

    public function test_website_publish_form_creates_and_edits_the_conversion_testimony(): void
    {
        $this->actingAs($this->person)->get('/publish?kind=conversion')->assertOk()
            ->assertSee('type="radio" name="kind"', false)->assertSee('name="conversion_verse"', false);

        $this->actingAs($this->person)->post('/testimonies', [
            'title' => 'Ma rencontre avec Jésus', 'type' => 'text', 'body_text' => 'Ce soir-là…', 'kind' => 'conversion',
            'conversion_year' => 2015, 'conversion_verse' => 'Romains 10:9', 'consent_given' => '1',
        ])->assertRedirect();
        $testimony = Testimony::conversion()->where('user_id', $this->person->id)->firstOrFail();
        $this->assertSame('conversion', $testimony->category_slug);
        $this->assertSame(2015, $testimony->conversion_year);

        // Formulaire : plus de choix, lien vers la modification.
        $this->actingAs($this->person)->get('/publish')->assertOk()->assertDontSee('type="radio" name="kind"', false)
            ->assertSee(route('testimonies.conversion.edit'), false);

        // Second envoi refusé.
        $this->actingAs($this->person)->from('/publish')->post('/testimonies', [
            'title' => 'Encore', 'type' => 'text', 'body_text' => 'Texte', 'kind' => 'conversion', 'consent_given' => '1',
        ])->assertRedirect('/publish')->assertSessionHasErrors('kind');

        // Modification.
        $this->actingAs($this->person)->get('/temoignages-de-conversion/le-mien/modifier')->assertOk()->assertSee('Romains 10:9');
        $this->actingAs($this->person)->put('/temoignages-de-conversion/le-mien', [
            'title' => 'Ma rencontre avec Jésus', 'body_text' => 'Ce soir-là, tout a changé.', 'conversion_place' => 'Abomey',
            'conversion_year' => 2015, 'conversion_verse' => 'Romains 10:9',
        ])->assertRedirect(route('testimonies.show', $testimony->id));
        $this->assertSame('Abomey', $testimony->fresh()->conversion_place);

        // Organisation : pas de choix « conversion », envoi refusé.
        $this->actingAs($this->org)->get('/publish?kind=conversion')->assertOk()->assertDontSee('type="radio" name="kind"', false);
        $this->actingAs($this->org)->from('/publish')->post('/testimonies', [
            'title' => 'Org', 'type' => 'text', 'body_text' => 'Texte', 'kind' => 'conversion', 'consent_given' => '1',
        ])->assertSessionHasErrors(['kind' => 'Seul un compte personnel peut publier un témoignage de conversion.']);
    }

    public function test_moderation_shows_and_filters_conversion_testimonies(): void
    {
        $conversion = $this->createConversion();
        $standard = Testimony::create(['user_id' => $this->person->id, 'title' => 'Guérison de Paul', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'pending']);

        $this->actingAs($this->moderator)->get('/moderation?conversion=1')->assertOk()
            ->assertSee($conversion->title)->assertDontSee('Guérison de Paul')->assertSee('fa-dove', false);
        $this->actingAs($this->moderator)->get('/moderation')->assertOk()->assertSee('Guérison de Paul');

        // Fiche : badge et détails.
        $this->actingAs($this->moderator)->get("/moderation/{$conversion->id}")->assertOk()
            ->assertSee('Converti en 2012 · Cotonou · Jean 3:16');
    }

    public function test_home_shelf_and_guest_invitation(): void
    {
        $testimony = $this->createConversion();
        $this->app['auth']->forgetGuards();

        // En attente : pas sur l'accueil.
        $this->get('/')->assertOk()->assertDontSee(route('testimonies.conversions') . '" class="inline-flex', false);

        app(TestimonyModeration::class)->approve($testimony, $this->moderator);
        $this->get('/')->assertOk()->assertSee('Témoignages de conversion')->assertSee($testimony->title);

        // Visiteur : invitation à se connecter ; membre connecté avec un témoignage : lien vers le sien.
        $this->get('/temoignages-de-conversion')->assertOk()->assertSee('Se connecter pour raconter ma conversion');
        $this->actingAs($this->person)->get('/temoignages-de-conversion')->assertOk()
            ->assertDontSee('Se connecter pour raconter ma conversion')->assertSee('Voir mon témoignage de conversion');
        $other = $this->user('paul');
        $this->actingAs($other)->get('/temoignages-de-conversion')->assertOk()->assertSee('Raconter ma conversion');
    }
}
