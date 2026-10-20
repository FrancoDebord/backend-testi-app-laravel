<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Testimony;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Types de témoignages (catégories) : nom anglais facultatif, nom localisé dans l'API et sur le site,
 * administration (API et site). Voir docs/fonctionnalites/categories-de-temoignages.md
 */
class CategoryNamesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Category $healing;
    private Category $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['display_name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret123', 'role' => 'administrateur', 'status' => 'active']);
        $this->healing = Category::create(['name' => 'Guérison', 'name_en' => 'Healing', 'slug' => 'guerison', 'is_active' => true, 'display_order' => 1]);
        $this->other   = Category::create(['name' => 'Louange', 'slug' => 'louange', 'is_active' => true, 'display_order' => 2]);
    }

    public function test_display_name_follows_the_locale_and_falls_back_to_french(): void
    {
        app()->setLocale('en');
        $this->assertSame('Healing', $this->healing->displayName());
        $this->assertSame('Healing', $this->healing->localized_name);
        $this->assertSame('Louange', $this->other->displayName());

        app()->setLocale('fr');
        $this->assertSame('Guérison', $this->healing->displayName());
    }

    public function test_public_categories_are_localized_with_accept_language(): void
    {
        $this->getJson('/api/v1/categories', ['Accept-Language' => 'en'])->assertOk()
            ->assertJsonPath('data.0.name', 'Healing')
            ->assertJsonPath('data.0.nameFr', 'Guérison')
            ->assertJsonPath('data.0.nameEn', 'Healing')
            ->assertJsonPath('data.0.slug', 'guerison')
            ->assertJsonPath('data.1.name', 'Louange')
            ->assertJsonPath('data.1.nameEn', null);

        $this->getJson('/api/v1/categories', ['Accept-Language' => 'fr'])->assertOk()
            ->assertJsonPath('data.0.name', 'Guérison')
            ->assertJsonPath('data.0.nameEn', 'Healing');

        $this->getJson('/api/v1/categories/guerison', ['Accept-Language' => 'en'])->assertOk()
            ->assertJsonPath('data.name', 'Healing');
    }

    public function test_admin_api_creates_and_updates_the_english_name(): void
    {
        $this->actingAs($this->admin);

        $id = $this->postJson('/api/v1/admin/categories', ['name' => 'Délivrance', 'name_en' => 'Deliverance', 'slug' => 'delivrance'], ['Accept-Language' => 'en'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Deliverance')
            ->assertJsonPath('data.nameFr', 'Délivrance')
            ->assertJsonPath('data.nameEn', 'Deliverance')
            ->json('data.id');
        $this->assertSame('Deliverance', Category::find($id)->name_en);

        $this->postJson('/api/v1/admin/categories', ['name' => 'Trop long', 'name_en' => str_repeat('a', 101)])
            ->assertUnprocessable()->assertJsonValidationErrors(['name_en']);

        $this->putJson("/api/v1/admin/categories/{$id}", ['name_en' => 'Freedom'])->assertOk()->assertJsonPath('data.nameEn', 'Freedom');
        // Sans name_en : inchangé.
        $this->putJson("/api/v1/admin/categories/{$id}", ['display_order' => 5])->assertOk()->assertJsonPath('data.nameEn', 'Freedom');
        // Chaîne vide : effacé, repli sur le nom français.
        $this->putJson("/api/v1/admin/categories/{$id}", ['name_en' => ''], ['Accept-Language' => 'en'])->assertOk()
            ->assertJsonPath('data.nameEn', null)->assertJsonPath('data.name', 'Délivrance');
        $this->assertNull(Category::find($id)->name_en);

        $this->getJson('/api/v1/admin/categories')->assertOk()
            ->assertJsonPath('data.0.nameFr', 'Guérison')->assertJsonPath('data.0.nameEn', 'Healing');
    }

    public function test_website_admin_form_saves_and_lists_the_english_name(): void
    {
        $this->actingAs($this->admin)->get(route('admin.categories.index'))->assertOk()
            ->assertSee('Nom anglais (facultatif)')->assertSee('name="name_en"', false)->assertSee('Healing');

        $this->post(route('admin.categories.store'), ['_form' => 'create', 'name' => 'Famille', 'name_en' => 'Family', 'slug' => 'famille'])
            ->assertRedirect()->assertSessionHas('success');
        $family = Category::where('slug', 'famille')->firstOrFail();
        $this->assertSame('Family', $family->name_en);

        $this->put(route('admin.categories.update', $family->id), ['name' => 'Famille', 'name_en' => '', 'is_active' => 1])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertNull($family->fresh()->name_en);

        $this->admin->update(['locale' => 'en']);
        $this->get(route('admin.categories.index'))->assertOk()->assertSee('English name (optional)');
    }

    public function test_website_shows_the_english_name_in_english(): void
    {
        $author = User::create(['display_name' => 'Marie', 'email' => 'marie@example.com', 'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active']);
        Testimony::create([
            'user_id' => $author->id, 'title' => 'Guéri du paludisme', 'type' => 'text', 'category_slug' => 'guerison',
            'category_id' => $this->healing->id, 'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ]);

        $this->from('/')->post('/langue', ['locale' => 'en']);
        $this->get('/explore')->assertOk()->assertSee('Healing')->assertDontSee('Guérison');

        $this->post('/langue', ['locale' => 'fr']);
        $this->get('/explore')->assertOk()->assertSee('Guérison');
    }

    public function test_seeder_fills_english_names_of_known_slugs(): void
    {
        Category::query()->delete();
        $this->seed(CategorySeeder::class);

        $this->assertSame('Healing', Category::where('slug', 'guerison')->value('name_en'));
        $this->assertSame('Divine protection', Category::where('slug', 'protection_divine')->value('name_en'));
        $this->assertSame(0, Category::whereNull('name_en')->count());
    }
}
