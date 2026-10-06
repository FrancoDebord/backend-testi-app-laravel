<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Comment;
use App\Models\PrayerRequest;
use App\Models\Testimony;
use App\Models\User;
use App\Services\Mentions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Mentions « @ » dans les commentaires. docs/fonctionnalites/mentions.md */
class MentionsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => $name, 'email' => str()->slug($name) . '@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active', 'phone' => '+22990000' . random_int(100, 999),
        ], $attrs));
    }

    private function testimony(User $author): Testimony
    {
        return Testimony::create([
            'user_id' => $author->id, 'title' => 'Guérison', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ]);
    }

    private static function token(User $u): string
    {
        return "@[{$u->display_name}](user:{$u->id})";
    }

    // ─── Recherche ─────────────────────────────────────────────────────────

    public function test_search_requires_login_and_never_exposes_email_or_phone(): void
    {
        $me = $this->user('Awa');
        $this->user('Paul Mbeki');

        $this->getJson('/api/v1/users/mention-search?q=pau')->assertUnauthorized();

        $res = $this->actingAs($me)->getJson('/api/v1/users/mention-search?q=pau')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.displayName', 'Paul Mbeki');
        $json = $res->getContent();
        $this->assertStringNotContainsString('@example.com', $json);
        $this->assertStringNotContainsString('+22990000', $json);
        $this->assertArrayNotHasKey('email', $res->json('data.0'));
    }

    public function test_search_excludes_me_and_inactive_accounts_and_ranks_followed_first(): void
    {
        $me = $this->user('Paula');
        $this->user('Paul Suspendu', ['status' => 'suspended']);
        $this->user('Paul Banni', ['status' => 'banned']);
        $other = $this->user('Apaul Inconnu');
        $followed = $this->user('Zed Paul');
        DB::table('follows')->insert(['follower_id' => $me->id, 'following_id' => $followed->id, 'created_at' => now()]);

        $names = collect($this->actingAs($me)->getJson('/api/v1/users/mention-search?q=paul')->assertOk()->json('data'))
            ->pluck('displayName')->all();

        $this->assertSame(['Zed Paul', 'Apaul Inconnu'], $names);
        $this->assertNotContains('Paula', $names);
        $this->assertTrue($other->exists);
    }

    public function test_search_matches_organization_name_and_favours_testimony_participants(): void
    {
        $me = $this->user('Awa');
        $this->user('Église ABC', ['account_type' => 'organization', 'organization_name' => 'Grâce Divine', 'verification_status' => 'verified']);
        $author = $this->user('Grace Auteur');
        $this->user('Grace Autre');
        $t = $this->testimony($author);

        $res = $this->actingAs($me)->getJson('/api/v1/users/mention-search?q=gr&testimony_id=' . $t->id)->assertOk();
        $this->assertSame('Grace Auteur', $res->json('data.0.displayName'));
        $this->assertContains('Église ABC', collect($res->json('data'))->pluck('displayName')->all());

        // « @ » seul : participants et comptes suivis seulement.
        $this->actingAs($me)->getJson('/api/v1/users/mention-search?q=&testimony_id=' . $t->id)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $author->id);
    }

    public function test_web_search_uses_the_session(): void
    {
        $me = $this->user('Awa');
        $this->user('Paul Mbeki');

        $this->actingAs($me)->getJson('/personnes/mentions?q=mbe')->assertOk()->assertJsonPath('data.0.displayName', 'Paul Mbeki');
    }

    // ─── Enregistrement et notification ────────────────────────────────────

    public function test_comment_mentions_are_resolved_notified_and_returned(): void
    {
        $me = $this->user('Awa');
        $author = $this->user('Auteur');
        $paul = $this->user('Paul Mbeki');
        $suspended = $this->user('Ancien', ['status' => 'suspended']);
        $t = $this->testimony($author);

        $text = 'Merci ' . self::token($paul) . ' et ' . self::token($suspended) . ' ! @[Faux Nom](user:' . $paul->id . ')';
        $res = $this->actingAs($me)->postJson("/api/v1/testimonies/{$t->id}/comments", ['text' => $text])->assertCreated();

        // Texte affichable, mention valide seulement, nom réel (pas « Faux Nom »).
        $res->assertJsonPath('data.text', 'Merci @Paul Mbeki et @Ancien ! @Paul Mbeki')
            ->assertJsonPath('data.mentions', [['id' => $paul->id, 'displayName' => 'Paul Mbeki']]);
        $this->assertStringContainsString("(user:{$paul->id})", Comment::first()->body);
        $this->assertStringNotContainsString($suspended->id, Comment::first()->body);

        $this->assertSame(1, AppNotification::where('recipient_id', $paul->id)->where('type', 'mention')->count());
        $this->assertSame(0, AppNotification::where('recipient_id', $suspended->id)->count());
        // L'auteur du témoignage reçoit « comment », pas de doublon.
        $this->assertSame(1, AppNotification::where('recipient_id', $author->id)->count());

        $this->getJson("/api/v1/testimonies/{$t->id}/comments")->assertOk()
            ->assertJsonPath('data.0.mentions.0.id', $paul->id)
            ->assertJsonPath('data.0.text', 'Merci @Paul Mbeki et @Ancien ! @Paul Mbeki');
    }

    public function test_mention_ids_hint_and_edit_keep_existing_mentions_without_notifying_twice(): void
    {
        $me = $this->user('Awa');
        $author = $this->user('Auteur');
        $paul = $this->user('Paul Mbeki');
        $ruth = $this->user('Ruth');
        $t = $this->testimony($author);

        $id = $this->actingAs($me)->postJson("/api/v1/testimonies/{$t->id}/comments", [
            'text' => 'Bonjour @Paul Mbeki', 'mention_ids' => [$paul->id],
        ])->assertCreated()->json('data.id');
        $this->assertSame(1, AppNotification::where('recipient_id', $paul->id)->where('type', 'mention')->count());

        // Modification en texte clair : Paul reste mentionné, Ruth est ajoutée et seule prévenue.
        $this->actingAs($me)->putJson("/api/v1/comments/{$id}", ['text' => 'Bonjour @Paul Mbeki et ' . self::token($ruth)])->assertOk()
            ->assertJsonCount(2, 'data.mentions');
        $this->assertSame(1, AppNotification::where('recipient_id', $paul->id)->where('type', 'mention')->count());
        $this->assertSame(1, AppNotification::where('recipient_id', $ruth->id)->where('type', 'mention')->count());
    }

    public function test_self_mention_is_kept_but_not_notified(): void
    {
        $me = $this->user('Awa');
        $t = $this->testimony($this->user('Auteur'));

        $this->actingAs($me)->postJson("/api/v1/testimonies/{$t->id}/comments", ['text' => 'Moi ' . self::token($me)])->assertCreated()
            ->assertJsonPath('data.mentions.0.id', $me->id);
        $this->assertSame(0, AppNotification::where('recipient_id', $me->id)->count());
    }

    public function test_web_comment_renders_clickable_mention_and_escapes_text(): void
    {
        $me = $this->user('Awa');
        $paul = $this->user('Paul <b>');
        $t = $this->testimony($this->user('Auteur'));

        $res = $this->actingAs($me)->postJson("/videos/{$t->id}/comments", ['body' => '<i>x</i> ' . self::token($paul)])->assertCreated();
        $html = $res->json('html');
        $this->assertStringContainsString('href="' . route('profiles.show', $paul->id) . '"', $html);
        $this->assertStringContainsString('@Paul &lt;b&gt;', $html);
        $this->assertStringContainsString('&lt;i&gt;x&lt;/i&gt;', $html);
        $this->assertSame(1, AppNotification::where('recipient_id', $paul->id)->where('type', 'mention')->count());
    }

    public function test_prayer_message_mentions_notify_only_people_who_can_see_the_request(): void
    {
        $me = $this->user('Awa');
        $author = $this->user('Auteur');
        $paul = $this->user('Paul');
        $request = PrayerRequest::create([
            'user_id' => $author->id, 'body' => 'Priez pour moi', 'visibility' => 'public', 'status' => 'open',
        ]);

        $this->actingAs($me)->postJson("/api/v1/prayer/requests/{$request->id}/messages", ['message' => 'Avec ' . self::token($paul)])
            ->assertSuccessful();

        $this->assertSame(1, AppNotification::where('recipient_id', $paul->id)->where('type', 'mention')->count());
        $notification = AppNotification::where('recipient_id', $paul->id)->first();
        $this->assertSame($request->id, $notification->payload['prayer_request_id'] ?? null);
    }

    public function test_plain_and_of_helpers(): void
    {
        $id = '0199aaaa-bbbb-7ccc-8ddd-eeeeffff0000';
        $text = "Salut @[Jean](user:$id) et @[Jean](user:$id)";

        $this->assertSame('Salut @Jean et @Jean', Mentions::plain($text));
        $this->assertSame([['id' => $id, 'displayName' => 'Jean']], Mentions::of($text));
        $this->assertSame([], Mentions::of('Sans mention @Jean'));
    }
}
