<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comptes organisation : inscription, profil, vérification par un administrateur.
 * Voir docs/fonctionnalites/comptes-organisation.md
 */
class OrganizationAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create([
            'display_name' => 'Admin', 'email' => 'admin@example.com',
            'password' => 'secret123', 'role' => 'administrateur', 'status' => 'active',
        ]);
    }

    private function registerOrganization(array $extra = []): \Illuminate\Testing\TestResponse
    {
        // Charge utile envoyée par l'application mobile (last_name vide).
        return $this->postJson('/api/v1/auth/register', array_merge([
            'account_type'          => 'organization',
            'first_name'            => 'Église de la Grâce',
            'last_name'             => '',
            'name'                  => 'Église de la Grâce',
            'display_name'          => 'Église de la Grâce',
            'email'                 => 'eglise@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
            'organization_name'     => 'Église de la Grâce',
            'organization_type'     => 'church',
            'organization_city'     => 'Cotonou',
            'organization_website'  => 'https://eglise-grace.example.org',
        ], $extra));
    }

    private function organization(array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => 'Ministère Lumière', 'email' => 'ministere@example.com',
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
            'account_type' => 'organization', 'organization_name' => 'Ministère Lumière',
            'organization_type' => 'ministry', 'verification_status' => 'pending',
        ], $attrs));
    }

    public function test_individual_registration_defaults(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Jean', 'last_name' => 'Dupont', 'display_name' => 'Jean Dupont',
            'email' => 'jean@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertCreated()
          ->assertJsonPath('data.user.account_type', 'individual')
          ->assertJsonPath('data.user.is_verified', false)
          ->assertJsonPath('data.user.verification_status', null)
          ->assertJsonPath('data.user.organization_name', null);

        $user = User::where('email', 'jean@example.com')->first();
        $this->assertFalse($user->isOrganization());
        $this->assertNull($user->verification_status);
    }

    public function test_organization_registration_stores_fields_and_is_pending(): void
    {
        $this->registerOrganization()->assertCreated()
            ->assertJsonPath('data.user.account_type', 'organization')
            ->assertJsonPath('data.user.organization_name', 'Église de la Grâce')
            ->assertJsonPath('data.user.organization_type', 'church')
            ->assertJsonPath('data.user.organization_city', 'Cotonou')
            ->assertJsonPath('data.user.organization_website', 'https://eglise-grace.example.org')
            ->assertJsonPath('data.user.is_verified', false)
            ->assertJsonPath('data.user.verification_status', 'pending');

        $user = User::where('email', 'eglise@example.com')->first();
        $this->assertTrue($user->isOrganization());
        $this->assertFalse($user->isVerified());
        $this->assertSame('pending', $user->verification_status->value);
        $this->assertSame('Église', $user->organization_type->label());
    }

    public function test_organization_registration_validation(): void
    {
        $this->registerOrganization(['organization_name' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('organization_name');

        $this->registerOrganization(['organization_type' => 'secte'])
            ->assertUnprocessable()->assertJsonValidationErrors('organization_type');

        $this->registerOrganization(['organization_website' => 'pas une adresse'])
            ->assertUnprocessable()->assertJsonValidationErrors('organization_website');

        $this->registerOrganization(['account_type' => 'entreprise'])
            ->assertUnprocessable()->assertJsonValidationErrors('account_type');

        $this->assertSame(0, User::where('email', 'eglise@example.com')->count());
    }

    public function test_renaming_a_verified_organization_resets_verification(): void
    {
        $org = $this->organization([
            'verification_status' => 'verified', 'verified_at' => now(), 'verified_by' => $this->admin->id,
        ]);
        $this->assertTrue($org->isVerified());

        // Ville seule : la vérification est conservée ; type null ignoré.
        $this->actingAs($org)->putJson('/api/v1/users/me', [
            'organization_city' => 'Porto-Novo', 'organization_type' => null,
        ])->assertOk()
          ->assertJsonPath('data.organization_city', 'Porto-Novo')
          ->assertJsonPath('data.organization_type', 'ministry')
          ->assertJsonPath('data.is_verified', true);

        $this->actingAs($org)->putJson('/api/v1/users/me', [
            'display_name' => 'Ministère Lumière du Monde', 'organization_name' => 'Ministère Lumière du Monde',
        ])->assertOk()
          ->assertJsonPath('data.organization_name', 'Ministère Lumière du Monde')
          ->assertJsonPath('data.verification_status', 'pending')
          ->assertJsonPath('data.is_verified', false);

        $org->refresh();
        $this->assertNull($org->verified_at);
        $this->assertNull($org->verified_by);
    }

    public function test_update_validation_and_individual_cannot_self_convert(): void
    {
        $org = $this->organization();
        $this->actingAs($org)->putJson('/api/v1/users/me', ['organization_name' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('organization_name');
        $this->actingAs($org)->putJson('/api/v1/users/me', ['organization_website' => 'ftp:/x'])
            ->assertUnprocessable()->assertJsonValidationErrors('organization_website');

        $person = User::create([
            'display_name' => 'Marie', 'email' => 'marie@example.com',
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ]);
        $this->actingAs($person)->putJson('/api/v1/users/me', [
            'account_type' => 'organization', 'verification_status' => 'verified', 'is_verified' => true,
            'organization_name' => 'Fausse Église', 'verified_at' => now()->toIso8601String(),
        ])->assertOk()
          ->assertJsonPath('data.account_type', 'individual')
          ->assertJsonPath('data.is_verified', false)
          ->assertJsonPath('data.verification_status', null)
          ->assertJsonPath('data.organization_name', null);

        // Une organisation ne peut pas non plus se déclarer vérifiée.
        $this->actingAs($org)->putJson('/api/v1/users/me', ['verification_status' => 'verified'])
            ->assertOk()->assertJsonPath('data.verification_status', 'pending');
    }

    public function test_admin_verifies_and_rejects_via_api(): void
    {
        $org = $this->organization();

        $this->actingAs($this->admin)->getJson('/api/v1/admin/users?account_type=organization&verification_status=pending')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $org->id)
            ->assertJsonPath('meta.pending_organizations', 1);

        $this->actingAs($this->admin)->postJson("/api/v1/admin/users/{$org->id}/verify")
            ->assertOk()->assertJsonPath('data.is_verified', true)->assertJsonPath('data.verification_status', 'verified');

        $org->refresh();
        $this->assertTrue($org->isVerified());
        $this->assertSame($this->admin->id, $org->verified_by);
        $this->assertNotNull($org->verified_at);
        $this->assertTrue(AppNotification::where('recipient_id', $org->id)->where('type', 'organization_verified')->exists());

        $this->actingAs($this->admin)->postJson("/api/v1/admin/users/{$org->id}/reject-verification", ['reason' => 'Site introuvable'])
            ->assertOk()->assertJsonPath('data.is_verified', false)->assertJsonPath('data.verification_status', 'rejected');

        $org->refresh();
        $this->assertSame('Site introuvable', $org->verification_note);
        $this->assertNull($org->verified_at);
        $notif = AppNotification::where('recipient_id', $org->id)->where('type', 'organization_rejected')->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('Site introuvable', $notif->message);

        // Une personne ne peut pas être « vérifiée ».
        $person = User::create(['display_name' => 'Paul', 'email' => 'paul@example.com', 'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active']);
        $this->actingAs($this->admin)->postJson("/api/v1/admin/users/{$person->id}/verify")->assertStatus(422);
    }

    public function test_non_admin_cannot_verify(): void
    {
        $org       = $this->organization();
        $moderator = User::create(['display_name' => 'Modo', 'email' => 'modo@example.com', 'password' => 'secret123', 'role' => 'moderateur', 'status' => 'active']);

        $this->actingAs($moderator)->postJson("/api/v1/admin/users/{$org->id}/verify")->assertForbidden();
        $this->actingAs($org)->postJson("/api/v1/admin/users/{$org->id}/verify")->assertForbidden();
        $this->actingAs($org)->postJson("/api/v1/admin/users/{$org->id}/reject-verification")->assertForbidden();

        $this->assertFalse($org->fresh()->isVerified());
    }

    public function test_admin_web_pages_and_actions(): void
    {
        $org = $this->organization();

        $this->actingAs($this->admin)->get(route('admin.users.index', ['tab' => 'pending']))
            ->assertOk()->assertSee('Organisations en attente')->assertSee('Ministère Lumière');
        $this->actingAs($this->admin)->get(route('admin.users.show', $org->id))
            ->assertOk()->assertSee('Ministère')->assertSee('En attente de vérification');

        $this->actingAs($this->admin)->post(route('admin.users.verify', $org->id))->assertRedirect();
        $this->assertTrue($org->fresh()->isVerified());

        $this->actingAs($this->admin)->post(route('admin.users.reject-verification', $org->id), ['reason' => 'Doublon'])->assertRedirect();
        $this->assertSame('rejected', $org->fresh()->verification_status->value);
    }

    public function test_testimony_json_exposes_author_verification(): void
    {
        $org = $this->organization(['verification_status' => 'verified', 'verified_at' => now()]);
        $testimony = Testimony::create([
            'user_id' => $org->id, 'title' => 'Culte de louange', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'approved',
        ]);

        $this->getJson("/api/v1/testimonies/{$testimony->id}")->assertOk()
            ->assertJsonPath('data.user.account_type', 'organization')
            ->assertJsonPath('data.user.is_verified', true)
            ->assertJsonPath('data.user.organization_name', 'Ministère Lumière');

        $this->get(route('profiles.show', $org->id))->assertOk()->assertSee('Organisation vérifiée');
    }
}
