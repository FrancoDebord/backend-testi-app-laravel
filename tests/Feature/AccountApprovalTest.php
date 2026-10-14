<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Testimony;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Services\PersonalVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Badge « Approuvé » des comptes : formulaire de vérification, vérification des organisations,
 * attribution et retrait manuels (motif, audit, notification), champs d'API, back-office, droits.
 * Voir docs/fonctionnalites/badge-approuve.md
 */
class AccountApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $person;
    private User $church;
    private User $modo;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->person = $this->makeUser('marie');
        $this->church = $this->makeUser('eglise', ['account_type' => 'organization', 'organization_name' => 'Église de la Grâce', 'organization_type' => 'church', 'verification_status' => 'pending']);
        $this->modo   = $this->makeUser('modo', ['role' => 'moderateur']);
        $this->admin  = $this->makeUser('admin', ['role' => 'administrateur']);
    }

    private function makeUser(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => ucfirst($name), 'email' => "{$name}@example.com",
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user);
    }

    public function test_manual_grant_requires_a_reason_sets_source_audits_and_notifies(): void
    {
        $this->as($this->admin)->postJson("/api/v1/admin/users/{$this->person->id}/approval", [])->assertUnprocessable();

        $this->as($this->admin)->postJson("/api/v1/admin/users/{$this->person->id}/approval", ['reason' => 'Pasteur connu de l’équipe'])
            ->assertOk()
            ->assertJsonPath('data.is_approved', true)
            ->assertJsonPath('data.account_type', 'individual')
            ->assertJsonPath('data.approval_badge', 'approved_personal');

        $p = $this->person->fresh();
        $this->assertSame('admin_manual', $p->approval_source);
        $this->assertSame($this->admin->id, $p->verified_by);
        $this->assertNotNull($p->verified_at);
        $log = AdminAuditLog::where('action', 'user.badge_granted')->where('subject_id', $p->id)->sole();
        $this->assertSame('Pasteur connu de l’équipe', $log->reason);
        $this->assertSame('admin_manual', $log->after['approval_source']);
        $this->assertDatabaseHas('app_notifications', ['recipient_id' => $p->id, 'type' => 'account_verified']);

        // Déjà approuvé : refusé.
        $this->as($this->admin)->postJson("/api/v1/admin/users/{$p->id}/approval", ['reason' => 'Encore'])->assertUnprocessable();
    }

    public function test_manual_revoke_requires_a_reason_and_notifies_with_it(): void
    {
        $this->as($this->admin)->postJson("/api/v1/admin/users/{$this->church->id}/approval", ['reason' => 'Église partenaire'])
            ->assertOk()->assertJsonPath('data.approval_badge', 'approved_organization');

        $this->as($this->admin)->deleteJson("/api/v1/admin/users/{$this->church->id}/approval", [])->assertUnprocessable();
        $this->as($this->admin)->deleteJson("/api/v1/admin/users/{$this->church->id}/approval", ['reason' => 'Activités suspendues'])
            ->assertOk()
            ->assertJsonPath('data.is_approved', false)
            ->assertJsonPath('data.approval_badge', null);

        $c = $this->church->fresh();
        $this->assertNull($c->approval_source);
        $this->assertSame('Activités suspendues', $c->verification_note);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'user.badge_revoked', 'subject_id' => $c->id, 'reason' => 'Activités suspendues']);
        // Retrait du badge : type dédié, distinct du refus d'une demande.
        $this->assertDatabaseHas('app_notifications', ['recipient_id' => $c->id, 'type' => 'account_approval_revoked']);
        $this->assertDatabaseMissing('app_notifications', ['recipient_id' => $c->id, 'type' => 'organization_rejected']);
        $this->assertStringContainsString('Activités suspendues', \App\Models\AppNotification::where('recipient_id', $c->id)->where('type', 'account_approval_revoked')->value('message'));
    }

    public function test_verification_form_and_organization_review_set_their_source(): void
    {
        $request = VerificationRequest::create([
            'user_id' => $this->person->id, 'full_name' => 'Marie K.', 'role' => 'pastor', 'church' => 'Église de la Grâce', 'status' => VerificationRequest::PENDING,
            'documents' => [], 'links' => [],
        ]);
        PersonalVerification::approve($request, $this->admin);
        $this->assertSame('verification_form', $this->person->fresh()->approval_source);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'user.badge_granted', 'subject_id' => $this->person->id]);

        $this->as($this->admin)->postJson("/api/v1/admin/users/{$this->church->id}/verify")->assertOk();
        $this->assertSame('organization_review', $this->church->fresh()->approval_source);

        // Retrait par le chemin existant (vérification personnelle) : source effacée, audit.
        $this->as($this->admin)->postJson("/api/v1/admin/users/{$this->person->id}/revoke-verification", ['reason' => 'Usurpation signalée'])->assertOk();
        $this->assertNull($this->person->fresh()->approval_source);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'user.badge_revoked', 'subject_id' => $this->person->id]);
    }

    public function test_author_resources_expose_approval_fields(): void
    {
        $this->as($this->admin)->postJson("/api/v1/admin/users/{$this->church->id}/approval", ['reason' => 'Église partenaire'])->assertOk();
        $t = Testimony::create([
            'user_id' => $this->church->id, 'title' => 'Culte de délivrance', 'type' => 'text', 'category_slug' => 'delivrance',
            'body_text' => 'Gloire à Dieu.', 'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ]);

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/testimonies/{$t->id}")->assertOk()
            ->assertJsonPath('data.user.is_approved', true)
            ->assertJsonPath('data.user.account_type', 'organization')
            ->assertJsonPath('data.user.approval_badge', 'approved_organization')
            ->assertJsonPath('data.user.approved_at', $this->church->fresh()->verified_at->toIso8601String());

        $this->as($this->person)->getJson('/api/v1/users/me')->assertOk()
            ->assertJsonPath('data.is_approved', false)
            ->assertJsonPath('data.account_type', 'individual')
            ->assertJsonPath('data.approval_badge', null)
            ->assertJsonPath('data.approved_at', null);

        // Auteur intégré en camelCase (requête de prière) : mêmes trois champs.
        $prayer = $this->as($this->church)->postJson('/api/v1/prayer/requests', ['body' => 'Priez pour notre campagne d’évangélisation.'])
            ->assertCreated()->json('data');
        $this->assertTrue($prayer['author']['is_approved']);
        $this->assertSame('approved_organization', $prayer['author']['approval_badge']);
        $this->assertSame('organization', $prayer['author']['account_type']);
        $this->assertNotNull($prayer['author']['approved_at']);
    }

    /**
     * Retrait du badge (type dédié account_approval_revoked) ≠ refus d'une demande
     * (account_verification_rejected, organization_rejected inchangés).
     */
    public function test_badge_revocation_has_its_own_notification_type(): void
    {
        // Organisation jamais vérifiée, demande refusée : refus, pas retrait.
        $this->as($this->admin)->postJson("/api/v1/admin/users/{$this->church->id}/reject-verification", ['reason' => 'Site introuvable'])->assertOk();
        $this->assertDatabaseHas('app_notifications', ['recipient_id' => $this->church->id, 'type' => 'organization_rejected']);
        $this->assertDatabaseMissing('app_notifications', ['recipient_id' => $this->church->id, 'type' => 'account_approval_revoked']);

        // Personne approuvée puis vérification retirée (chemin existant) : retrait.
        $this->as($this->admin)->postJson("/api/v1/admin/users/{$this->person->id}/approval", ['reason' => 'Pasteur connu'])->assertOk();
        $this->as($this->admin)->postJson("/api/v1/admin/users/{$this->person->id}/revoke-verification", ['reason' => 'Fonction terminée'])->assertOk();
        $this->assertDatabaseHas('app_notifications', ['recipient_id' => $this->person->id, 'type' => 'account_approval_revoked']);
        $this->assertDatabaseMissing('app_notifications', ['recipient_id' => $this->person->id, 'type' => 'account_verification_rejected']);
        $this->assertTrue(\App\Enums\NotificationType::AccountApprovalRevoked->isSystem());
    }

    public function test_back_office_filter_button_and_permissions(): void
    {
        $this->as($this->modo)->postJson("/api/v1/admin/users/{$this->person->id}/approval", ['reason' => 'Test'])->assertForbidden();
        $this->as($this->person)->postJson("/api/v1/admin/users/{$this->church->id}/approval", ['reason' => 'Test'])->assertForbidden();
        $this->as($this->modo)->post("/admin/users/{$this->person->id}/badge", ['reason' => 'Test'])->assertForbidden();
        $this->assertFalse($this->person->fresh()->isApproved());

        $this->as($this->admin)->get("/admin/users/{$this->person->id}")->assertOk()->assertSee('Attribuer le badge Approuvé');
        $this->as($this->admin)->post("/admin/users/{$this->person->id}/badge", ['reason' => 'Évangéliste reconnu'])->assertSessionHas('success');
        $this->assertTrue($this->person->fresh()->isApproved());

        $this->as($this->admin)->get('/admin/users?tab=approved')->assertOk()
            ->assertSee('Marie')->assertDontSee('Eglise@example.com');
        $ids = collect($this->as($this->admin)->getJson('/api/v1/admin/users?approved=1')->assertOk()->json('data'))->pluck('id');
        $this->assertEquals([$this->person->id], $ids->all());

        $this->as($this->admin)->delete("/admin/users/{$this->person->id}/badge", ['reason' => 'Erreur d’attribution'])->assertSessionHas('success');
        $this->assertFalse($this->person->fresh()->isApproved());
        $this->assertDatabaseHas('app_notifications', ['recipient_id' => $this->person->id, 'type' => 'account_approval_revoked']);
    }
}
