<?php

namespace Tests\Feature;

use App\Models\Testimony;
use App\Models\User;
use App\Services\FollowService;
use App\Services\Recommendations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Recommandations et fil « Pour vous ». docs/fonctionnalites/recommandations.md */
class RecommendationsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, string $role = 'utilisateur'): User
    {
        return User::create([
            'display_name' => $name, 'email' => str()->slug($name) . '@example.com', 'password' => 'secret123',
            'role' => $role, 'status' => 'active',
        ]);
    }

    private function testimony(User $author, string $title, string $category, array $attrs = []): Testimony
    {
        return Testimony::create(array_merge([
            'user_id' => $author->id, 'title' => $title, 'type' => 'video', 'category_slug' => $category,
            'media_url' => 'https://example.com/v.mp4', 'visibility' => 'public', 'status' => 'approved',
            'approved_at' => now()->subDays(40), 'views_count' => 10,
        ], $attrs));
    }

    public function test_newcomer_feed_alternates_most_recent_and_most_viewed(): void
    {
        $a = $this->user('Auteur');
        $old = $this->testimony($a, 'Ancien très vu', 'guerison', ['views_count' => 9000, 'approved_at' => now()->subYear()]);
        $this->testimony($a, 'Moyen', 'guerison', ['approved_at' => now()->subMonths(3)]);
        $new = $this->testimony($a, 'Tout nouveau', 'famille', ['approved_at' => now()->subHour()]);

        $ids = app(Recommendations::class)->feed(null, 1, 2)->pluck('id')->all();
        $this->assertSame([$new->id, $old->id], $ids);

        $this->getJson('/api/v1/testimonies?sort=for_you&limit=2')->assertOk()
            ->assertJsonPath('data.0.id', $new->id)->assertJsonPath('data.1.id', $old->id)
            ->assertJsonPath('meta.total', 3)->assertJsonPath('meta.lastPage', 2);
        // Page 2 : la suite, sans doublon
        $this->getJson('/api/v1/testimonies?sort=for_you&limit=2&page=2')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Moyen');
    }

    public function test_feed_follows_interests_and_followed_accounts(): void
    {
        $me = $this->user('Moi');
        $church = $this->user('Église');
        $other = $this->user('Autre');
        $liked = $this->testimony($other, 'Guérison aimée', 'guerison');
        $this->testimony($other, 'Famille populaire', 'famille', ['views_count' => 5000]);
        $fromChurch = $this->testimony($church, "Message de l'église", 'finances');
        $guerison2 = $this->testimony($other, 'Autre guérison', 'guerison');

        DB::table('reactions')->insert(['id' => (string) str()->uuid(), 'user_id' => $me->id, 'testimony_id' => $liked->id, 'type' => 'like', 'created_at' => now(), 'updated_at' => now()]);
        app(FollowService::class)->follow($me, $church);

        $titles = app(Recommendations::class)->feed($me, 1, 4)->pluck('title')->all();
        $this->assertSame([$fromChurch->title, $guerison2->title], array_slice(array_diff($titles, [$liked->title]), 0, 2));
        $this->assertFalse(app(Recommendations::class)->isNewcomer($me));
    }

    public function test_recommendations_prefer_same_category_author_and_skip_seen(): void
    {
        $me = $this->user('Moi');
        $author = $this->user('Auteur');
        $current = $this->testimony($author, 'En cours', 'guerison', ['tags' => ['paludisme']]);
        $sameCat = $this->testimony($this->user('X'), 'Même catégorie', 'guerison', ['tags' => ['paludisme']]);
        $seen = $this->testimony($this->user('Y'), 'Même catégorie déjà vue', 'guerison', ['tags' => ['paludisme']]);
        $this->testimony($this->user('Z'), 'Sans rapport', 'emploi', ['type' => 'text', 'media_url' => null]);

        Recommendations::recordView($me, $seen);

        $list = app(Recommendations::class)->forTestimony($current, $me, 3)->pluck('title')->all();
        $this->assertSame('Même catégorie', $list[0]);
        $this->assertLessThan(array_search('Même catégorie déjà vue', $list, true), 0);
        $this->assertNotContains('En cours', $list);

        $this->getJson("/api/v1/testimonies/{$current->id}/recommendations?limit=2")->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('data.0.title', 'Même catégorie');
        // Page de lecture : « À regarder également » suit les recommandations
        $this->get("/testimonies/{$current->id}")->assertOk()->assertSeeInOrder(['À regarder également', 'Même catégorie']);
    }

    public function test_views_are_recorded_for_signed_in_people(): void
    {
        $me = $this->user('Moi');
        $t = $this->testimony($this->user('A'), 'Vu', 'guerison');

        $this->actingAs($me)->getJson("/api/v1/testimonies/{$t->id}")->assertOk();
        $this->actingAs($me)->getJson("/api/v1/testimonies/{$t->id}")->assertOk();
        $this->assertSame(2, (int) DB::table('testimony_views')->where('user_id', $me->id)->value('view_count'));
        $this->assertSame(['guerison' => 1.0], app(Recommendations::class)->interests($me));
    }
}
