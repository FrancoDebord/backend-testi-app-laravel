<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\DailyVerse;
use App\Models\DailyVerseComment;
use App\Models\Event;
use App\Models\EventComment;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** « J'aime » sur les commentaires — docs/fonctionnalites/jaime-commentaires.md */
class CommentLikesTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private User $fan;
    private Testimony $testimony;
    private Comment $comment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = $this->user('Auteur');
        $this->fan    = $this->user('Fan');
        $this->testimony = $this->testimony($this->author);
        $this->comment = Comment::create(['testimony_id' => $this->testimony->id, 'user_id' => $this->author->id, 'body' => 'Gloire à Dieu']);
    }

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => $name, 'email' => str()->slug($name) . '@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    private function testimony(User $author, array $attrs = []): Testimony
    {
        return Testimony::create(array_merge([
            'user_id' => $author->id, 'title' => 'Guéri', 'type' => 'text', 'category_slug' => 'autre', 'body_text' => 'Histoire',
            'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ], $attrs));
    }

    private function guest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    // ─── Commentaires des témoignages ──────────────────────────────────────

    public function test_like_is_idempotent_and_returns_the_state(): void
    {
        $url = "/api/v1/comments/{$this->comment->id}/like";

        $this->actingAs($this->fan)->postJson($url)->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.likesCount', 1)
            ->assertJsonPath('data.isLikedByMe', true);
        $this->actingAs($this->fan)->postJson($url)->assertOk()->assertJsonPath('data.likesCount', 1);

        $this->assertSame(1, CommentLike::count());
        $this->assertSame(1, $this->comment->fresh()->likes_count);

        // L'auteur peut aimer son propre commentaire.
        $this->actingAs($this->author)->postJson($url)->assertOk()->assertJsonPath('data.likesCount', 2);

        $this->actingAs($this->fan)->deleteJson($url)->assertOk()
            ->assertJsonPath('data.likesCount', 1)->assertJsonPath('data.isLikedByMe', false);
        $this->actingAs($this->fan)->deleteJson($url)->assertOk()->assertJsonPath('data.likesCount', 1);
    }

    public function test_unlike_never_makes_the_counter_negative(): void
    {
        $url = "/api/v1/comments/{$this->comment->id}/like";
        $this->actingAs($this->fan)->postJson($url)->assertOk();
        // Compteur désynchronisé (ancienne donnée) : retirer le « j'aime » le ramène à 0, jamais en dessous.
        DB::table('comments')->where('id', $this->comment->id)->update(['likes_count' => 0]);

        $this->actingAs($this->fan)->deleteJson($url)->assertOk()->assertJsonPath('data.likesCount', 0);
        $this->assertSame(0, $this->comment->fresh()->likes_count);
    }

    public function test_lists_expose_likes_count_and_is_liked_by_me(): void
    {
        $reply = Comment::create(['testimony_id' => $this->testimony->id, 'user_id' => $this->fan->id, 'parent_id' => $this->comment->id, 'body' => 'Amen']);
        $other = Comment::create(['testimony_id' => $this->testimony->id, 'user_id' => $this->fan->id, 'body' => 'Merci']);
        $this->actingAs($this->fan)->postJson("/api/v1/comments/{$this->comment->id}/like")->assertOk();
        $this->actingAs($this->fan)->postJson("/api/v1/comments/{$reply->id}/like")->assertOk();

        $data = collect($this->actingAs($this->fan)->getJson("/api/v1/testimonies/{$this->testimony->id}/comments")->assertOk()->json('data'))->keyBy('id');
        $this->assertSame(1, $data[$this->comment->id]['likesCount']);
        $this->assertTrue($data[$this->comment->id]['isLikedByMe']);
        $this->assertFalse($data[$other->id]['isLikedByMe']);
        $this->assertSame(0, $data[$other->id]['likesCount']);
        $replyData = collect($data[$this->comment->id]['replies'])->firstWhere('id', $reply->id);
        $this->assertTrue($replyData['isLikedByMe']);

        // Liste lue sans une requête par commentaire (nouvelle requête HTTP : rien de déjà lu).
        $this->app->forgetScopedInstances();
        DB::enableQueryLog();
        $this->actingAs($this->fan)->getJson("/api/v1/testimonies/{$this->testimony->id}/comments")->assertOk();
        $likeQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'comment_likes'))->count();
        DB::disableQueryLog();
        $this->assertSame(1, $likeQueries);

        // Visiteur : toujours faux.
        $guestData = collect($this->guest()->getJson("/api/v1/testimonies/{$this->testimony->id}/comments")->assertOk()->json('data'))->keyBy('id');
        $this->assertFalse($guestData[$this->comment->id]['isLikedByMe']);
        $this->assertSame(1, $guestData[$this->comment->id]['likesCount']);
    }

    public function test_author_is_notified_once_and_never_for_own_like(): void
    {
        $url = "/api/v1/comments/{$this->comment->id}/like";
        $this->actingAs($this->author)->postJson($url)->assertOk();
        $this->assertSame(0, AppNotification::where('type', 'comment_liked')->count());

        $this->actingAs($this->fan)->postJson($url)->assertOk();
        $this->actingAs($this->fan)->deleteJson($url)->assertOk();
        $this->actingAs($this->fan)->postJson($url)->assertOk();

        $notifications = AppNotification::where('type', 'comment_liked')->get();
        $this->assertCount(1, $notifications);
        $n = $notifications->first();
        $this->assertSame($this->author->id, $n->recipient_id);
        $this->assertSame($this->fan->id, $n->actor_id);
        $this->assertSame($this->testimony->id, $n->testimony_id);
        $this->assertSame($this->comment->id, $n->comment_id);
        $this->assertSame('Fan a aimé votre commentaire', $n->message);

        $this->actingAs($this->author)->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('data.0.type', 'comment_liked')
            ->assertJsonPath('data.0.commentId', $this->comment->id);
    }

    public function test_comment_liked_push_follows_the_likes_preference(): void
    {
        $this->assertSame('push_likes', (new \ReflectionClassConstant(\App\Services\PushNotifications::class, 'PREFERENCES'))->getValue()['comment_liked']);
        \App\Models\UserSetting::create(['user_id' => $this->author->id, 'push_likes' => false]);
        $this->assertFalse(\App\Services\PushNotifications::allows($this->author->id, 'comment_liked'));
        $this->assertTrue(\App\Enums\NotificationType::CommentLiked->isReaction());
    }

    public function test_invisible_or_missing_comments_return_404(): void
    {
        $this->actingAs($this->fan)->postJson('/api/v1/comments/' . str()->uuid() . '/like')->assertNotFound()
            ->assertJsonPath('success', false);

        // Témoignage en attente de modération : invisible des autres membres.
        $pending = $this->testimony($this->author, ['status' => 'pending', 'approved_at' => null]);
        $hidden = Comment::create(['testimony_id' => $pending->id, 'user_id' => $this->author->id, 'body' => 'Caché']);
        $this->actingAs($this->fan)->postJson("/api/v1/comments/{$hidden->id}/like")->assertNotFound();
        $this->assertSame(0, CommentLike::count());

        // Visiteur : connexion requise.
        $this->guest()->postJson("/api/v1/comments/{$this->comment->id}/like")->assertUnauthorized();
    }

    // ─── Commentaires des événements ───────────────────────────────────────

    private function event(): Event
    {
        $org = $this->user('Église', ['account_type' => 'organization', 'organization_name' => 'Église de la Grâce',
            'organization_type' => 'church', 'verification_status' => 'verified']);
        $id = $this->actingAs($org)->postJson('/api/v1/events', [
            'title' => 'Croisade', 'type' => 'crusade', 'city' => 'Cotonou',
            'starts_at' => now()->addDays(10)->toIso8601String(), 'ends_at' => now()->addDays(12)->toIso8601String(),
        ])->assertCreated()->json('data.id');

        return Event::findOrFail($id);
    }

    public function test_event_comment_likes(): void
    {
        $event = $this->event();
        $commentId = $this->actingAs($this->author)->postJson("/api/v1/events/{$event->id}/comments", ['body' => 'Quelle soirée !'])
            ->assertCreated()->assertJsonPath('data.likesCount', 0)->assertJsonPath('data.isLikedByMe', false)->json('data.id');

        $url = "/api/v1/events/{$event->id}/comments/{$commentId}/like";
        $this->actingAs($this->fan)->postJson($url)->assertOk()->assertJsonPath('data.likesCount', 1)->assertJsonPath('data.isLikedByMe', true);
        $this->actingAs($this->fan)->postJson($url)->assertOk()->assertJsonPath('data.likesCount', 1);

        $item = collect($this->actingAs($this->fan)->getJson("/api/v1/events/{$event->id}/comments")->assertOk()->json('data'))->firstWhere('id', $commentId);
        $this->assertSame(1, $item['likesCount']);
        $this->assertTrue($item['isLikedByMe']);

        $n = AppNotification::where('type', 'comment_liked')->firstOrFail();
        $this->assertSame($event->id, $n->payload['event_id']);
        $this->assertSame($commentId, $n->comment_id);

        $this->actingAs($this->fan)->deleteJson($url)->assertOk()->assertJsonPath('data.likesCount', 0)->assertJsonPath('data.isLikedByMe', false);
        $this->assertSame(0, EventComment::find($commentId)->likes_count);

        // Commentaire d'un autre événement, ou événement masqué : 404.
        $this->actingAs($this->fan)->postJson('/api/v1/events/' . str()->uuid() . "/comments/{$commentId}/like")->assertNotFound();
        $event->update(['hidden_at' => now()]);
        $this->actingAs($this->fan)->postJson($url)->assertNotFound();
    }

    // ─── Commentaires du verset du jour ────────────────────────────────────

    public function test_daily_verse_comment_likes(): void
    {
        $verse = DailyVerse::create(['verse_text' => 'Car Dieu a tant aimé le monde', 'reference' => 'Jean 3:16', 'scheduled_date' => now()->toDateString(), 'is_active' => true]);
        $comment = DailyVerseComment::create(['daily_verse_id' => $verse->id, 'user_id' => $this->author->id, 'body' => 'Merci Seigneur']);
        $url = "/api/v1/daily-verse/{$verse->id}/comments/{$comment->id}/like";

        $this->actingAs($this->fan)->postJson($url)->assertOk()->assertJsonPath('data.likesCount', 1)->assertJsonPath('data.isLikedByMe', true);

        $item = collect($this->actingAs($this->fan)->getJson("/api/v1/daily-verse/{$verse->id}/comments")->assertOk()->json('data'))->firstWhere('id', $comment->id);
        $this->assertSame(1, $item['likesCount']);
        $this->assertTrue($item['isLikedByMe']);

        $n = AppNotification::where('type', 'comment_liked')->firstOrFail();
        $this->assertSame($verse->id, $n->payload['daily_verse_id']);

        // Commentaire retiré (signalements) : invisible des autres membres, visible de son auteur.
        $comment->update(['hidden_at' => now()]);
        $this->actingAs($this->fan)->deleteJson($url)->assertNotFound();
        $this->actingAs($this->author)->postJson($url)->assertOk()->assertJsonPath('data.likesCount', 2);
    }

    // ─── Site ──────────────────────────────────────────────────────────────

    public function test_web_routes_like_with_json_and_pages_show_the_button(): void
    {
        $this->actingAs($this->fan)->postJson(route('comments.like', $this->comment->id))->assertOk()
            ->assertJsonPath('data.likesCount', 1)->assertJsonPath('data.isLikedByMe', true);

        $this->actingAs($this->fan)->get("/videos/{$this->testimony->id}")->assertOk()
            ->assertSee('data-comment-like="' . route('comments.like', $this->comment->id) . '"', false)
            ->assertSee('aria-pressed="true"', false);

        $this->actingAs($this->fan)->deleteJson(route('comments.unlike', $this->comment->id))->assertOk()
            ->assertJsonPath('data.likesCount', 0)->assertJsonPath('data.isLikedByMe', false);

        // Visiteur : redirigé vers la connexion (pas de bouton actif).
        $this->guest()->post(route('comments.like', $this->comment->id))->assertRedirect(route('login'));

        $event = $this->event();
        $eventComment = EventComment::create(['event_id' => $event->id, 'user_id' => $this->author->id, 'body' => 'Béni']);
        $this->actingAs($this->fan)->postJson(route('events.comments.like', [$event->id, $eventComment->id]))->assertOk()->assertJsonPath('data.likesCount', 1);
        $this->actingAs($this->fan)->get(route('events.show', [$event->id, 'section' => 'commentaires']))->assertOk()
            ->assertSee('data-comment-like="' . route('events.comments.like', [$event->id, $eventComment->id]) . '"', false);

        $verse = DailyVerse::create(['verse_text' => 'Le Seigneur est mon berger', 'reference' => 'Psaume 23:1', 'scheduled_date' => now()->toDateString(), 'is_active' => true]);
        $verseComment = DailyVerseComment::create(['daily_verse_id' => $verse->id, 'user_id' => $this->author->id, 'body' => 'Amen']);
        $this->actingAs($this->fan)->postJson(route('daily-verse.comments.like', [$verse->id, $verseComment->id]))->assertOk()->assertJsonPath('data.isLikedByMe', true);
        $this->actingAs($this->fan)->get('/verset-du-jour?onglet=commentaires')->assertOk()
            ->assertSee('data-comment-like="' . route('daily-verse.comments.like', [$verse->id, $verseComment->id]) . '"', false);
    }
}
