<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Administration : suppression d'un compte depuis la liste ou la fiche (super-administrateurs) et
 * approbation d'un témoignage rejeté depuis les listes.
 * Voir docs/fonctionnalites/confidentialite-et-suppression.md et gestion-des-temoignages.md
 */
class AdminUserDeletionAndReapprovalTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => ucfirst($name), 'email' => "{$name}@example.com", 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    private function superAdmin(string $name = 'super'): User
    {
        $admin = $this->user($name, ['role' => 'administrateur']);
        $admin->forceFill(['is_super_admin' => true])->save();

        return $admin;
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user);
    }

    // ── Suppression d'un compte ─────────────────────────────────────────────

    public function test_super_admin_deletes_an_account_from_the_administration(): void
    {
        $super  = $this->superAdmin();
        $member = $this->user('membre');
        Testimony::create(['user_id' => $member->id, 'title' => 'Guérison', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'approved', 'approved_at' => now()]);

        $this->as($super)->get(route('admin.users.show', $member->id))
            ->assertOk()->assertSee('Supprimer le compte')->assertSee('user-delete-form', false);
        $this->as($super)->get(route('admin.users.index'))
            ->assertOk()->assertSee('delete-user-d-' . $member->id, false);

        $this->as($super)->delete(route('admin.users.destroy', $member->id))
            ->assertRedirect(route('admin.users.index'))->assertSessionHas('success');

        $this->assertNull(User::withTrashed()->find($member->id));
        $this->assertSame(0, Testimony::withTrashed()->where('user_id', $member->id)->count());
        $log = AdminAuditLog::where('action', 'user.deleted')->where('subject_id', $member->id)->first();
        $this->assertSame($super->id, $log->actor_id);
        $this->assertSame('Suppression depuis l’administration', $log->reason);
    }

    public function test_deletion_is_reserved_to_super_admins_and_never_targets_self_or_super_admins(): void
    {
        $super  = $this->superAdmin();
        $other  = $this->superAdmin('autre');
        $admin  = $this->user('admin', ['role' => 'administrateur']);
        $member = $this->user('membre');

        // Administrateur simple : pas de bouton, 403.
        $this->as($admin)->get(route('admin.users.show', $member->id))
            ->assertOk()->assertSee('Réservé aux super-administrateurs.')->assertDontSee('user-delete-form', false);
        $this->as($admin)->delete(route('admin.users.destroy', $member->id))->assertForbidden();

        // Son propre compte, un autre super-administrateur : refusé.
        $this->as($super)->delete(route('admin.users.destroy', $super->id))->assertSessionHas('error');
        $this->as($super)->delete(route('admin.users.destroy', $other->id))->assertSessionHas('error');
        $this->as($super)->get(route('admin.users.show', $other->id))->assertDontSee('user-delete-form', false);

        $this->assertNotNull(User::find($member->id));
        $this->assertNotNull(User::find($super->id));
        $this->assertNotNull(User::find($other->id));
    }

    // ── Approbation d'un témoignage rejeté ──────────────────────────────────

    public function test_rejected_testimony_can_be_approved_from_the_lists(): void
    {
        $admin  = $this->user('admin', ['role' => 'administrateur']);
        $author = $this->user('auteur');
        $t = Testimony::create(['user_id' => $author->id, 'title' => 'Délivrance', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'rejected', 'rejection_reason' => 'Hors sujet']);

        $this->as($admin)->get(route('admin.content.index', ['status' => 'rejected']))
            ->assertOk()->assertSee('approve-d-' . $t->id, false)->assertSee(route('moderation.show', $t->id), false);
        $this->as($admin)->get(route('moderation.index', ['onglet' => 'rejected']))
            ->assertOk()->assertSee('approve-d-' . $t->id, false);

        $this->as($admin)->from(route('admin.content.index'))
            ->post(route('moderation.approve', $t->id), ['return' => 'back'])
            ->assertRedirect(route('admin.content.index'));

        $t->refresh();
        $this->assertSame('approved', $t->status->value);
        $this->assertNull($t->rejection_reason);
        $this->assertNotNull($t->approved_at);
        $this->assertSame(1, $author->fresh()->testimony_count);
    }
}
