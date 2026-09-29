<?php

namespace Tests\Feature;

use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Carnet privé : témoignages gardés pour soi, jamais modérés ni visibles
 * des autres, partageables plus tard. Voir docs/fonctionnalites/carnet-prive.md
 */
class JournalTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private User $other;
    private User $moderator;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $make = fn (string $role, string $name) => User::create([
            'display_name' => $name, 'email' => strtolower($name) . '@example.com',
            'password' => 'secret123', 'role' => $role, 'status' => 'active',
        ]);
        DB::table('categories')->insert([
            'id' => (string) Str::uuid(), 'name' => 'Autre', 'slug' => 'autre',
            'icon' => '🌟', 'color' => '#6B7280', 'display_order' => 13,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->author    = $make('utilisateur', 'Auteur');
        $this->other     = $make('utilisateur', 'Autre');
        $this->moderator = $make('moderateur', 'Moderateur');
        $this->admin     = $make('administrateur', 'Admin');
    }

    private function writeInJournal(array $extra = []): string
    {
        return $this->actingAs($this->author)->postJson('/api/v1/testimonies', array_merge([
            'title'      => 'Ce que Dieu a fait ce matin',
            'type'       => 'text',
            'body_text'  => 'Une réponse à la prière, à ne pas oublier.',
            'visibility' => 'private',
        ], $extra))->assertCreated()->json('data.id');
    }

    public function test_journal_entry_is_a_private_draft_without_category_or_moderation(): void
    {
        $id = $this->writeInJournal();

        $t = Testimony::find($id);
        $this->assertSame('private', $t->visibility->value);
        $this->assertSame('draft', $t->status->value);
        $this->assertSame('autre', $t->category_slug); // catégorie facultative

        // Jamais dans la file de modération.
        $this->assertSame(0, Testimony::pending()->count());
        $this->actingAs($this->moderator)->getJson('/api/v1/moderation/pending')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_only_the_author_sees_the_journal(): void
    {
        $id = $this->writeInJournal();

        $this->actingAs($this->author)->getJson('/api/v1/journal')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->actingAs($this->other)->getJson('/api/v1/journal')->assertOk()->assertJsonCount(0, 'data');

        // Détail : l'auteur oui (via son jeton sur la route publique), les autres 404.
        $this->actingAs($this->author, 'sanctum')->getJson("/api/v1/testimonies/{$id}")->assertOk();
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->other, 'sanctum')->getJson("/api/v1/testimonies/{$id}")->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/testimonies/{$id}")->assertNotFound();

        // Fil public : absent. Lecture par l'auteur : pas comptée comme une vue.
        $this->getJson('/api/v1/testimonies')->assertJsonCount(0, 'data');
        $this->assertSame(0, Testimony::find($id)->views_count);
    }

    public function test_moderators_and_admins_never_see_journal_entries(): void
    {
        $id = $this->writeInJournal();

        $this->actingAs($this->moderator)->getJson("/api/v1/moderation/{$id}")->assertNotFound();
        $this->actingAs($this->moderator)->postJson("/api/v1/moderation/{$id}/approve")->assertNotFound();
        $this->actingAs($this->admin)->getJson('/api/v1/admin/testimonies')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->admin)->get('/admin/content')->assertOk()->assertDontSee('Ce que Dieu a fait ce matin');
        $this->assertSame('draft', Testimony::find($id)->status->value);
    }

    public function test_journal_can_be_filtered_and_searched(): void
    {
        $this->writeInJournal(['title' => 'Guérison de maman']);
        $this->writeInJournal(['title' => 'Emploi trouvé', 'type' => 'audio', 'media_url' => 'https://x.test/a.m4a']);

        $this->actingAs($this->author)->getJson('/api/v1/journal?type=audio')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Emploi trouvé');
        $this->actingAs($this->author)->getJson('/api/v1/journal?q=maman')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Guérison de maman');
    }

    public function test_editing_a_journal_entry_keeps_it_private(): void
    {
        $id = $this->writeInJournal();
        $this->actingAs($this->author)->putJson("/api/v1/testimonies/{$id}", [
            'title' => 'Titre corrigé', 'type' => 'text', 'body_text' => 'Texte complété',
        ])->assertOk();

        $t = Testimony::find($id);
        $this->assertSame('Titre corrigé', $t->title);
        $this->assertSame('draft', $t->status->value);
        $this->assertSame(0, Testimony::pending()->count());
    }

    public function test_sharing_sends_the_entry_to_moderation_and_it_can_return_to_the_journal(): void
    {
        $id = $this->writeInJournal();

        $this->actingAs($this->other)->postJson("/api/v1/testimonies/{$id}/publish")->assertNotFound();

        $this->actingAs($this->author)->postJson("/api/v1/testimonies/{$id}/publish", ['category' => 'autre'])
            ->assertOk()->assertJsonPath('data.visibility', 'public')->assertJsonPath('data.status', 'pending');
        $this->assertSame(1, Testimony::pending()->count());
        $this->actingAs($this->author)->getJson('/api/v1/journal')->assertJsonCount(0, 'data');
        $this->actingAs($this->author)->postJson("/api/v1/testimonies/{$id}/publish")->assertStatus(409);

        // Retour dans le carnet : retiré du public et de la modération.
        $this->actingAs($this->author)->postJson("/api/v1/testimonies/{$id}/make-private")
            ->assertOk()->assertJsonPath('data.visibility', 'private')->assertJsonPath('data.status', 'draft');
        $this->assertSame(0, Testimony::pending()->count());
        $this->actingAs($this->author)->getJson('/api/v1/journal')->assertJsonCount(1, 'data');
    }

    public function test_my_testimonies_excludes_the_journal(): void
    {
        $this->writeInJournal();
        $this->actingAs($this->author)->postJson('/api/v1/testimonies', [
            'title' => 'Public', 'type' => 'text', 'category' => 'autre', 'body_text' => 'x', 'visibility' => 'public',
        ])->assertCreated();

        $this->actingAs($this->author)->getJson('/api/v1/testimonies/my')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Public');
    }

    public function test_public_testimony_still_requires_a_category(): void
    {
        $this->actingAs($this->author)->postJson('/api/v1/testimonies', [
            'title' => 'Sans catégorie', 'type' => 'text', 'body_text' => 'x', 'visibility' => 'public',
        ])->assertStatus(422);
    }
}
