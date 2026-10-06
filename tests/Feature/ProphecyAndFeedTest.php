<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Prophecy;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Paroles prophétiques (carnet privé, journal de prière, accomplissement) et « Mon fil ».
 * Voir docs/fonctionnalites/paroles-prophetiques.md et recommandations.md
 */
class ProphecyAndFeedTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user  = $this->makeUser('marie');
        $this->other = $this->makeUser('paul');
        Category::create(['slug' => 'guerison', 'name' => 'Guérison']);
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

    // ─── Paroles prophétiques ────────────────────────────────────────────────

    public function test_record_with_defaults_and_privacy(): void
    {
        $id = $this->actingAs($this->user)->postJson('/api/v1/prophecies', [
            'body_text' => "Je t'ouvrirai des portes que personne ne pourra fermer.",
            'given_by'  => 'Pasteur Jean',
        ])->assertCreated()
            ->assertJsonPath('data.receivedOn', now()->toDateString())
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('data.isPublic', false)
            ->json('data.id');

        $this->actingAs($this->other)->getJson("/api/v1/prophecies/{$id}")->assertNotFound();
        $this->actingAs($this->other)->getJson('/api/v1/prophecies')->assertOk()->assertJsonCount(0, 'data');
        $this->guest()->getJson('/api/v1/prophecies')->assertUnauthorized();
    }

    public function test_text_or_audio_is_required(): void
    {
        $this->actingAs($this->user)->postJson('/api/v1/prophecies', ['title' => 'Vide'])
            ->assertUnprocessable()->assertJsonValidationErrors('body_text');

        $this->actingAs($this->user)->postJson('/api/v1/prophecies', [
            'audio_url' => 'https://example.org/storage/media/audios/a.m4a', 'audio_duration' => 42,
        ])->assertCreated()->assertJsonPath('data.audioDuration', 42);
    }

    public function test_reminder_rules(): void
    {
        $this->actingAs($this->user)->postJson('/api/v1/prophecies', [
            'body_text' => 'Parole', 'reminder_frequency' => 'weekly', 'reminder_time' => '07:30',
        ])->assertUnprocessable()->assertJsonValidationErrors('reminder_weekday');

        $id = $this->actingAs($this->user)->postJson('/api/v1/prophecies', [
            'body_text' => 'Parole', 'reminder_frequency' => 'daily', 'reminder_time' => '07:30',
        ])->assertCreated()->assertJsonPath('data.reminder.time', '07:30')->json('data.id');

        $this->actingAs($this->user)->putJson("/api/v1/prophecies/{$id}", ['reminder_frequency' => null])
            ->assertOk()->assertJsonPath('data.reminder', null);
    }

    public function test_prayer_journal(): void
    {
        $id = $this->actingAs($this->user)->postJson('/api/v1/prophecies', ['body_text' => 'Parole'])->json('data.id');

        $this->actingAs($this->user)->postJson("/api/v1/prophecies/{$id}/prayers", ['note' => 'Proclamée ce matin'])
            ->assertCreated()->assertJsonPath('data.prophecy.prayerCount', 1);
        $prayerId = $this->actingAs($this->user)->postJson("/api/v1/prophecies/{$id}/prayers")
            ->assertJsonPath('data.prophecy.prayerCount', 2)->json('data.prayer.id');

        $this->actingAs($this->user)->getJson("/api/v1/prophecies/{$id}/prayers")->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($this->user)->deleteJson("/api/v1/prophecies/{$id}/prayers/{$prayerId}")
            ->assertOk()->assertJsonPath('data.prayerCount', 1);
        $this->actingAs($this->other)->postJson("/api/v1/prophecies/{$id}/prayers")->assertNotFound();
    }

    public function test_fulfilled_by_a_testimony_and_made_public(): void
    {
        $id = $this->actingAs($this->user)->postJson('/api/v1/prophecies', [
            'title' => 'Une maison', 'body_text' => 'Tu auras ta maison avant la fin de l\'année.', 'given_by' => 'Prophète Élie',
        ])->json('data.id');

        // La parole d'un autre ne peut pas être rattachée.
        $this->actingAs($this->other)->postJson('/api/v1/testimonies', [
            'title' => 'Ma maison', 'type' => 'text', 'category' => 'guerison', 'body_text' => 'Récit', 'prophecy_id' => $id,
        ])->assertNotFound();

        $testimonyId = $this->actingAs($this->user)->postJson('/api/v1/testimonies', [
            'title' => 'Dieu m\'a donné une maison', 'type' => 'text', 'category' => 'guerison',
            'body_text' => 'Récit', 'prophecy_id' => $id, 'prophecy_public' => true,
        ])->assertCreated()->assertJsonPath('data.prophecy.givenBy', 'Prophète Élie')->json('data.id');

        $p = Prophecy::find($id);
        $this->assertSame('fulfilled', $p->status);
        $this->assertSame($testimonyId, $p->testimony_id);
        $this->assertTrue($p->is_public);

        // Une fois publié, la parole est visible avec le témoignage.
        Testimony::whereKey($testimonyId)->update(['status' => 'approved', 'approved_at' => now()]);
        $this->guest()->getJson("/api/v1/testimonies/{$testimonyId}")
            ->assertOk()->assertJsonPath('data.prophecy.bodyText', 'Tu auras ta maison avant la fin de l\'année.');

        // Avec un témoignage publié, la parole ne redevient pas « en attente ».
        $this->actingAs($this->user)->putJson("/api/v1/prophecies/{$id}", ['status' => 'waiting'])->assertUnprocessable();
    }

    public function test_private_prophecy_stays_hidden_with_its_testimony(): void
    {
        $id = $this->actingAs($this->user)->postJson('/api/v1/prophecies', ['body_text' => 'Parole secrète'])->json('data.id');
        $testimonyId = $this->actingAs($this->user)->postJson('/api/v1/testimonies', [
            'title' => 'Accomplie', 'type' => 'text', 'category' => 'guerison', 'body_text' => 'Récit', 'prophecy_id' => $id,
        ])->json('data.id');
        Testimony::whereKey($testimonyId)->update(['status' => 'approved', 'approved_at' => now()]);

        $this->guest()->getJson("/api/v1/testimonies/{$testimonyId}")->assertOk()->assertJsonPath('data.prophecy', null);
    }

    public function test_mark_fulfilled_without_testimony(): void
    {
        $id = $this->actingAs($this->user)->postJson('/api/v1/prophecies', ['body_text' => 'Parole'])->json('data.id');

        $this->actingAs($this->user)->putJson("/api/v1/prophecies/{$id}", ['status' => 'fulfilled'])
            ->assertOk()->assertJsonPath('data.status', 'fulfilled')->assertJsonPath('data.fulfilledOn', now()->toDateString());
        $this->actingAs($this->user)->putJson("/api/v1/prophecies/{$id}", ['status' => 'waiting'])
            ->assertOk()->assertJsonPath('data.fulfilledOn', null);
    }

    // ─── Mon fil ─────────────────────────────────────────────────────────────

    private function published(User $author, string $title): Testimony
    {
        return Testimony::create([
            'user_id' => $author->id, 'title' => $title, 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Récit', 'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ]);
    }

    public function test_personal_feed_mixes_following_and_suggestions(): void
    {
        $stranger = $this->makeUser('inconnu');
        for ($i = 1; $i <= 4; $i++) {
            $this->published($this->other, "Suivi {$i}");
        }
        $this->published($stranger, 'Suggestion');
        $this->published($this->user, 'Le mien'); // jamais dans son propre fil

        $this->actingAs($this->user)->postJson("/api/v1/users/{$this->other->id}/follow")->assertSuccessful();

        $res = $this->actingAs($this->user)->getJson('/api/v1/feed')->assertOk()
            ->assertJsonPath('meta.followingCount', 1)
            ->assertJsonCount(5, 'data');
        $reasons = collect($res->json('data'))->pluck('feedReason')->all();
        $this->assertSame(['following', 'following', 'following', 'suggested', 'following'], $reasons);
        $this->assertNotContains('Le mien', collect($res->json('data'))->pluck('title'));

        $this->guest()->getJson('/api/v1/feed')->assertUnauthorized();
    }
}
