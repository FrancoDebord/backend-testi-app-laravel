<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Connexion par téléphone de contact + mot de passe, API (POST /api/v1/auth/login) et site (POST /login).
 * Voir docs/fonctionnalites/telephone.md
 */
class PhoneLoginTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::create($attrs + [
            'display_name' => 'Awa D.', 'email' => 'awa' . uniqid() . '@example.com', 'password' => 'secret123',
            'phone' => '+2290197000000', 'phone_country' => 'bj',
            'role' => 'utilisateur', 'status' => 'active', 'is_active' => true,
        ]);
    }

    private function apiLogin(array $body)
    {
        return $this->postJson('/api/v1/auth/login', $body);
    }

    private function assertApiSuccess($res, User $user): void
    {
        $res->assertOk()
            ->assertJsonStructure(['success', 'message', 'data' => ['user' => ['id'], 'access_token', 'token_type']])
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_api_login_with_e164_phone_and_country(): void
    {
        $user = $this->makeUser();
        $this->assertApiSuccess($this->apiLogin(['phone' => '0197000000', 'phone_country' => 'bj', 'password' => 'secret123']), $user);
    }

    public function test_api_login_with_number_typed_with_spaces_and_leading_zero(): void
    {
        $user = $this->makeUser();
        $this->assertApiSuccess($this->apiLogin(['phone' => '01 97 00 00 00', 'phone_country' => 'BJ', 'password' => 'secret123']), $user);

        // France : le 0 initial est le préfixe national, retiré.
        $fr = $this->makeUser(['phone' => '+33612345678', 'phone_country' => 'fr']);
        $this->assertApiSuccess($this->apiLogin(['phone' => '06 12 34 56 78', 'phone_country' => 'fr', 'password' => 'secret123']), $fr);
    }

    public function test_api_login_with_international_form_with_or_without_country(): void
    {
        $user = $this->makeUser();
        $this->assertApiSuccess($this->apiLogin(['phone' => '+229 01 97 00 00 00', 'password' => 'secret123']), $user);
        $this->assertApiSuccess($this->apiLogin(['phone' => '+2290197000000', 'phone_country' => 'bj', 'password' => 'secret123']), $user);
        // Indicatif choisi différent de celui du numéro international : le numéro saisi l'emporte.
        $this->assertApiSuccess($this->apiLogin(['phone' => '+2290197000000', 'phone_country' => 'fr', 'password' => 'secret123']), $user);
    }

    public function test_api_wrong_password_and_unknown_phone_give_the_same_401(): void
    {
        $this->makeUser();
        $message = __('account.api.invalid_phone_credentials');

        $this->apiLogin(['phone' => '0197000000', 'phone_country' => 'bj', 'password' => 'mauvais'])
            ->assertStatus(401)->assertJsonPath('message', $message);
        $this->apiLogin(['phone' => '0199999999', 'phone_country' => 'bj', 'password' => 'secret123'])
            ->assertStatus(401)->assertJsonPath('message', $message);
    }

    public function test_api_legacy_raw_phone_is_found(): void
    {
        $raw   = $this->makeUser(['phone' => '97 00 00 01', 'phone_country' => null]);
        $zeros = $this->makeUser(['phone' => '00229 97-00-00-02', 'phone_country' => null]);

        // Valeur brute identique.
        $this->assertApiSuccess($this->apiLogin(['phone' => '97 00 00 01', 'password' => 'secret123']), $raw);
        // Mêmes chiffres, saisie différente.
        $this->assertApiSuccess($this->apiLogin(['phone' => '97000001', 'phone_country' => 'bj', 'password' => 'secret123']), $raw);
        $this->assertApiSuccess($this->apiLogin(['phone' => '+22997000002', 'password' => 'secret123']), $zeros);
    }

    public function test_api_ambiguous_legacy_phone_matches_no_account(): void
    {
        $this->makeUser(['phone' => '97 00 00 03', 'phone_country' => null]);
        $this->makeUser(['phone' => '97-00-00-03', 'phone_country' => null]);

        $this->apiLogin(['phone' => '97000003', 'password' => 'secret123'])->assertStatus(401);
    }

    public function test_api_banned_account_gets_403(): void
    {
        $this->makeUser(['status' => 'banned']);
        $this->apiLogin(['phone' => '0197000000', 'phone_country' => 'bj', 'password' => 'secret123'])
            ->assertStatus(403)->assertJsonPath('message', __('account.api.banned'));
    }

    public function test_api_account_without_password_gets_401(): void
    {
        $this->makeUser(['password' => null]);
        $this->apiLogin(['phone' => '0197000000', 'phone_country' => 'bj', 'password' => 'secret123'])
            ->assertStatus(401)->assertJsonPath('message', __('account.api.invalid_phone_credentials'));
    }

    public function test_api_validation_requires_email_or_phone_and_known_country(): void
    {
        $this->apiLogin(['password' => 'secret123'])->assertStatus(422)->assertJsonValidationErrors(['email', 'phone']);
        $this->apiLogin(['phone' => '0197000000', 'phone_country' => 'zz', 'password' => 'secret123'])
            ->assertStatus(422)->assertJsonValidationErrors(['phone_country']);
    }

    public function test_api_email_login_still_works(): void
    {
        $user = $this->makeUser(['email' => 'awa@example.com']);
        $this->assertApiSuccess($this->apiLogin(['email' => 'awa@example.com', 'password' => 'secret123']), $user);
        $this->apiLogin(['email' => 'awa@example.com', 'password' => 'mauvais'])->assertStatus(401);
    }

    public function test_api_login_is_throttled(): void
    {
        $this->makeUser();
        for ($i = 0; $i < 10; $i++) {
            $this->apiLogin(['phone' => '0197000000', 'phone_country' => 'bj', 'password' => 'mauvais'])->assertStatus(401);
        }
        $this->apiLogin(['phone' => '0197000000', 'phone_country' => 'bj', 'password' => 'secret123'])->assertStatus(429);
    }

    // ── Site ────────────────────────────────────────────────────────────

    public function test_web_login_page_offers_email_or_phone(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('name="login_method"', false)
            ->assertSee('value="phone"', false)
            ->assertSee('name="phone_country"', false)
            ->assertSee('name="phone"', false)
            ->assertSee('(+229)', false);
    }

    public function test_web_login_by_phone(): void
    {
        $user = $this->makeUser();

        $this->post('/login', ['login_method' => 'phone', 'phone_country' => 'bj', 'phone' => '01 97 00 00 00', 'password' => 'secret123'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_web_login_by_international_phone_and_legacy_phone(): void
    {
        $user = $this->makeUser();
        $this->post('/login', ['login_method' => 'phone', 'phone' => '+2290197000000', 'password' => 'secret123'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');

        $legacy = $this->makeUser(['phone' => '97 00 00 01', 'phone_country' => null]);
        $this->post('/login', ['login_method' => 'phone', 'phone_country' => 'bj', 'phone' => '97000001', 'password' => 'secret123'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($legacy);
    }

    public function test_web_wrong_password_and_unknown_phone_give_the_same_error(): void
    {
        $this->makeUser();

        $this->from('/login')->post('/login', ['login_method' => 'phone', 'phone_country' => 'bj', 'phone' => '0197000000', 'password' => 'mauvais'])
            ->assertRedirect('/login')->assertSessionHasErrors(['phone' => __('account.invalid_credentials')]);
        $this->from('/login')->post('/login', ['login_method' => 'phone', 'phone_country' => 'bj', 'phone' => '0199999999', 'password' => 'secret123'])
            ->assertRedirect('/login')->assertSessionHasErrors(['phone' => __('account.invalid_credentials')]);
        $this->assertGuest();
    }

    public function test_web_banned_and_passwordless_accounts_are_refused(): void
    {
        $this->makeUser(['status' => 'banned']);
        $this->from('/login')->post('/login', ['login_method' => 'phone', 'phone_country' => 'bj', 'phone' => '0197000000', 'password' => 'secret123'])
            ->assertSessionHasErrors(['phone' => __('account.banned')]);

        $this->makeUser(['phone' => '+2290197000009', 'password' => null]);
        $this->from('/login')->post('/login', ['login_method' => 'phone', 'phone_country' => 'bj', 'phone' => '0197000009', 'password' => 'secret123'])
            ->assertSessionHasErrors(['phone' => __('account.invalid_credentials')]);
        $this->assertGuest();
    }

    public function test_web_email_login_still_works_and_ignores_phone_fields(): void
    {
        $user = $this->makeUser(['email' => 'awa@example.com']);
        $this->post('/login', ['login_method' => 'email', 'email' => 'awa@example.com', 'phone' => '0199999999', 'phone_country' => 'bj', 'password' => 'secret123'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_web_login_without_identifier_is_rejected(): void
    {
        $this->from('/login')->post('/login', ['login_method' => 'phone', 'password' => 'secret123'])
            ->assertRedirect('/login')->assertSessionHasErrors(['phone']);
        $this->from('/login')->post('/login', ['login_method' => 'email', 'password' => 'secret123'])
            ->assertRedirect('/login')->assertSessionHasErrors(['email']);
    }
}
