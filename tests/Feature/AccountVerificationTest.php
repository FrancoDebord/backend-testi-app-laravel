<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Testimony;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Vérification des comptes personnels : demande avec pièces privées, examen par un
 * administrateur (API et site), badge, droits inchangés.
 * Voir docs/fonctionnalites/verification-des-comptes.md
 */
class AccountVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $person;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->admin  = $this->makeUser('admin', ['role' => 'administrateur']);
        $this->person = $this->makeUser('pasteur', ['display_name' => 'Pasteur Jean']);
    }

    private function makeUser(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => ucfirst($name), 'email' => "{$name}@example.com",
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'full_name' => 'Jean Kossi Adjovi',
            'role'      => 'pastor',
            'church'    => 'Église de la Grâce, Cotonou',
            'links'     => ['https://youtube.com/@pasteurjean', ''],
            'message'   => 'Merci de vérifier mon compte.',
            'documents' => [
                UploadedFile::fake()->image('identite.jpg', 800, 500),
                UploadedFile::fake()->create('lettre.pdf', 200, 'application/pdf'),
            ],
        ], $extra);
    }

    private function submit(?User $as = null, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($as ?? $this->person)->post('/api/v1/users/me/verification', $this->payload($extra), ['Accept' => 'application/json']);
    }

    public function test_person_submits_a_request_with_private_documents(): void
    {
        $this->actingAs($this->person)->getJson('/api/v1/users/me/verification')
            ->assertOk()->assertJsonPath('data.status', 'none')->assertJsonPath('data.canRequest', true)
            ->assertJsonCount(6, 'data.roles');

        $this->submit()->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.canRequest', false)
            ->assertJsonPath('data.request.documentCount', 2)
            ->assertJsonPath('data.request.links', ['https://youtube.com/@pasteurjean'])
            ->assertJsonMissingPath('data.request.documents');

        $request = VerificationRequest::firstOrFail();
        $this->assertSame('pending', $request->status);
        $this->assertSame('pending', $this->person->fresh()->verification_status->value);
        foreach ($request->documents as $doc) {
            Storage::disk('local')->assertExists($doc['path']);
            Storage::disk('public')->assertMissing($doc['path']);
        }

        // Une seule demande à la fois.
        $this->submit()->assertStatus(422);
        $this->assertSame(1, VerificationRequest::count());
    }

    public function test_validation(): void
    {
        $this->submit(extra: ['full_name' => '', 'role' => 'roi', 'church' => '', 'documents' => []])
            ->assertStatus(422)->assertJsonValidationErrors(['full_name', 'role', 'church', 'documents']);
        $this->submit(extra: ['role' => 'other', 'role_other' => ''])
            ->assertStatus(422)->assertJsonValidationErrors(['role_other']);
        $this->submit(extra: ['links' => ['pas-un-lien']])
            ->assertStatus(422)->assertJsonValidationErrors(['links.0']);
        $this->submit(extra: ['documents' => [UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')]])
            ->assertStatus(422)->assertJsonValidationErrors(['documents.0']);
        $this->assertSame(0, VerificationRequest::count());
    }

    public function test_organizations_cannot_use_the_personal_flow(): void
    {
        $org = $this->makeUser('org', ['account_type' => 'organization', 'organization_name' => 'Église', 'verification_status' => 'pending']);
        $this->submit($org)->assertStatus(422);
        $this->actingAs($org)->getJson('/api/v1/users/me/verification')->assertOk()->assertJsonPath('data.canRequest', false);
    }

    public function test_documents_are_only_reachable_by_admins(): void
    {
        $this->submit()->assertCreated();
        $request = VerificationRequest::firstOrFail();
        $path = $request->documents[0]['path'];

        // Aucune URL publique : ni /storage/…, ni la route API sans droits.
        $this->app['auth']->forgetGuards();
        $this->get('/storage/' . $path)->assertNotFound();
        $this->getJson("/api/v1/admin/verification-requests/{$request->id}/documents/0")->assertUnauthorized();
        $this->actingAs($this->person)->getJson("/api/v1/admin/verification-requests/{$request->id}/documents/0")->assertForbidden();
        $moderator = $this->makeUser('modo', ['role' => 'moderateur']);
        $this->actingAs($moderator)->getJson("/api/v1/admin/verification-requests/{$request->id}")->assertForbidden();
        $this->actingAs($this->person)->get(route('admin.verifications.document', [$request->id, 0]))->assertForbidden();

        $this->actingAs($this->admin)->get("/api/v1/admin/verification-requests/{$request->id}/documents/0")
            ->assertOk()->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->admin)->get(route('admin.verifications.document', [$request->id, 1]))->assertOk();
        $this->actingAs($this->admin)->get("/api/v1/admin/verification-requests/{$request->id}/documents/5")->assertNotFound();

        $this->actingAs($this->admin)->getJson("/api/v1/admin/verification-requests/{$request->id}")
            ->assertOk()->assertJsonPath('data.documents.0.isImage', true)
            ->assertJsonPath('data.documents.1.isImage', false)
            ->assertJsonMissingPath('data.documents.0.path');
    }

    public function test_admin_approves_and_badge_appears_everywhere(): void
    {
        $this->submit()->assertCreated();
        $request = VerificationRequest::firstOrFail();

        $this->actingAs($this->admin)->getJson('/api/v1/admin/verification-requests')
            ->assertOk()->assertJsonPath('data.pendingCount', 1)->assertJsonPath('data.items.0.id', $request->id);

        $this->actingAs($this->admin)->postJson("/api/v1/admin/verification-requests/{$request->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $person = $this->person->fresh();
        $this->assertTrue($person->isVerified());
        $this->assertFalse($person->isVerifiedOrganization());
        $this->assertTrue(AppNotification::where('recipient_id', $person->id)->where('type', 'account_verified')->exists());

        // Badge dans la ressource utilisateur et sur le profil web (vus par un visiteur).
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/users/{$person->id}")->assertOk()
            ->assertJsonPath('data.is_verified', true)->assertJsonPath('data.account_type', 'individual');
        $this->get(route('profiles.show', $person->id))->assertOk()->assertSee('Compte vérifié');

        $testimony = Testimony::create([
            'user_id' => $person->id, 'title' => 'Guérison', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'approved',
        ]);
        $this->getJson("/api/v1/testimonies/{$testimony->id}")->assertOk()->assertJsonPath('data.user.is_verified', true);

        // Déjà traitée.
        $this->actingAs($this->admin)->postJson("/api/v1/admin/verification-requests/{$request->id}/approve")->assertStatus(422);
        $this->actingAs($person)->getJson('/api/v1/users/me/verification')->assertJsonPath('data.status', 'verified');
    }

    public function test_verified_person_gets_no_organization_rights(): void
    {
        $this->person->forceFill(['verification_status' => 'verified', 'verified_at' => now()])->save();

        $this->actingAs($this->person)->postJson('/api/v1/events', [
            'title' => 'Croisade', 'type' => 'crusade',
            'starts_at' => now()->addDays(3)->toIso8601String(), 'ends_at' => now()->addDays(4)->toIso8601String(),
            'city' => 'Cotonou',
        ])->assertForbidden();
        $this->assertFalse(\App\Models\Event::canBeCreatedBy($this->person->fresh()));
    }

    public function test_rejection_with_reason_then_new_request_after_delay(): void
    {
        $this->submit()->assertCreated();
        $request = VerificationRequest::firstOrFail();

        $this->actingAs($this->admin)->postJson("/api/v1/admin/verification-requests/{$request->id}/reject", ['reason' => ''])
            ->assertStatus(422)->assertJsonValidationErrors(['reason']);
        $this->actingAs($this->admin)->postJson("/api/v1/admin/verification-requests/{$request->id}/reject", ['reason' => 'Pièce illisible'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->assertFalse($this->person->fresh()->isVerified());
        $notif = AppNotification::where('recipient_id', $this->person->id)->where('type', 'account_verification_rejected')->firstOrFail();
        $this->assertStringContainsString('Pièce illisible', $notif->message);

        // Statut « refusé » non exposé au public, visible par la personne.
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/users/{$this->person->id}")->assertJsonPath('data.verification_status', null);
        $this->actingAs($this->person)->getJson('/api/v1/users/me/verification')
            ->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.reason', 'Pièce illisible')
            ->assertJsonPath('data.canRequest', false);
        $this->submit()->assertStatus(422);

        $this->travel(25)->hours();
        $this->actingAs($this->person)->getJson('/api/v1/users/me/verification')->assertJsonPath('data.canRequest', true);
        $this->submit()->assertCreated();
        $this->assertSame(2, VerificationRequest::count());
    }

    public function test_admin_revokes_a_verification(): void
    {
        $this->submit()->assertCreated();
        $request = VerificationRequest::firstOrFail();
        $this->actingAs($this->admin)->postJson("/api/v1/admin/verification-requests/{$request->id}/approve")->assertOk();

        $this->actingAs($this->admin)->postJson("/api/v1/admin/users/{$this->person->id}/revoke-verification", ['reason' => 'Usurpation signalée'])
            ->assertOk();

        $this->assertFalse($this->person->fresh()->isVerified());
        $this->assertSame('revoked', $request->fresh()->status);
        $this->actingAs($this->person)->getJson('/api/v1/users/me/verification')
            ->assertJsonPath('data.status', 'revoked')->assertJsonPath('data.reason', 'Usurpation signalée');
        // Déjà retirée.
        $this->actingAs($this->admin)->postJson("/api/v1/admin/users/{$this->person->id}/revoke-verification", ['reason' => 'Encore'])
            ->assertStatus(422);
    }

    public function test_non_admin_cannot_review(): void
    {
        $this->submit()->assertCreated();
        $request = VerificationRequest::firstOrFail();
        $this->actingAs($this->person)->postJson("/api/v1/admin/verification-requests/{$request->id}/approve")->assertForbidden();
        $this->actingAs($this->person)->post(route('admin.verifications.approve', $request->id))->assertForbidden();
        $this->assertFalse($this->person->fresh()->isVerified());
    }

    public function test_web_pages_request_and_review(): void
    {
        $this->actingAs($this->person)->get(route('profile.verification'))->assertOk()->assertSee('Faire vérifier mon compte')
            ->assertSee('Pièces justificatives');
        $this->actingAs($this->person)->post(route('profile.verification.store'), $this->payload())
            ->assertRedirect(route('profile.verification'));
        $this->actingAs($this->person)->get(route('profile.verification'))->assertOk()->assertSee('Demande en cours');

        $request = VerificationRequest::firstOrFail();
        $this->actingAs($this->admin)->get(route('admin.verifications.index'))->assertOk()->assertSee('Jean Kossi Adjovi');
        $this->actingAs($this->admin)->get(route('admin.verifications.show', $request->id))->assertOk()
            ->assertSee('Pièces justificatives')->assertSee(route('admin.verifications.document', [$request->id, 0]), false);

        $this->actingAs($this->admin)->post(route('admin.verifications.reject', $request->id), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->actingAs($this->admin)->post(route('admin.verifications.approve', $request->id))->assertRedirect();
        $this->assertTrue($this->person->fresh()->isVerified());

        $this->actingAs($this->admin)->get(route('admin.users.show', $this->person->id))->assertOk()->assertSee('Retirer le badge');
        $this->actingAs($this->admin)->post(route('admin.users.revoke-verification', $this->person->id), ['revoke_reason' => 'Fonction terminée'])
            ->assertRedirect();
        $this->assertFalse($this->person->fresh()->isVerified());
    }

    public function test_documents_are_deleted_with_the_account(): void
    {
        $this->submit()->assertCreated();
        $path = VerificationRequest::firstOrFail()->documents[0]['path'];
        Storage::disk('local')->assertExists($path);

        $this->person->delete();

        Storage::disk('local')->assertMissing($path);
        $this->assertSame([], VerificationRequest::firstOrFail()->documents);
    }
}
