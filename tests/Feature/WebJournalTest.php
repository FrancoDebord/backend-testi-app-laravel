<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Prophecy;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Carnet privé sur le site : témoignages privés (/carnet) et paroles prophétiques (/carnet/paroles).
 * Voir docs/fonctionnalites/carnet-prive.md et paroles-prophetiques.md
 */
class WebJournalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $make = fn (string $name, string $role = 'utilisateur') => User::create([
            'display_name' => ucfirst($name), 'email' => "{$name}@example.com",
            'password' => 'secret123', 'role' => $role, 'status' => 'active',
        ]);
        $this->user  = $make('marie');
        $this->other = $make('paul');
        Category::create(['slug' => 'guerison', 'name' => 'Guérison']);
        Category::create(['slug' => 'autre', 'name' => 'Autre']);
    }

    private function entry(array $attrs = []): Testimony
    {
        return Testimony::create(array_merge([
            'user_id' => $this->user->id, 'title' => 'Une prière exaucée', 'type' => 'text',
            'category_slug' => 'autre', 'body_text' => 'Merci Seigneur pour ce matin.',
            'visibility' => 'private', 'status' => 'draft',
        ], $attrs));
    }

    private function prophecy(array $attrs = []): Prophecy
    {
        return $this->user->prophecies()->create(array_merge([
            'received_on' => now()->subMonth()->toDateString(),
            'body_text'   => "Je t'ouvrirai des portes que personne ne pourra fermer.",
            'given_by'    => 'Pasteur Jean',
        ], $attrs))->refresh();
    }

    // ─── Témoignages du carnet ───────────────────────────────────────────────

    public function test_journal_lists_only_my_private_entries(): void
    {
        $this->entry(['title' => 'Mon entrée privée']);
        $this->entry(['title' => 'Déjà publié', 'visibility' => 'public', 'status' => 'approved']);
        Testimony::create([
            'user_id' => $this->other->id, 'title' => 'Carnet de Paul', 'type' => 'text',
            'category_slug' => 'autre', 'body_text' => '…', 'visibility' => 'private', 'status' => 'draft',
        ]);

        $this->get('/carnet')->assertRedirect('/login');
        $this->actingAs($this->user)->get('/carnet')->assertOk()
            ->assertSee('Mon entrée privée')->assertDontSee('Déjà publié')->assertDontSee('Carnet de Paul');
        $this->actingAs($this->user)->get('/carnet?q=introuvable')->assertOk()->assertSee('Aucun résultat');
    }

    public function test_share_entry_sends_it_to_moderation(): void
    {
        $entry = $this->entry();

        $this->actingAs($this->other)->post("/carnet/{$entry->id}/partager", ['category' => 'guerison'])->assertNotFound();
        $this->actingAs($this->user)->post("/carnet/{$entry->id}/partager", [])->assertSessionHasErrors('category');

        $this->actingAs($this->user)->post("/carnet/{$entry->id}/partager", ['category' => 'guerison'])
            ->assertRedirect(route('testimonies.show', $entry->id));
        $entry->refresh();
        $this->assertSame('public', $entry->visibility->value);
        $this->assertSame('pending', $entry->status->value);
        $this->assertSame('guerison', $entry->category_slug);

        // Partagée : n'est plus une entrée du carnet.
        $this->actingAs($this->user)->post("/carnet/{$entry->id}/partager", ['category' => 'guerison'])->assertNotFound();
    }

    public function test_move_public_testimony_back_to_journal_and_delete(): void
    {
        $t = $this->entry(['visibility' => 'public', 'status' => 'approved']);
        $p = $this->prophecy(['status' => 'fulfilled', 'testimony_id' => $t->id, 'is_public' => true]);

        $this->actingAs($this->other)->post("/testimonies/{$t->id}/carnet")->assertNotFound();
        $this->actingAs($this->user)->post("/testimonies/{$t->id}/carnet")->assertRedirect();
        $this->assertTrue($t->refresh()->isInJournal());
        $this->assertFalse($p->refresh()->is_public);

        $this->actingAs($this->user)->get("/testimonies/{$t->id}")->assertOk()->assertSee('Dans votre carnet privé');

        $this->actingAs($this->other)->delete("/carnet/{$t->id}")->assertNotFound();
        $this->actingAs($this->user)->delete("/carnet/{$t->id}")->assertRedirect(route('journal.index'));
        $this->assertSoftDeleted('testimonies', ['id' => $t->id]);
        $this->assertNull($p->refresh()->testimony_id);
        $this->assertTrue($p->isFulfilled());
    }

    // ─── Paroles prophétiques ────────────────────────────────────────────────

    public function test_create_prophecy_with_text_or_audio(): void
    {
        Storage::fake('public');

        $this->actingAs($this->user)->post('/carnet/paroles', ['received_on' => now()->toDateString()])
            ->assertSessionHasErrors('body_text');

        $this->actingAs($this->user)->post('/carnet/paroles', [
            'received_on' => now()->toDateString(),
            'audio'       => UploadedFile::fake()->create('parole.mp3', 200, 'audio/mpeg'),
            'audio_duration' => 42,
            'reminder_frequency' => 'weekly', 'reminder_time' => '07:30', 'reminder_weekday' => 3,
        ])->assertRedirect();

        $p = $this->user->prophecies()->first();
        $this->assertNotNull($p->audio_url);
        $this->assertSame(42, $p->audio_duration);
        $this->assertSame('weekly', $p->reminder_frequency);
        $this->assertSame(3, $p->reminder_weekday);
        $this->assertSame('waiting', $p->status);

        $this->actingAs($this->user)->get('/carnet/paroles')->assertOk()->assertSee('Parole enregistrée en audio');
        $this->actingAs($this->user)->get("/carnet/paroles/{$p->id}")->assertOk()->assertSee('Chaque mercredi');
        $this->actingAs($this->other)->get("/carnet/paroles/{$p->id}")->assertNotFound();
        $this->actingAs($this->other)->get('/carnet/paroles')->assertOk()->assertDontSee('Parole enregistrée en audio');
    }

    public function test_update_clears_reminder_and_checks_due_date(): void
    {
        $p = $this->prophecy(['reminder_frequency' => 'daily', 'reminder_time' => '06:00']);

        $this->actingAs($this->user)->put("/carnet/paroles/{$p->id}", [
            'received_on' => $p->received_on->toDateString(),
            'body_text'   => $p->body_text,
            'due_on'      => now()->subYear()->toDateString(),
        ])->assertSessionHasErrors('due_on');

        $this->actingAs($this->user)->put("/carnet/paroles/{$p->id}", [
            'received_on' => $p->received_on->toDateString(),
            'title'       => 'Portes ouvertes',
            'body_text'   => $p->body_text,
            'reminder_frequency' => '',
            'reminder_time'      => '06:00',
        ])->assertRedirect(route('prophecies.show', $p->id));

        $p->refresh();
        $this->assertSame('Portes ouvertes', $p->title);
        $this->assertNull($p->reminder_frequency);
        $this->assertNull($p->reminder_time);

        $this->actingAs($this->other)->put("/carnet/paroles/{$p->id}", ['body_text' => 'x'])->assertNotFound();
    }

    public function test_prayer_journal_and_fulfilment(): void
    {
        $p = $this->prophecy();

        $this->actingAs($this->user)->post("/carnet/paroles/{$p->id}/prieres", ['note' => 'Pour mon travail'])->assertRedirect();
        $this->actingAs($this->user)->post("/carnet/paroles/{$p->id}/prieres", [])->assertRedirect();
        $this->assertSame(2, $p->refresh()->prayer_count);
        $this->actingAs($this->user)->get("/carnet/paroles/{$p->id}")->assertOk()->assertSee('Pour mon travail');

        $prayerId = $p->prayers()->first()->id;
        $this->actingAs($this->other)->delete("/carnet/paroles/{$p->id}/prieres/{$prayerId}")->assertNotFound();
        $this->actingAs($this->user)->delete("/carnet/paroles/{$p->id}/prieres/{$prayerId}")->assertRedirect();
        $this->assertSame(1, $p->refresh()->prayer_count);

        $this->actingAs($this->user)->post("/carnet/paroles/{$p->id}/accomplie", ['fulfilled_on' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('fulfilled_on');
        $this->actingAs($this->user)->post("/carnet/paroles/{$p->id}/accomplie", [])->assertRedirect();
        $this->assertTrue($p->refresh()->isFulfilled());
        $this->assertSame(now()->toDateString(), $p->fulfilled_on->toDateString());

        $this->actingAs($this->user)->post("/carnet/paroles/{$p->id}/en-attente")->assertRedirect();
        $p->refresh();
        $this->assertSame('waiting', $p->status);
        $this->assertNull($p->fulfilled_on);
    }

    public function test_testify_from_prophecy_makes_it_public_with_the_testimony(): void
    {
        $p = $this->prophecy();

        $this->actingAs($this->user)->get("/publish?prophecy={$p->id}")->assertOk()->assertSee('Publier aussi la parole prophétique');
        $this->actingAs($this->other)->get("/publish?prophecy={$p->id}")->assertNotFound();

        $this->actingAs($this->user)->post('/testimonies', [
            'title' => 'La porte s\'est ouverte', 'type' => 'text', 'category' => 'guerison',
            'body_text' => 'Dieu a accompli sa parole.', 'visibility' => 'public', 'consent_given' => '1',
            'prophecy_id' => $p->id, 'prophecy_public' => '1',
        ])->assertRedirect();

        $p->refresh();
        $this->assertTrue($p->isFulfilled());
        $this->assertTrue($p->is_public);
        $this->assertNotNull($p->testimony_id);

        // Un témoignage est publié pour la parole : elle reste accomplie.
        $this->actingAs($this->user)->post("/carnet/paroles/{$p->id}/en-attente")->assertSessionHas('error');
        $this->assertTrue($p->refresh()->isFulfilled());

        // Personne d'autre ne peut rattacher la parole.
        $this->actingAs($this->other)->post('/testimonies', [
            'title' => 'Essai', 'type' => 'text', 'category' => 'guerison', 'body_text' => '…',
            'consent_given' => '1', 'prophecy_id' => $p->id,
        ])->assertNotFound();
    }

    public function test_delete_prophecy(): void
    {
        $p = $this->prophecy();

        $this->actingAs($this->other)->delete("/carnet/paroles/{$p->id}")->assertNotFound();
        $this->actingAs($this->user)->delete("/carnet/paroles/{$p->id}")->assertRedirect(route('prophecies.index'));
        $this->assertSoftDeleted('prophecies', ['id' => $p->id]);
    }
}
