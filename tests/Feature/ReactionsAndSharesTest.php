<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Event;
use App\Models\PrayerRequest;
use App\Models\PrayerSession;
use App\Models\Reaction;
use App\Models\Testimony;
use App\Models\User;
use App\Services\FollowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Réactions comptées une seule fois, suppression par la modération, partages et réactions des
 * événements / requêtes / sessions. docs/fonctionnalites/reactions.md, partage-interne.md
 */
class ReactionsAndSharesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => $name, 'email' => str()->slug($name) . '@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    private function testimony(User $author): Testimony
    {
        return Testimony::create([
            'user_id' => $author->id, 'title' => 'Guérison', 'type' => 'text', 'category_slug' => 'autre',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ]);
    }

    private function event(User $organizer): Event
    {
        return Event::create([
            'organizer_id' => $organizer->id, 'title' => 'Grande croisade', 'type' => 'crusade',
            'status' => 'published', 'starts_at' => now()->addDays(10), 'ends_at' => now()->addDays(12),
        ]);
    }

    // ─── Témoignages : une réaction par groupe ───────────────────────────────

    public function test_liking_twice_or_switching_reaction_never_counts_twice(): void
    {
        $author = $this->user('Auteur');
        $me = $this->user('Moi');
        $t = $this->testimony($author);
        $url = "/api/v1/testimonies/{$t->id}/reactions";

        $this->actingAs($me)->postJson($url, ['type' => 'like'])->assertCreated()->assertJsonPath('data.likesCount', 1);
        $this->actingAs($me)->postJson($url, ['type' => 'like'])->assertOk()->assertJsonPath('data.likesCount', 1);
        // Changer pour « feu » remplace le j'aime.
        $this->actingAs($me)->postJson($url, ['type' => 'fire'])->assertOk()
            ->assertJsonPath('data.likesCount', 1)->assertJsonPath('data.myReaction', 'fire');
        // « Je prie » est un autre groupe ; « amen » le remplace.
        $this->actingAs($me)->postJson($url, ['type' => 'pray'])->assertCreated()->assertJsonPath('data.prayersCount', 1);
        $this->actingAs($me)->postJson($url, ['type' => 'amen'])->assertOk()->assertJsonPath('data.prayersCount', 1);

        $this->assertSame(2, Reaction::where('user_id', $me->id)->count());
        $this->assertSame(1, AppNotification::where('recipient_id', $author->id)->where('type', 'like')->count());

        $this->actingAs($me)->getJson("/api/v1/testimonies/{$t->id}")->assertOk()
            ->assertJsonPath('data.isLikedByMe', true)->assertJsonPath('data.myReaction', 'fire')
            ->assertJsonPath('data.isPrayedByMe', true)->assertJsonPath('data.myPrayerReaction', 'amen');

        // Retrait sans identifiant, par groupe.
        $this->actingAs($me)->deleteJson("{$url}?group=appreciation")->assertOk()
            ->assertJsonPath('data.likesCount', 0)->assertJsonPath('data.prayersCount', 1);
        $this->actingAs($me)->deleteJson("{$url}?group=appreciation")->assertOk()->assertJsonPath('data.likesCount', 0);
    }

    public function test_reaction_state_is_returned_on_public_lists(): void
    {
        $me = $this->user('Moi');
        $t = $this->testimony($this->user('Auteur'));
        $this->actingAs($me)->postJson("/api/v1/testimonies/{$t->id}/reactions", ['type' => 'love'])->assertCreated();

        $item = collect($this->actingAs($me)->getJson('/api/v1/testimonies')->assertOk()->json('data'))->firstWhere('id', $t->id);
        $this->assertTrue($item['isLikedByMe']);
        $this->assertSame('love', $item['myReaction']);
    }

    public function test_web_toggle_keeps_one_reaction_per_group(): void
    {
        $me = $this->user('Moi');
        $t = $this->testimony($this->user('Auteur'));

        $this->actingAs($me)->postJson("/testimonies/{$t->id}/reactions", ['type' => 'like'], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJsonPath('like_count', 1);
        $this->actingAs($me)->postJson("/testimonies/{$t->id}/reactions", ['type' => 'love'], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJsonPath('like_count', 1)->assertJsonPath('my_reactions', ['love']);
        $this->actingAs($me)->postJson("/testimonies/{$t->id}/reactions", ['type' => 'love'], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJsonPath('like_count', 0)->assertJsonPath('reacted', false);
    }

    // ─── Suppression par la modération ───────────────────────────────────────

    public function test_moderator_never_deletes_what_someone_else_submitted(): void
    {
        $author = $this->user('Auteur');
        $moderator = $this->user('Modo', ['role' => 'moderateur']);
        $admin = $this->user('Admin', ['role' => 'administrateur']);

        $t = $this->testimony($author);
        $this->actingAs($moderator)->getJson("/api/v1/testimonies/{$t->id}")->assertJsonPath('data.canDelete', false);
        $this->actingAs($moderator)->deleteJson("/api/v1/testimonies/{$t->id}")->assertForbidden();
        $this->actingAs($moderator)->delete("/testimonies/{$t->id}")->assertForbidden();
        $this->actingAs($author)->getJson("/api/v1/testimonies/{$t->id}")->assertJsonPath('data.canDelete', true);

        $event = $this->event($author);
        $this->actingAs($moderator)->getJson("/api/v1/events/{$event->id}")->assertJsonPath('data.canDelete', false);
        $this->actingAs($moderator)->deleteJson("/api/v1/events/{$event->id}")->assertForbidden();

        $request = PrayerRequest::create(['user_id' => $author->id, 'body' => 'Priez', 'visibility' => 'public']);
        $this->actingAs($moderator)->getJson("/api/v1/prayer/requests/{$request->id}")->assertJsonPath('data.canDelete', false);
        $this->actingAs($moderator)->deleteJson("/api/v1/prayer/requests/{$request->id}")->assertForbidden();

        $session = PrayerSession::create([
            'host_id' => $author->id, 'title' => 'Veillée', 'starts_at' => now()->addDay(),
            'duration_minutes' => 60, 'ends_at' => now()->addDay()->addHour(), 'visibility' => 'public',
        ]);
        $this->actingAs($moderator)->getJson("/api/v1/prayer/sessions/{$session->id}")->assertJsonPath('data.canDelete', false);
        $this->actingAs($moderator)->deleteJson("/api/v1/prayer/sessions/{$session->id}")->assertForbidden();

        // L'administrateur garde ce droit.
        $this->actingAs($admin)->deleteJson("/api/v1/testimonies/{$t->id}")->assertOk();
        $this->actingAs($admin)->deleteJson("/api/v1/prayer/sessions/{$session->id}")->assertOk();
        $this->assertNull(Testimony::find($t->id));
    }

    public function test_organizer_events_and_past_events_in_my_feed(): void
    {
        $organizer = $this->user('Église');
        $me = $this->user('Moi');
        app(FollowService::class)->follow($me, $organizer);
        $upcoming = $this->event($organizer);
        $past = Event::create([
            'organizer_id' => $organizer->id, 'title' => 'Camp de jeunes', 'type' => 'crusade',
            'status' => 'published', 'starts_at' => now()->subDays(10), 'ends_at' => now()->subDays(8),
        ]);
        $ongoing = Event::create([
            'organizer_id' => $organizer->id, 'title' => 'Conférence', 'type' => 'crusade',
            'status' => 'published', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHours(3),
        ]);

        $ids = fn (string $scope) => collect($this->getJson("/api/v1/events?scope={$scope}&organizer_id={$organizer->id}")->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$past->id], $ids('past'));
        $this->assertSame([$ongoing->id], $ids('ongoing'));
        $this->assertContains($upcoming->id, $ids('upcoming'));

        $following = $this->actingAs($me)->getJson('/api/v1/feed/inserts')->assertOk()->json('data.following');
        $this->assertContains($past->id, collect($following['pastEvents'])->pluck('id')->all());
        $this->assertContains($ongoing->id, collect($following['events'])->pluck('id')->all());

        $this->get("/profiles/{$organizer->id}")->assertOk()->assertSee('Événements passés')->assertSee('Camp de jeunes');
    }

    // ─── Événements, requêtes, sessions : réactions et partages ──────────────

    public function test_event_reactions_count_once_and_shares_reach_followers(): void
    {
        $organizer = $this->user('Église');
        $sharer = $this->user('Partageur');
        $follower = $this->user('Abonné');
        app(FollowService::class)->follow($follower, $sharer);
        $event = $this->event($organizer);

        $this->actingAs($sharer)->postJson("/api/v1/events/{$event->id}/reactions", ['type' => 'like'])->assertCreated();
        $this->actingAs($sharer)->postJson("/api/v1/events/{$event->id}/reactions", ['type' => 'fire'])->assertOk()
            ->assertJsonPath('data.reactions.likesCount', 1)->assertJsonPath('data.reactions.myReaction', 'fire');
        $this->actingAs($sharer)->postJson("/api/v1/events/{$event->id}/reactions", ['type' => 'pray'])->assertCreated()
            ->assertJsonPath('data.reactions.prayersCount', 1);

        $this->actingAs($organizer)->postJson("/api/v1/events/{$event->id}/repost")->assertStatus(422);
        $this->actingAs($sharer)->postJson("/api/v1/events/{$event->id}/repost", ['comment' => 'Venez nombreux'])->assertCreated()
            ->assertJsonPath('data.reactions.repostsCount', 1)->assertJsonPath('data.reactions.isRepostedByMe', true);
        $this->actingAs($sharer)->postJson("/api/v1/events/{$event->id}/repost", ['comment' => 'Venez !'])->assertOk()
            ->assertJsonPath('data.reactions.repostsCount', 1);
        $this->assertSame(1, AppNotification::where('recipient_id', $organizer->id)->where('type', 'share')->count());

        // « Mon fil » de l'abonné : l'événement partagé arrive dans le groupe « suivis » avec le bandeau.
        $events = collect($this->actingAs($follower)->getJson('/api/v1/feed/inserts')->assertOk()->json('data.following.events'));
        $item = $events->firstWhere('id', $event->id);
        $this->assertNotNull($item);
        $this->assertSame('Partageur', $item['repost']['user']['displayName']);
        $this->assertSame('Venez !', $item['repost']['comment']);

        $this->actingAs($sharer)->deleteJson("/api/v1/events/{$event->id}/repost")->assertOk()
            ->assertJsonPath('data.reactions.repostsCount', 0);
        $this->actingAs($sharer)->deleteJson("/api/v1/events/{$event->id}/reactions?group=appreciation")->assertOk()
            ->assertJsonPath('data.reactions.likesCount', 0)->assertJsonPath('data.reactions.prayersCount', 1);
    }

    public function test_prayer_request_keeps_its_own_pray_button(): void
    {
        $author = $this->user('Auteur');
        $me = $this->user('Moi');
        $request = PrayerRequest::create(['user_id' => $author->id, 'body' => 'Priez pour ma famille', 'visibility' => 'public']);

        $this->actingAs($me)->postJson("/api/v1/prayer/requests/{$request->id}/reactions", ['type' => 'pray'])->assertStatus(422);
        $this->actingAs($me)->postJson("/api/v1/prayer/requests/{$request->id}/reactions", ['type' => 'love'])->assertCreated()
            ->assertJsonPath('data.reactions.likesCount', 1);
        $this->actingAs($me)->postJson("/api/v1/prayer/requests/{$request->id}/repost")->assertCreated();

        $private = PrayerRequest::create(['user_id' => $author->id, 'body' => 'Privé', 'visibility' => 'private']);
        $this->actingAs($me)->postJson("/api/v1/prayer/requests/{$private->id}/repost")->assertNotFound();
    }

    public function test_testimony_lists_show_who_shared_it(): void
    {
        $author = $this->user('Auteur');
        $sharer = $this->user('Partageur');
        $me = $this->user('Moi');
        app(FollowService::class)->follow($me, $sharer);
        $t = $this->testimony($author);

        $this->actingAs($sharer)->postJson("/api/v1/testimonies/{$t->id}/repost", ['comment' => 'À lire'])->assertCreated();

        $item = collect($this->actingAs($me)->getJson('/api/v1/testimonies')->assertOk()->json('data'))->firstWhere('id', $t->id);
        $this->assertSame('Partageur', $item['repostedBy']['display_name']);
        $this->assertSame('À lire', $item['repostComment']);
    }

    public function test_my_feed_shows_my_own_publications(): void
    {
        $me = $this->user('Moi');
        $t = $this->testimony($me);
        $event = $this->event($me);
        $prayer = PrayerRequest::create(['user_id' => $me->id, 'body' => 'Ma requête', 'visibility' => 'private']);

        $items = collect($this->actingAs($me)->getJson('/api/v1/feed')->assertOk()->json('data'));
        $this->assertSame('mine', $items->firstWhere('id', $t->id)['feedReason'] ?? null);

        $following = $this->actingAs($me)->getJson('/api/v1/feed/inserts')->assertOk()->json('data.following');
        $this->assertContains($event->id, collect($following['events'])->pluck('id')->all());
        $this->assertContains($prayer->id, collect($following['prayerRequests'])->pluck('id')->all());

        // Nouveau témoignage publié : visible tout de suite (cache de Mon fil vidé).
        $t2 = $this->testimony($me);
        $ids = collect($this->actingAs($me)->getJson('/api/v1/feed')->json('data'))->pluck('id');
        $this->assertContains($t2->id, $ids->all());
    }
}
