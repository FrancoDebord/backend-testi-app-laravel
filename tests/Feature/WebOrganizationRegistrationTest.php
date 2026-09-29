<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Enums\VerificationStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Inscription depuis le site : personne ou organisation.
 * Voir docs/fonctionnalites/comptes-organisation.md (« Inscription depuis le site »).
 */
class WebOrganizationRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function organizationPayload(array $extra = []): array
    {
        return array_merge([
            'account_type'          => 'organization',
            'display_name'          => '',
            'email'                 => 'eglise@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
            'organization_name'     => '  Église de la Grâce ',
            'organization_type'     => 'church',
            'organization_city'     => 'Cotonou',
            'organization_website'  => 'https://eglise-grace.example.org',
            'phone_country'         => 'bj',
            'phone'                 => '01 97 00 00 00',
        ], $extra);
    }

    public function test_register_page_shows_account_type_choice(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Je suis une personne')
            ->assertSee('Je représente une organisation')
            ->assertSee('Nom de l\'organisation', false)
            ->assertSee('Église')
            ->assertSee('Média chrétien')
            ->assertSee('Votre organisation sera vérifiée par notre équipe.')
            ->assertSee('value="individual" checked', false);
    }

    public function test_individual_registration_still_works(): void
    {
        $this->post('/register', [
            'display_name'          => 'Jean Dupont',
            'email'                 => 'jean@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
            // Champs organisation remplis sans JavaScript : ignorés pour une personne.
            'organization_name'     => 'Ne doit pas être enregistré',
        ])->assertRedirect(route('home'));

        $user = User::where('email', 'jean@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Jean Dupont', $user->display_name);
        $this->assertSame(AccountType::Individual, $user->account_type);
        $this->assertNull($user->organization_name);
        $this->assertNull($user->verification_status);
        $this->assertSame(UserAccountStatus::Active, $user->status);
    }

    public function test_individual_with_explicit_account_type(): void
    {
        $this->post('/register', [
            'account_type'          => 'individual',
            'display_name'          => 'Marie',
            'email'                 => 'marie@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect(route('home'));

        $this->assertSame(AccountType::Individual, User::where('email', 'marie@example.com')->first()->account_type);
    }

    public function test_organization_is_created_pending(): void
    {
        $response = $this->post('/register', $this->organizationPayload());

        $user = User::where('email', 'eglise@example.com')->firstOrFail();
        $response->assertRedirect(route('profiles.show', $user->id));
        $this->assertAuthenticatedAs($user);

        $this->assertSame(AccountType::Organization, $user->account_type);
        $this->assertSame('Église de la Grâce', $user->organization_name);
        $this->assertSame('Église de la Grâce', $user->display_name);
        $this->assertSame('church', $user->organization_type->value);
        $this->assertSame('Cotonou', $user->organization_city);
        $this->assertSame('https://eglise-grace.example.org', $user->organization_website);
        $this->assertSame(VerificationStatus::Pending, $user->verification_status);
        $this->assertSame(UserAccountStatus::Active, $user->status);
        $this->assertFalse($user->isVerified());
        $this->assertDatabaseHas('user_settings', ['user_id' => $user->id]);
    }

    public function test_organization_with_only_required_fields(): void
    {
        $this->post('/register', $this->organizationPayload([
            'organization_type' => '', 'organization_city' => '', 'organization_website' => '',
        ]))->assertSessionHasNoErrors();

        $user = User::where('email', 'eglise@example.com')->firstOrFail();
        $this->assertNull($user->organization_type);
        $this->assertSame(VerificationStatus::Pending, $user->verification_status);
    }

    public function test_own_profile_shows_pending_notice(): void
    {
        $this->post('/register', $this->organizationPayload());
        $user = User::where('email', 'eglise@example.com')->firstOrFail();

        $this->get(route('profiles.show', $user->id))
            ->assertOk()
            ->assertSee('Vérification en cours.');

        // Un visiteur ne voit pas l'avis.
        auth()->logout();
        $this->get(route('profiles.show', $user->id))
            ->assertOk()
            ->assertDontSee('Vérification en cours.');
    }

    public function test_organization_name_is_required_in_organization_mode(): void
    {
        $this->from('/register')
            ->post('/register', $this->organizationPayload(['organization_name' => '']))
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['organization_name'])
            ->assertSessionDoesntHaveErrors(['display_name']);

        $this->assertDatabaseMissing('users', ['email' => 'eglise@example.com']);
        $this->assertGuest();
    }

    public function test_display_name_is_required_in_individual_mode(): void
    {
        $this->from('/register')->post('/register', [
            'account_type'          => 'individual',
            'email'                 => 'jean@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors(['display_name'])
          ->assertSessionDoesntHaveErrors(['organization_name']);
    }

    public function test_invalid_organization_fields_are_rejected(): void
    {
        $this->from('/register')
            ->post('/register', $this->organizationPayload([
                'organization_type'    => 'sect',
                'organization_website' => 'ftp://exemple.org',
            ]))
            ->assertSessionHasErrors(['organization_type', 'organization_website']);

        $this->from('/register')
            ->post('/register', $this->organizationPayload(['account_type' => 'company']))
            ->assertSessionHasErrors(['account_type']);

        $this->assertDatabaseMissing('users', ['email' => 'eglise@example.com']);
    }

    public function test_old_input_restores_organization_mode(): void
    {
        $this->from('/register')
            ->post('/register', $this->organizationPayload(['organization_name' => '', 'organization_city' => 'Porto-Novo', 'organization_type' => 'ngo']))
            ->assertRedirect('/register');

        $this->get('/register')
            ->assertOk()
            ->assertSee('value="organization" checked', false)
            ->assertDontSee('value="individual" checked', false)
            ->assertSee('value="Porto-Novo"', false)
            ->assertSee('value="ngo" selected', false)
            ->assertSee('Le champ nom de l’organisation est obligatoire.');
    }
}
