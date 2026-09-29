<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\OrganizationType;
use App\Enums\VerificationStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Modification des informations d'une organisation depuis « Modifier le profil » du site.
 * Voir docs/fonctionnalites/comptes-organisation.md (« Modifier son organisation depuis le site »).
 */
class WebOrganizationProfileEditTest extends TestCase
{
    use RefreshDatabase;

    private function organization(array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => 'Ministère Lumière', 'email' => 'ministere@example.com',
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
            'account_type' => 'organization', 'organization_name' => 'Ministère Lumière',
            'organization_type' => 'ministry', 'organization_city' => 'Cotonou',
            'verification_status' => 'pending',
        ], $attrs));
    }

    private function person(): User
    {
        return User::create([
            'display_name' => 'Jean Dupont', 'email' => 'jean@example.com',
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ]);
    }

    private function orgPayload(array $extra = []): array
    {
        return array_merge([
            'organization_name'    => 'Ministère Lumière',
            'organization_type'    => 'ministry',
            'organization_city'    => 'Cotonou',
            'organization_website' => '',
            'country'              => 'Bénin',
            'phone_country'        => 'bj',
            'phone'                => '01 97 00 00 01',
            'bio'                  => '',
        ], $extra);
    }

    public function test_edit_page_shows_organization_section_for_organization(): void
    {
        $org = $this->organization();

        $this->actingAs($org)->get('/profile/edit')
            ->assertOk()
            ->assertSee('id="organization-title"', false)
            ->assertSee('name="organization_name"', false)
            ->assertSee('name="organization_type"', false)
            ->assertSee('name="organization_city"', false)
            ->assertSee('name="organization_website"', false)
            ->assertSee('Média chrétien')
            ->assertSee('value="ministry" selected', false)
            ->assertSee('En attente de vérification')
            ->assertSee('badge-pending', false)
            ->assertDontSee('name="display_name"', false)
            ->assertDontSee('Modifier le nom remettra votre organisation en attente de vérification.');
    }

    public function test_verified_organization_sees_rename_warning(): void
    {
        $org = $this->organization(['verification_status' => 'verified', 'verified_at' => now()]);

        $this->actingAs($org)->get('/profile/edit')
            ->assertOk()
            ->assertSee('Vérifiée')
            ->assertSee('badge-validated', false)
            ->assertSee('Modifier le nom remettra votre organisation en attente de vérification.');
    }

    public function test_rejected_organization_sees_reason(): void
    {
        $org = $this->organization(['verification_status' => 'rejected', 'verification_note' => 'Site introuvable']);

        $this->actingAs($org)->get('/profile/edit')
            ->assertOk()
            ->assertSee('badge-rejected', false)
            ->assertSee('Vérification refusée')
            ->assertSee('Site introuvable');
    }

    public function test_individual_edit_page_is_unchanged(): void
    {
        $this->actingAs($this->person())->get('/profile/edit')
            ->assertOk()
            ->assertSee('name="display_name"', false)
            ->assertSee('Photo de profil')
            ->assertDontSee('name="organization_name"', false)
            ->assertDontSee('id="organization-title"', false);
    }

    public function test_organization_updates_city_type_and_website_without_losing_verification(): void
    {
        $org = $this->organization(['verification_status' => 'verified', 'verified_at' => now()]);

        $this->actingAs($org)->from('/profile/edit')->put('/profile', $this->orgPayload([
            'organization_type'    => 'church',
            'organization_city'    => 'Porto-Novo',
            'organization_website' => 'https://lumiere.example.org',
            'bio'                  => 'Nous annonçons l’Évangile.',
        ]))->assertRedirect('/profile/edit')
           ->assertSessionHas('success', 'Profil mis à jour.');

        $org->refresh();
        $this->assertSame(OrganizationType::Church, $org->organization_type);
        $this->assertSame('Porto-Novo', $org->organization_city);
        $this->assertSame('https://lumiere.example.org', $org->organization_website);
        $this->assertSame('Nous annonçons l’Évangile.', $org->bio);
        $this->assertSame(VerificationStatus::Verified, $org->verification_status);
        $this->assertNotNull($org->verified_at);
    }

    public function test_renaming_verified_organization_resets_to_pending_and_updates_display_name(): void
    {
        $org = $this->organization(['verification_status' => 'verified', 'verified_at' => now()]);

        $this->actingAs($org)->from('/profile/edit')->put('/profile', $this->orgPayload([
            'organization_name' => '  Ministère Lumière du Monde ',
            'display_name'      => 'Ignoré',
        ]))->assertRedirect('/profile/edit')
           ->assertSessionHas('success', 'Profil mis à jour. Votre organisation sera de nouveau vérifiée par notre équipe.');

        $org->refresh();
        $this->assertSame('Ministère Lumière du Monde', $org->organization_name);
        $this->assertSame('Ministère Lumière du Monde', $org->display_name);
        $this->assertSame(VerificationStatus::Pending, $org->verification_status);
        $this->assertNull($org->verified_at);
        $this->assertFalse($org->isVerified());
    }

    public function test_editing_rejected_organization_resends_request(): void
    {
        $org = $this->organization(['verification_status' => 'rejected', 'verification_note' => 'Site introuvable']);

        $this->actingAs($org)->put('/profile', $this->orgPayload([
            'organization_website' => 'https://lumiere.example.org',
        ]))->assertSessionHas('success', 'Profil mis à jour. Votre organisation sera de nouveau vérifiée par notre équipe.');

        $org->refresh();
        $this->assertSame(VerificationStatus::Pending, $org->verification_status);
        $this->assertNull($org->verification_note);
    }

    public function test_organization_name_is_required_and_website_validated(): void
    {
        $org = $this->organization();

        $this->actingAs($org)->from('/profile/edit')
            ->put('/profile', $this->orgPayload(['organization_name' => '']))
            ->assertRedirect('/profile/edit')
            ->assertSessionHasErrors('organization_name');

        $this->actingAs($org)->from('/profile/edit')
            ->put('/profile', $this->orgPayload(['organization_website' => 'ftp://lumiere.example.org']))
            ->assertSessionHasErrors('organization_website');

        $this->actingAs($org)->from('/profile/edit')
            ->put('/profile', $this->orgPayload(['organization_type' => 'secte']))
            ->assertSessionHasErrors('organization_type');

        $this->assertSame('Ministère Lumière', $org->fresh()->organization_name);
    }

    public function test_empty_type_keeps_saved_type(): void
    {
        $org = $this->organization();

        $this->actingAs($org)->put('/profile', $this->orgPayload(['organization_type' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame(OrganizationType::Ministry, $org->fresh()->organization_type);
    }

    public function test_individual_cannot_become_organization(): void
    {
        $person = $this->person();

        $this->actingAs($person)->put('/profile', [
            'display_name'        => 'Jean D.',
            'organization_name'   => 'Mon Église',
            'account_type'        => 'organization',
            'verification_status' => 'verified',
        ])->assertSessionHasNoErrors()
          ->assertSessionHas('success', 'Profil mis à jour.');

        $person->refresh();
        $this->assertSame('Jean D.', $person->display_name);
        $this->assertSame(AccountType::Individual, $person->account_type);
        $this->assertNull($person->organization_name);
        $this->assertNull($person->verification_status);
    }

    public function test_individual_still_needs_display_name(): void
    {
        $this->actingAs($this->person())->from('/profile/edit')
            ->put('/profile', ['display_name' => ''])
            ->assertSessionHasErrors('display_name');
    }

    public function test_organization_cannot_self_verify(): void
    {
        $org = $this->organization();

        $this->actingAs($org)->put('/profile', $this->orgPayload(['verification_status' => 'verified']));

        $this->assertSame(VerificationStatus::Pending, $org->fresh()->verification_status);
    }
}
