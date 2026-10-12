<?php

namespace Tests\Feature;

use App\Models\Follow;
use App\Models\ModerationLog;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contrat API attendu par l'application mobile (audit du 2026-10-06) :
 * renommer un témoignage, verset long, motif de refus, réglages, « Qui peut commenter »,
 * suppression d'un compte sans mot de passe, partages par un invité.
 */
class AppContractTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => ucfirst($name), 'email' => "{$name}@example.com", 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    private function testimony(User $author, array $attrs = []): Testimony
    {
        return Testimony::create(array_merge([
            'user_id' => $author->id, 'title' => 'Guérison', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ], $attrs));
    }

    public function test_renaming_a_testimony_sends_only_the_title(): void
    {
        $author = $this->user('auteur');
        $t = $this->testimony($author);

        $this->actingAs($author)->putJson("/api/v1/testimonies/{$t->id}", ['title' => 'Nouveau titre'])
            ->assertOk()->assertJsonPath('data.title', 'Nouveau titre')->assertJsonPath('data.type', 'text');
        // À la création, le type reste obligatoire.
        $this->actingAs($author)->postJson('/api/v1/testimonies', ['title' => 'Sans type', 'category' => 'guerison', 'body_text' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    public function test_long_bible_verse_is_stored(): void
    {
        $author = $this->user('auteur');
        $t = $this->testimony($author);
        $verse = 'Psaume 91:1-4 — «' . str_repeat('Celui qui demeure sous l’abri du Très-Haut ', 10) . '»';
        $this->assertGreaterThan(255, mb_strlen($verse));

        $this->actingAs($author)->putJson("/api/v1/testimonies/{$t->id}", ['bible_verse' => $verse])->assertOk();
        $this->assertSame($verse, $t->fresh()->bible_verse);
    }

    public function test_rejection_reason_is_shown_to_the_author_only(): void
    {
        $author = $this->user('auteur');
        $moderator = $this->user('modo', ['role' => 'moderateur']);
        $t = $this->testimony($author, ['status' => 'rejected', 'approved_at' => null]);
        ModerationLog::create([
            'testimony_id' => $t->id, 'moderator_id' => $moderator->id, 'action' => 'rejected',
            'rejection_reason' => 'falseTestimony', 'moderator_note' => 'Merci de préciser les faits.', 'created_at' => now(),
        ]);

        $this->actingAs($author)->getJson('/api/v1/testimonies/my')->assertOk()
            ->assertJsonPath('data.0.rejectionReason', 'Faux témoignage — Merci de préciser les faits.');
        $this->actingAs($author)->getJson("/api/v1/testimonies/{$t->id}")->assertOk()
            ->assertJsonPath('data.rejectionReason', 'Faux témoignage — Merci de préciser les faits.');
    }

    public function test_settings_can_be_read_and_comment_permission_is_enforced(): void
    {
        $author = $this->user('auteur');
        $fan = $this->user('fan');
        $stranger = $this->user('inconnu');
        Follow::create(['follower_id' => $fan->id, 'following_id' => $author->id]);
        $t = $this->testimony($author);
        $url = "/api/v1/testimonies/{$t->id}/comments";

        $this->actingAs($author)->getJson('/api/v1/users/me/settings')->assertOk()
            ->assertJsonPath('data.comment_permission', 'everyone');
        $this->actingAs($author)->putJson('/api/v1/users/me/settings', ['comment_permission' => 'followers', 'push_likes' => false])
            ->assertOk()->assertJsonPath('data.comment_permission', 'followers');
        $this->actingAs($author)->getJson('/api/v1/users/me/settings')->assertJsonPath('data.push_likes', false);

        $this->actingAs($stranger)->getJson("/api/v1/testimonies/{$t->id}")->assertJsonPath('data.canComment', false);
        $this->actingAs($stranger)->postJson($url, ['text' => 'Gloire à Dieu'])->assertForbidden();
        $this->actingAs($fan)->postJson($url, ['text' => 'Gloire à Dieu'])->assertCreated();
        $this->actingAs($author)->postJson($url, ['text' => 'Merci'])->assertCreated();

        $this->actingAs($author)->putJson('/api/v1/users/me/settings', ['comment_permission' => 'nobody'])->assertOk();
        $this->actingAs($fan)->postJson($url, ['text' => 'Encore'])->assertForbidden();
    }

    public function test_account_without_password_is_deleted_with_a_confirmation(): void
    {
        $phoneUser = $this->user('telephone', ['password' => null, 'email' => null, 'phone' => '+22990000001']);

        $this->actingAs($phoneUser)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.has_password', false);
        $this->actingAs($phoneUser)->deleteJson('/api/v1/users/me')->assertUnprocessable()->assertJsonValidationErrors('confirm');
        $this->actingAs($phoneUser)->deleteJson('/api/v1/users/me', ['confirm' => true])->assertOk();
        $this->assertSoftDeleted('users', ['id' => $phoneUser->id]);

        $member = $this->user('membre');
        $this->actingAs($member)->getJson('/api/v1/auth/me')->assertJsonPath('data.has_password', true);
        $this->actingAs($member)->deleteJson('/api/v1/users/me', ['password' => 'mauvais'])->assertForbidden();
    }

    public function test_guests_can_record_shares_of_public_content_only(): void
    {
        $author = $this->user('auteur');
        $t = $this->testimony($author);
        $private = $this->testimony($author, ['visibility' => 'private']);

        $this->postJson("/api/v1/testimonies/{$t->id}/share")->assertOk();
        $this->assertSame(1, $t->fresh()->share_count);
        $this->postJson("/api/v1/testimonies/{$private->id}/share")->assertNotFound();
    }
}
