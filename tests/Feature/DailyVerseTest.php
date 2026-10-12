<?php

namespace Tests\Feature;

use App\Models\BibleBook;
use App\Models\BibleVerse;
use App\Models\DailyVerse;
use App\Models\DailyVerseComment;
use App\Models\PrayerRequest;
use App\Models\Testimony;
use App\Models\User;
use App\Support\BibleReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verset du jour : versions, réactions, commentaires, requêtes de prière et témoignages liés (API et site).
 * Voir docs/fonctionnalites/verset-du-jour.md
 */
class DailyVerseTest extends TestCase
{
    use RefreshDatabase;

    private User $member;
    private User $other;
    private User $moderator;
    private DailyVerse $verse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->member    = $this->makeUser('membre');
        $this->other     = $this->makeUser('autre');
        $this->moderator = $this->makeUser('modo', ['role' => 'moderateur']);

        $this->verse = DailyVerse::create([
            'verse_text' => 'Car Dieu a tant aimé le monde…', 'reference' => 'Jean 3 : 16-17',
            'scheduled_date' => now()->toDateString(), 'is_active' => true,
        ]);

        // Deux traductions : LSG a la plage complète, KJV seulement le verset 16.
        foreach (['LSG' => 'Jean', 'KJV' => 'John'] as $code => $name) {
            BibleBook::create(['translation' => $code, 'number' => 43, 'name' => $name, 'abbreviation' => 'Jn', 'testament' => 'NT', 'chapters_count' => 21]);
        }
        BibleVerse::create(['translation' => 'LSG', 'book' => 43, 'chapter' => 3, 'verse' => 16, 'text' => 'Car Dieu a tant aimé le monde']);
        BibleVerse::create(['translation' => 'LSG', 'book' => 43, 'chapter' => 3, 'verse' => 17, 'text' => "Dieu, en effet, n'a pas envoyé son Fils"]);
        BibleVerse::create(['translation' => 'KJV', 'book' => 43, 'chapter' => 3, 'verse' => 16, 'text' => 'For God so loved the {world}']);
    }

    private function makeUser(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => ucfirst($name), 'email' => "{$name}@example.com",
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    private function guest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    public function test_reference_parser_reads_french_english_and_ranges(): void
    {
        $this->assertSame(['book' => 24, 'chapter' => 29, 'verseStart' => 11, 'verseEnd' => 11], BibleReference::parse('Jérémie 29 : 11'));
        $this->assertSame(['book' => 43, 'chapter' => 3, 'verseStart' => 16, 'verseEnd' => 18], BibleReference::parse('Jean 3:16-18'));
        $this->assertSame(60, BibleReference::parse('1 Pierre 5.7')['book']);
        $this->assertSame(19, BibleReference::parse('Ps 23,1')['book']);
        $this->assertSame(43, BibleReference::parse('John 3:16')['book']);
        $this->assertCount(2, BibleReference::findAll('Lisez (Jn 3:16) puis Rm 8:28.'));
        $this->assertNull(BibleReference::parse('Un texte sans référence'));
        $this->assertSame('Jean 3:16-17', BibleReference::label(BibleReference::parse('jean 3,16 à 17')));
    }

    public function test_today_exposes_parsed_reference_and_counts(): void
    {
        $this->getJson('/api/v1/daily-verse')->assertOk()
            ->assertJsonPath('data.id', $this->verse->id)
            ->assertJsonPath('data.parsed.book', 43)
            ->assertJsonPath('data.parsed.verseEnd', 17)
            ->assertJsonPath('data.counts.comments', 0)
            ->assertJsonPath('data.myReactions', []);

        $this->getJson('/api/v1/daily-verse?translation=kjv')->assertOk()
            ->assertJsonPath('data.translation.code', 'KJV')
            ->assertJsonPath('data.translation.text', 'For God so loved the world');
    }

    public function test_versions_return_each_translation_and_flag_partial_ones(): void
    {
        $res = $this->getJson("/api/v1/daily-verse/{$this->verse->id}/versions")->assertOk();
        $versions = collect($res->json('data.versions'))->keyBy('code');

        $this->assertSame('Jean 3:16-17', $res->json('data.parsed.label'));
        $this->assertSame('Car Dieu a tant aimé le monde…', $res->json('data.original.text'));
        $this->assertCount(2, $versions['LSG']['verses']);
        $this->assertTrue($versions['LSG']['available']);
        $this->assertCount(1, $versions['KJV']['verses']); // passage incomplet : seuls les versets présents

        // Version absente du serveur : liste vide, pas d'erreur.
        $this->getJson('/api/v1/daily-verse/versions?translation=XYZ')->assertOk()->assertJsonCount(0, 'data.versions');
    }

    public function test_unrecognised_reference_offers_only_the_original_text(): void
    {
        $this->verse->update(['reference' => 'Parole de sagesse']);

        $this->getJson('/api/v1/daily-verse/versions')->assertOk()
            ->assertJsonPath('data.parsed', null)
            ->assertJsonPath('data.versions.0.available', false);
    }

    public function test_reactions_toggle_on_any_verse(): void
    {
        $this->postJson("/api/v1/daily-verse/{$this->verse->id}/react", ['type' => 'like'])->assertUnauthorized();

        $this->actingAs($this->member)->postJson("/api/v1/daily-verse/{$this->verse->id}/react", ['type' => 'pray'])
            ->assertOk()->assertJsonPath('data.counts.prayers', 1)->assertJsonPath('data.myReactions', ['pray']);
        $this->actingAs($this->member)->postJson("/api/v1/daily-verse/{$this->verse->id}/react", ['type' => 'pray'])
            ->assertOk()->assertJsonPath('data.counts.prayers', 0);
        $this->actingAs($this->member)->postJson("/api/v1/daily-verse/{$this->verse->id}/react", ['type' => 'like', 'active' => true])
            ->assertOk()->assertJsonPath('data.counts.likes', 1);
    }

    public function test_comments_create_delete_and_rights(): void
    {
        $url = "/api/v1/daily-verse/{$this->verse->id}/comments";
        $this->postJson($url, ['body' => 'Amen !'])->assertUnauthorized();
        $this->actingAs($this->member)->postJson($url, ['body' => ''])->assertUnprocessable();

        $id = $this->actingAs($this->member)->postJson($url, ['body' => 'Ce verset me porte.'])
            ->assertCreated()->assertJsonPath('data.isMine', true)->json('data.id');

        $this->guest()->getJson($url)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.canDelete', false)->assertJsonPath('data.0.canReport', false);

        $this->actingAs($this->other)->deleteJson("{$url}/{$id}")->assertForbidden();
        // Un modérateur ne supprime jamais ce qu'un autre a écrit ; un administrateur, oui.
        $this->actingAs($this->moderator)->deleteJson("{$url}/{$id}")->assertForbidden();
        $this->moderator->update(['role' => 'administrateur']);
        $this->actingAs($this->moderator->fresh())->deleteJson("{$url}/{$id}")->assertOk();
        $this->moderator->update(['role' => 'moderateur']);
        $this->assertSoftDeleted('daily_verse_comments', ['id' => $id]);
    }

    public function test_comment_mentions_are_readable_and_notified(): void
    {
        $body = "Merci @[{$this->other->display_name}](user:{$this->other->id}) pour ce partage";
        $this->actingAs($this->member)->postJson("/api/v1/daily-verse/{$this->verse->id}/comments", ['body' => $body])
            ->assertCreated()
            ->assertJsonPath('data.body', "Merci @{$this->other->display_name} pour ce partage")
            ->assertJsonPath('data.mentions.0.id', strtolower($this->other->id));

        $this->assertDatabaseHas('app_notifications', ['recipient_id' => $this->other->id, 'type' => 'mention']);
    }

    public function test_three_reports_hide_a_comment(): void
    {
        $comment = DailyVerseComment::create(['daily_verse_id' => $this->verse->id, 'user_id' => $this->member->id, 'body' => 'Publicité']);
        $url = "/api/v1/daily-verse/{$this->verse->id}/comments/{$comment->id}/report";

        $this->actingAs($this->member)->postJson($url, ['reason' => 'spam'])->assertUnprocessable(); // son propre commentaire
        $this->actingAs($this->other)->postJson($url, ['reason' => 'spam'])->assertOk();
        $this->actingAs($this->other)->postJson($url, ['reason' => 'spam'])->assertOk(); // même personne : compté une fois
        $this->assertSame(1, $comment->fresh()->report_count);

        $this->actingAs($this->moderator)->postJson($url, ['reason' => 'spam'])->assertOk();
        $this->actingAs($this->makeUser('troisieme'))->postJson($url, ['reason' => 'other'])->assertOk();
        $this->assertNotNull($comment->fresh()->hidden_at);

        // Retiré : invisible du public, visible de son auteur.
        $this->guest()->getJson("/api/v1/daily-verse/{$this->verse->id}/comments")->assertJsonCount(0, 'data');
        $this->actingAs($this->member)->getJson("/api/v1/daily-verse/{$this->verse->id}/comments")
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.isHidden', true);
    }

    public function test_prayer_requests_linked_to_the_verse_follow_visibility(): void
    {
        $this->actingAs($this->member)->postJson('/api/v1/prayer/requests', [
            'body' => 'Que je comprenne cet amour chaque jour.', 'daily_verse_id' => $this->verse->id,
        ])->assertCreated()->assertJsonPath('data.dailyVerseId', $this->verse->id);
        $this->actingAs($this->member)->postJson('/api/v1/prayer/requests', [
            'body' => 'Une requête privée sur ce verset.', 'daily_verse_id' => $this->verse->id, 'visibility' => 'private',
        ])->assertCreated();
        $this->actingAs($this->member)->postJson('/api/v1/prayer/requests', ['body' => 'Sans rapport avec le verset.'])->assertCreated();
        $this->actingAs($this->member)->postJson('/api/v1/prayer/requests', ['body' => 'Verset inconnu ici.', 'daily_verse_id' => 999])
            ->assertUnprocessable();

        $this->guest()->getJson("/api/v1/daily-verse/{$this->verse->id}/prayer-requests")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.canCreate', false);
        $this->actingAs($this->member)->getJson("/api/v1/prayer/requests?scope=verse&daily_verse_id={$this->verse->id}")
            ->assertJsonCount(2, 'data');
        $this->assertSame(3, PrayerRequest::count());
    }

    public function test_linked_testimonies_match_the_cited_passage(): void
    {
        $make = fn (string $title, ?string $ref, ?string $verse, string $status = 'approved') => Testimony::create([
            'user_id' => $this->other->id, 'title' => $title, 'type' => 'text', 'category_slug' => 'autre',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => $status,
            'approved_at' => now(), 'bible_ref' => $ref, 'bible_verse' => $verse,
        ]);
        $make('Par la référence', 'Jn 3:16', null);
        $make('Dans le verset saisi', null, '« Car Dieu a tant aimé… » (Jean 3.17)');
        $make('Autre passage', 'Jean 3:5', null);
        $make('En attente', 'Jean 3:16', null, 'pending');

        $titles = collect($this->getJson("/api/v1/daily-verse/{$this->verse->id}/testimonies")->assertOk()->json('data'))->pluck('title');
        $this->assertEqualsCanonicalizing(['Par la référence', 'Dans le verset saisi'], $titles->all());
    }

    public function test_daily_verse_is_stable_without_a_scheduled_verse(): void
    {
        $this->verse->update(['scheduled_date' => null]);
        DailyVerse::create(['verse_text' => 'Autre', 'reference' => 'Psaume 23:1', 'is_active' => true]);

        $first = DailyVerse::today()->id;
        foreach (range(1, 5) as $_) {
            $this->assertSame($first, DailyVerse::today()->id);
        }
    }

    public function test_web_page_shows_versions_tabs_and_guest_prompts(): void
    {
        $this->get('/verset-du-jour')->assertOk()
            ->assertSee('Jean 3:16-17')
            ->assertSee('Comparer')
            ->assertSee('Connectez-vous', false);

        $this->get('/verset-du-jour?version=KJV')->assertOk()->assertSee('For God so loved the world');
        // Version mémorisée en session.
        $this->get('/verset-du-jour')->assertSee('For God so loved the world');
        $this->get('/verset-du-jour?comparer=1')->assertOk()->assertSee("Dieu, en effet, n'a pas envoyé son Fils");
        $this->get('/verset-du-jour/' . now()->toDateString())->assertOk();
        $this->get('/verset-du-jour/2026-13-45')->assertNotFound();
        $this->get('/')->assertSee(route('daily-verse.show'), false);
    }

    public function test_web_member_can_comment_pray_and_react(): void
    {
        $this->actingAs($this->member)->from('/verset-du-jour')
            ->post("/verset-du-jour/{$this->verse->id}/commentaires", ['body' => 'Merci Seigneur'])
            ->assertRedirect();
        $this->actingAs($this->member)->from('/verset-du-jour')
            ->post("/verset-du-jour/{$this->verse->id}/prieres", ['body' => 'Aide-moi à aimer comme toi.', 'visibility' => 'public'])
            ->assertRedirect();
        $this->actingAs($this->member)->from('/verset-du-jour')
            ->post("/verset-du-jour/{$this->verse->id}/reagir", ['type' => 'like'])->assertRedirect();

        $this->assertDatabaseHas('daily_verse_comments', ['daily_verse_id' => $this->verse->id, 'body' => 'Merci Seigneur']);
        $this->assertDatabaseHas('prayer_requests', ['daily_verse_id' => $this->verse->id]);
        $this->assertSame(1, $this->verse->fresh()->like_count);

        $this->actingAs($this->member)->get('/verset-du-jour?onglet=commentaires')->assertSee('Merci Seigneur');
        $this->guest()->post("/verset-du-jour/{$this->verse->id}/commentaires", ['body' => 'x'])->assertRedirect(route('login'));
    }

    public function test_legacy_react_is_idempotent(): void
    {
        // État local de l'application périmé : réagir deux fois ne renvoie plus 409.
        $this->actingAs($this->member)->postJson('/api/v1/daily-verse/react', ['type' => 'like'])->assertOk();
        $this->actingAs($this->member)->postJson('/api/v1/daily-verse/react', ['type' => 'like'])->assertOk();
        $this->actingAs($this->member)->getJson('/api/v1/daily-verse')->assertJsonPath('data.counts.likes', 1);
        $this->actingAs($this->member)->deleteJson('/api/v1/daily-verse/react', ['type' => 'like'])->assertOk();
        $this->actingAs($this->member)->getJson('/api/v1/daily-verse')->assertJsonPath('data.counts.likes', 0);
    }

    public function test_mention_notification_points_to_the_verse(): void
    {
        $body = "Merci @[{$this->other->display_name}](user:{$this->other->id})";
        $this->actingAs($this->member)->postJson("/api/v1/daily-verse/{$this->verse->id}/comments", ['body' => $body])->assertCreated();

        $this->actingAs($this->other)->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('data.0.type', 'mention')
            ->assertJsonPath('data.0.dailyVerseId', $this->verse->id);
    }
}
