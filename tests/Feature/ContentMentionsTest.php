<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\PrayerRequest;
use App\Models\Testimony;
use App\Models\User;
use App\Services\Mentions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Mentions « @ » dans le texte des témoignages et des requêtes de prière (docs/fonctionnalites/mentions.md) :
 * notification à la publication du témoignage (approbation), une seule fois, jamais pour le carnet privé ;
 * requêtes de prière : seulement les personnes qui peuvent la voir, auteur masqué si anonyme.
 */
class ContentMentionsTest extends TestCase
{
    use RefreshDatabase;

    private static int $phoneSeq = 0;

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => $name, 'email' => str()->slug($name) . '@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active', 'phone' => '+2298' . str_pad((string) ++self::$phoneSeq, 7, '0', STR_PAD_LEFT),
        ], $attrs));
    }

    private static function token(User $u): string
    {
        return "@[{$u->display_name}](user:{$u->id})";
    }

    private function mentionsOf(User $u): \Illuminate\Support\Collection
    {
        return AppNotification::where('recipient_id', $u->id)->where('type', 'mention')->get();
    }

    private function follow(User $follower, User $author): void
    {
        DB::table('follows')->insert(['follower_id' => $follower->id, 'following_id' => $author->id, 'created_at' => now()]);
    }

    // ─── Témoignages ───────────────────────────────────────────────────────

    public function test_testimony_mention_is_notified_at_approval_only_once(): void
    {
        $author = $this->user('Marie');
        $paul = $this->user('Paul Mbeki');
        $modo = $this->user('Modo', ['role' => 'moderateur']);

        $id = $this->actingAs($author)->postJson('/api/v1/testimonies', [
            'title' => 'Guérie', 'type' => 'text', 'category' => 'guerison',
            'body_text' => 'Merci à ' . self::token($paul) . ' pour ses prières.',
        ])->assertCreated()
            ->assertJsonPath('data.bodyText', 'Merci à @Paul Mbeki pour ses prières.')
            ->assertJsonPath('data.mentions.0.id', strtolower($paul->id))
            ->json('data.id');

        // En attente de modération : personne n'est prévenu.
        $this->assertCount(0, $this->mentionsOf($paul));
        $this->assertStringContainsString('(user:', Testimony::find($id)->body_text);

        $this->actingAs($modo)->postJson("/api/v1/moderation/{$id}/approve")->assertOk();

        $notes = $this->mentionsOf($paul);
        $this->assertCount(1, $notes);
        $this->assertSame($id, $notes[0]->testimony_id);
        $this->assertSame($author->id, $notes[0]->actor_id);
        $this->assertSame('Marie vous a mentionné dans son témoignage « Guérie »', $notes[0]->message);

        // Modification (texte lisible renvoyé tel quel + nouvelle mention), nouvelle approbation :
        // Paul n'est pas prévenu une seconde fois, seule la nouvelle personne l'est.
        $awa = $this->user('Awa');
        $this->actingAs($author)->putJson("/api/v1/testimonies/{$id}", [
            'body_text' => 'Merci à @Paul Mbeki et @Awa.', 'mention_ids' => [$awa->id],
        ])->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonCount(2, 'data.mentions');
        $this->assertCount(0, $this->mentionsOf($awa));

        $this->actingAs($modo)->postJson("/api/v1/moderation/{$id}/approve")->assertOk();
        $this->assertCount(1, $this->mentionsOf($paul));
        $this->assertCount(1, $this->mentionsOf($awa));
    }

    public function test_journal_testimony_mentions_are_never_notified(): void
    {
        $author = $this->user('Marie');
        $paul = $this->user('Paul');

        $id = $this->actingAs($author)->postJson('/api/v1/testimonies', [
            'title' => 'Pour moi', 'type' => 'text', 'visibility' => 'private',
            'body_text' => 'Avec ' . self::token($paul),
        ])->assertCreated()->json('data.id');

        $testimony = Testimony::find($id);
        $this->assertSame(0, Mentions::notifyTestimony($testimony->load('user')));
        // Même approuvé par erreur, le carnet privé reste muet.
        $testimony->forceFill(['status' => 'approved'])->save();
        $this->assertSame(0, Mentions::notifyTestimony($testimony->fresh('user')));
        $this->assertCount(0, $this->mentionsOf($paul));
    }

    public function test_followers_only_testimony_notifies_only_followers_and_web_approval_works(): void
    {
        $author = $this->user('Marie');
        $follower = $this->user('Abonné');
        $stranger = $this->user('Inconnu');
        $modo = $this->user('Modo', ['role' => 'moderateur']);
        $this->follow($follower, $author);

        $testimony = Testimony::create([
            'user_id' => $author->id, 'title' => 'Entre nous', 'type' => 'text', 'category_slug' => 'autre',
            'body_text' => 'Merci ' . self::token($follower) . ' et ' . self::token($stranger),
            'visibility' => 'followers', 'status' => 'pending',
        ]);

        $this->actingAs($modo)->post(route('moderation.approve', $testimony->id))->assertRedirect();

        $this->assertCount(1, $this->mentionsOf($follower));
        $this->assertCount(0, $this->mentionsOf($stranger));
    }

    public function test_web_store_recognizes_mention_ids_and_page_renders_link(): void
    {
        $author = $this->user('Marie');
        $paul = $this->user('Paul');
        \App\Models\Category::create(['slug' => 'guerison', 'name' => 'Guérison', 'is_active' => true]);

        $this->actingAs($author)->post(route('testimonies.store'), [
            'title' => 'Guérie', 'type' => 'text', 'category' => 'guerison', 'consent_given' => '1',
            'body_text' => 'Merci **@Paul** !', 'mention_ids' => [$paul->id],
        ])->assertRedirect();

        $testimony = Testimony::latest()->first();
        $this->assertSame('Merci **' . self::token($paul) . '** !', $testimony->body_text);
        $html = (string) $testimony->body_html;
        $this->assertStringContainsString('<strong><a href="' . route('profiles.show', strtolower($paul->id)) . '"', $html);
        $this->assertSame('Merci @Paul !', $testimony->body_plain);
    }

    // ─── Requêtes de prière ────────────────────────────────────────────────

    public function test_prayer_request_mentions_notify_people_who_can_see_it(): void
    {
        $author = $this->user('Marie');
        $follower = $this->user('Abonné');
        $stranger = $this->user('Inconnu');
        $this->follow($follower, $author);

        $id = $this->actingAs($author)->postJson('/api/v1/prayer/requests', [
            'body' => 'Priez avec ' . self::token($follower) . ' et ' . self::token($stranger) . ' pour ma famille.',
            'visibility' => 'followers',
        ])->assertSuccessful()
            ->assertJsonPath('data.body', 'Priez avec @Abonné et @Inconnu pour ma famille.')
            ->assertJsonCount(2, 'data.mentions')
            ->json('data.id');

        $notes = $this->mentionsOf($follower);
        $this->assertCount(1, $notes);
        $this->assertSame('Marie vous a mentionné dans une requête de prière', $notes[0]->message);
        $this->assertSame($id, $notes[0]->payload['prayer_request_id']);
        $this->assertCount(0, $this->mentionsOf($stranger));

        // Devenue publique : l'inconnu peut la voir et est prévenu ; l'abonné ne l'est pas deux fois.
        $this->actingAs($author)->putJson("/api/v1/prayer/requests/{$id}", ['visibility' => 'public'])->assertOk();
        $this->assertCount(1, $this->mentionsOf($follower));
        $this->assertCount(1, $this->mentionsOf($stranger));
    }

    public function test_private_prayer_request_never_notifies(): void
    {
        $author = $this->user('Marie');
        $paul = $this->user('Paul');

        $this->actingAs($author)->postJson('/api/v1/prayer/requests', [
            'body' => 'Seigneur, bénis ' . self::token($paul) . ' aujourd’hui.', 'visibility' => 'private',
        ])->assertSuccessful();

        $this->assertCount(0, $this->mentionsOf($paul));
    }

    public function test_anonymous_prayer_request_does_not_reveal_the_author(): void
    {
        $author = $this->user('Marie');
        $paul = $this->user('Paul');

        $this->actingAs($author)->postJson('/api/v1/prayer/requests', [
            'body' => 'Priez pour moi et pour @Paul, merci.', 'mention_ids' => [$paul->id], 'is_anonymous' => true,
        ])->assertSuccessful();

        $note = $this->mentionsOf($paul)->first();
        $this->assertNotNull($note);
        $this->assertSame("Quelqu'un vous a mentionné dans une requête de prière", $note->message);
        $this->assertNull($note->actor_id);
        $this->assertNull($note->actor_avatar);
        $this->assertStringNotContainsString('Marie', json_encode($note->toArray()));
    }
}
