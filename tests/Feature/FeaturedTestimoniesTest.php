<?php

namespace Tests\Feature;

use App\Http\Resources\TestimonyResource;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class FeaturedTestimoniesTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::create(['display_name' => 'Auteur', 'email' => 'a@example.com', 'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active']);
    }

    private function testimony(string $title, array $attrs = []): Testimony
    {
        $t = Testimony::create(array_merge([
            'user_id' => $this->author->id, 'title' => $title, 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'approved', 'is_featured' => false,
        ], $attrs));

        // created_at / approved_at ne sont pas « fillable » pour tous : on les force.
        foreach (['created_at', 'approved_at'] as $col) {
            if (array_key_exists($col, $attrs)) {
                $t->forceFill([$col => $attrs[$col]])->saveQuietly();
            }
        }

        return $t->fresh();
    }

    public function test_recent_manual_and_old_testimonies(): void
    {
        $recent   = $this->testimony('Récent', ['approved_at' => now()->subDays(2), 'created_at' => now()->subDays(3)]);
        $lateOk   = $this->testimony('Soumis il y a longtemps, approuvé hier', ['created_at' => now()->subDays(20), 'approved_at' => now()->subDay()]);
        $noApprov = $this->testimony('Sans date d’approbation, créé il y a 3 jours', ['created_at' => now()->subDays(3), 'approved_at' => null]);
        $pinned   = $this->testimony('Ancien mis en avant', ['is_featured' => true, 'created_at' => now()->subMonths(3), 'approved_at' => now()->subMonths(3)]);
        $old      = $this->testimony('Ancien', ['created_at' => now()->subDays(10), 'approved_at' => now()->subDays(8)]);
        $private  = $this->testimony('Récent mais privé', ['visibility' => 'private', 'approved_at' => now()->subDay()]);
        $pending  = $this->testimony('Récent en attente', ['status' => 'pending', 'approved_at' => null]);

        $featured = Testimony::featured()->latestPublished()->pluck('title')->all();

        // Ordre : du plus récemment publié au plus ancien.
        $this->assertSame([$lateOk->title, $recent->title, $noApprov->title, $pinned->title], $featured);

        $this->assertTrue($recent->isCurrentlyFeatured());
        $this->assertTrue($pinned->isCurrentlyFeatured());
        $this->assertFalse($old->isCurrentlyFeatured());
        $this->assertFalse($pending->isCurrentlyFeatured());
        $this->assertNotContains($private->title, $featured);
    }

    public function test_api_exposes_computed_and_manual_flags(): void
    {
        $recent = $this->testimony('Récent', ['approved_at' => now()->subDay()])->load('user');
        $data   = (new TestimonyResource($recent))->toArray(Request::create('/'));

        $this->assertTrue($data['isFeatured']);
        $this->assertFalse($data['isPinned']);
    }

    public function test_featured_after_seven_days_is_no_longer_featured(): void
    {
        $t = $this->testimony('Limite', ['approved_at' => now()->subDays(7)->subMinute()]);
        $this->assertFalse($t->isCurrentlyFeatured());
        $this->assertFalse(Testimony::featured()->whereKey($t->id)->exists());
    }

    public function test_home_page_shows_recent_testimony_in_featured_section(): void
    {
        $this->testimony('Témoignage de cette semaine', ['approved_at' => now()->subDay()]);

        $this->get('/')->assertOk()->assertSeeInOrder(['À la une', 'Témoignage de cette semaine']);
    }
}
