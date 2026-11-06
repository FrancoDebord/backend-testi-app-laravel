<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nombre de témoignages d'une catégorie (GET /categories, testimonyCount) : le même que la
 * liste GET /testimonies?category= (témoignages publiés seulement). Tris most_prayed / most_liked.
 */
class CategoryCountTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::create(['display_name' => 'Auteur', 'email' => 'auteur@example.com', 'password' => 'secret123', 'status' => 'active']);
        // Compteur stocké volontairement faux : il ne doit plus être affiché.
        Category::create(['name' => 'Guérison', 'slug' => 'guerison', 'is_active' => true, 'display_order' => 1, 'testimony_count' => 9]);
        Category::create(['name' => 'Emploi', 'slug' => 'emploi', 'is_active' => true, 'display_order' => 2]);
    }

    private function testimony(string $slug, array $attributes = []): Testimony
    {
        return Testimony::create($attributes + [
            'user_id' => $this->author->id, 'title' => 'Témoignage', 'type' => 'text', 'category_slug' => $slug,
            'category_id' => Category::where('slug', $slug)->value('id'), 'body_text' => 'Texte',
            'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ]);
    }

    public function test_count_matches_the_published_list_of_the_category(): void
    {
        $this->testimony('guerison');
        $this->testimony('guerison');
        $this->testimony('guerison', ['status' => 'pending', 'approved_at' => null]);
        $this->testimony('guerison', ['visibility' => 'private']);
        $this->testimony('emploi');

        $categories = collect($this->getJson('/api/v1/categories')->assertOk()->json('data'))->keyBy('slug');
        $this->assertSame(2, $categories['guerison']['testimonyCount']);
        $this->assertSame(1, $categories['emploi']['testimonyCount']);

        foreach (['guerison', 'emploi'] as $slug) {
            $this->assertSame(
                $categories[$slug]['testimonyCount'],
                $this->getJson("/api/v1/testimonies?category={$slug}")->assertOk()->json('meta.total'),
            );
        }
    }

    public function test_most_prayed_and_most_liked_sorts(): void
    {
        $prayed = $this->testimony('guerison', ['prayer_count' => 7, 'like_count' => 1]);
        $liked  = $this->testimony('guerison', ['prayer_count' => 1, 'like_count' => 9]);

        $this->assertSame([$prayed->id, $liked->id],
            collect($this->getJson('/api/v1/testimonies?category=guerison&sort=most_prayed')->json('data'))->pluck('id')->all());
        $this->assertSame([$liked->id, $prayed->id],
            collect($this->getJson('/api/v1/testimonies?category=guerison&sort=most_liked')->json('data'))->pluck('id')->all());
    }
}
