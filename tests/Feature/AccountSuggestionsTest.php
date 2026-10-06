<?php

namespace Tests\Feature;

use App\Models\Testimony;
use App\Models\User;
use App\Services\AccountSuggestions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** « Comptes à suivre » (API, site). docs/fonctionnalites/abonnements.md */
class AccountSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => $name, 'email' => str()->slug($name) . '@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    private function org(string $name, string $status = 'verified'): User
    {
        return $this->user($name, ['account_type' => 'organization', 'organization_name' => $name, 'verification_status' => $status]);
    }

    private function publish(User $author, int $count, int $daysAgo = 5, string $category = 'guerison'): void
    {
        for ($i = 0; $i < $count; $i++) {
            Testimony::create([
                'user_id' => $author->id, 'title' => "T{$i}", 'type' => 'text', 'category_slug' => $category,
                'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'approved',
                'approved_at' => now()->subDays($daysAgo), 'views_count' => 10,
            ]);
        }
    }

    private function follow(User $a, User $b): void
    {
        DB::table('follows')->insert(['follower_id' => $a->id, 'following_id' => $b->id, 'created_at' => now()]);
    }

    public function test_guest_gets_verified_and_active_accounts_without_contact_details(): void
    {
        $verified = $this->org('Église Vérifiée');
        $active = $this->user('Témoin Actif');
        $this->publish($active, 6);
        $quiet = $this->user('Sans témoignage');
        $this->org('Refusée', 'rejected');
        $suspended = $this->user('Suspendu', ['status' => 'suspended']);
        $this->publish($suspended, 3);

        $res = $this->getJson('/api/v1/users/suggestions?limit=10')->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$verified->id, $active->id], $ids);
        $this->assertNotContains($quiet->id, $ids);
        $byId = collect($res->json('data'))->keyBy('id');
        $this->assertTrue($byId[$verified->id]['isVerified']);
        $this->assertTrue($byId[$verified->id]['isOrganization']);
        $this->assertFalse($byId[$verified->id]['isFollowing']);
        $this->assertSame('Organisation vérifiée', $byId[$verified->id]['reason']);
        $this->assertSame(6, $byId[$active->id]['testimonyCount']);
        $this->assertSame('Publie souvent des témoignages', $byId[$active->id]['reason']);
        $this->assertArrayNotHasKey('email', $byId[$active->id]);
        $this->assertStringNotContainsString('@example.com', $res->getContent());
    }

    public function test_excludes_me_and_accounts_i_follow_even_after_caching(): void
    {
        $me = $this->user('Moi');
        $this->publish($me, 4);
        $a = $this->user('A');
        $this->publish($a, 2);
        $b = $this->user('B');
        $this->publish($b, 2);
        $this->follow($me, $a);

        $ids = collect($this->actingAs($me)->getJson('/api/v1/users/suggestions')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$b->id], $ids);

        // Abonnement fait après la mise en cache : retiré sans attendre l'expiration.
        $this->actingAs($me)->postJson("/api/v1/users/{$b->id}/follow")->assertOk();
        $this->actingAs($me)->getJson('/api/v1/users/suggestions')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_followed_by_people_i_follow_ranks_first_with_reason(): void
    {
        $me = $this->user('Moi');
        $friend = $this->user('Amie Grace');
        $this->follow($me, $friend);
        $popular = $this->user('Très actif');
        $this->publish($popular, 8);
        $friendOfFriend = $this->user('Ami commun');
        $this->publish($friendOfFriend, 1, 300);
        $this->follow($friend, $friendOfFriend);

        $res = $this->actingAs($me)->getJson('/api/v1/users/suggestions')->assertOk();
        $res->assertJsonPath('data.0.id', $friendOfFriend->id)
            ->assertJsonPath('data.0.reason', 'Suivi par Amie Grace que vous suivez');
    }

    public function test_type_filter_and_recent_activity_weighting(): void
    {
        Cache::flush();
        $org = $this->org('Ministère');
        $recent = $this->user('Récent');
        $this->publish($recent, 3, 3);
        $old = $this->user('Ancien');
        $this->publish($old, 3, 400);

        $people = collect($this->getJson('/api/v1/users/suggestions?type=people')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$recent->id, $old->id], $people);

        $orgs = collect($this->getJson('/api/v1/users/suggestions?type=organizations')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$org->id], $orgs);

        $this->getJson('/api/v1/users/suggestions?type=autre')->assertStatus(422);
    }

    public function test_web_partial_renders_without_variables_and_hides_when_empty(): void
    {
        $this->assertSame('', trim(view('community.partials.suggested')->render()));

        $org = $this->org('Église de la Paix');
        Cache::flush(); // le classement « invité » vide est en cache
        $html = view('community.partials.suggested')->render();
        $this->assertStringContainsString('Comptes à suivre', $html);
        $this->assertStringContainsString('Église de la Paix', $html);
        $this->assertStringContainsString(route('profiles.show', $org->id), $html);

        $me = $this->user('Moi');
        $this->actingAs($me)->get('/communaute')->assertOk()->assertSee('Suggestions pour vous');
        $this->assertInstanceOf(AccountSuggestions::class, app(AccountSuggestions::class));
    }
}
