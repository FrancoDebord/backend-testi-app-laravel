<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le pays est enregistré en toutes lettres (« Côte d'Ivoire »), jamais limité à un code de 3 caractères. */
class CountryNameTest extends TestCase
{
    use RefreshDatabase;

    private const COUNTRY = "Côte d'Ivoire";

    public function test_web_registration_keeps_the_full_country_name(): void
    {
        $this->post('/register', [
            'account_type' => 'individual', 'display_name' => 'Jean Dupont', 'email' => 'jean@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'country' => self::COUNTRY,
        ])->assertSessionHasNoErrors();

        $this->assertSame(self::COUNTRY, User::where('email', 'jean@example.com')->value('country'));
    }

    public function test_api_registration_keeps_the_full_country_name(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Jean', 'last_name' => 'Dupont', 'display_name' => 'Jean Dupont', 'email' => 'jean@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'country' => self::COUNTRY,
        ])->assertSuccessful();

        $this->assertSame(self::COUNTRY, User::where('email', 'jean@example.com')->value('country'));
    }

    public function test_phone_sign_in_accepts_a_full_country_name(): void
    {
        // La vérification du jeton Firebase échoue en test (401) : l'essentiel est que la validation n'ait pas refusé le pays (422).
        $response = $this->postJson('/api/v1/auth/phone', [
            'firebase_token' => 'jeton', 'phone' => '+22501020304', 'first_name' => 'Awa', 'country' => self::COUNTRY,
        ]);

        $this->assertNotSame(422, $response->status(), $response->getContent());
    }

    public function test_web_forms_offer_the_country_list(): void
    {
        $html = $this->get('/register')->assertOk()
            ->assertSee('data-country-select', false)
            ->assertSee('<option value="">Choisir un pays</option>', false)
            ->getContent();

        $this->assertStringContainsString('<option value="Côte d&#039;Ivoire" data-code="ci" data-flag=', $html);
        $this->assertStringContainsString('<option value="Bénin" data-code="bj" data-flag=', $html);
        // Ordre alphabétique sans tenir compte des accents : « Égypte » entre « Djibouti » et « Érythrée ».
        $list = \App\Support\Countries::all();
        $this->assertLessThan(array_search('Érythrée', $list), array_search('Égypte', $list));
        $this->assertGreaterThan(array_search('Djibouti', $list), array_search('Égypte', $list));
        $this->assertGreaterThan(190, count($list));
    }

    public function test_every_country_has_a_flag(): void
    {
        foreach (\App\Support\Countries::all() as $country) {
            $code = \App\Support\Countries::code($country);
            $this->assertNotNull($code, "Pas de code pour {$country}");
            $this->assertFileExists(public_path("flags/{$code}.svg"), "Drapeau manquant pour {$country}");
        }

        $this->get('/register')->assertOk()
            ->assertSee('data-flag="' . asset('flags/ci.svg') . '"', false);
    }

    public function test_web_registration_refuses_a_country_outside_the_list(): void
    {
        $this->from('/register')->post('/register', [
            'account_type' => 'individual', 'display_name' => 'Jean Dupont', 'email' => 'jean@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'country' => 'Atlantide',
        ])->assertRedirect('/register')->assertSessionHasErrors(['country' => 'Choisissez un pays dans la liste.']);

        $this->assertDatabaseMissing('users', ['email' => 'jean@example.com']);
    }

    public function test_profile_keeps_a_legacy_country_value(): void
    {
        $user = User::create([
            'display_name' => 'Jean Dupont', 'email' => 'jean@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active', 'country' => 'CIV',
        ]);

        // La valeur enregistrée reste proposée et sélectionnée, et peut être conservée.
        $this->actingAs($user)->get('/profile/edit')->assertOk()
            ->assertSee('<option value="CIV" selected>CIV</option>', false);
        $this->actingAs($user)->put('/profile', ['display_name' => 'Jean Dupont', 'country' => 'CIV'])->assertSessionHasNoErrors();

        // Une autre valeur hors liste est refusée.
        $this->actingAs($user)->put('/profile', ['display_name' => 'Jean Dupont', 'country' => 'Atlantide'])
            ->assertSessionHasErrors('country');
        $this->assertSame('CIV', $user->fresh()->country);
    }

    public function test_profile_update_keeps_the_full_country_name(): void
    {
        $user = User::create([
            'display_name' => 'Jean Dupont', 'email' => 'jean@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active',
        ]);

        $this->actingAs($user)->put('/profile', ['display_name' => 'Jean Dupont', 'country' => 'République centrafricaine'])
            ->assertSessionHasNoErrors();
        $this->assertSame('République centrafricaine', $user->fresh()->country);

        $this->actingAs($user)->putJson('/api/v1/users/me', ['country' => self::COUNTRY])->assertSuccessful();
        $this->assertSame(self::COUNTRY, $user->fresh()->country);
    }
}
