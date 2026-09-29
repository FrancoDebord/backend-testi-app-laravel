<?php

namespace Tests\Feature;

use App\Models\Testimony;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Téléphone de contact : saisie, format, sécurité de la connexion par téléphone, confidentialité. docs/fonctionnalites/telephone.md */
class PhoneNumberTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $extra = [])
    {
        return $this->post('/register', array_merge([
            'account_type' => 'individual', 'display_name' => 'Jean Dupont', 'email' => 'jean@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'country' => 'Bénin',
        ], $extra));
    }

    private function user(array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => 'Awa', 'email' => 'awa@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    /** Jeton Firebase de test (le serveur n'en vérifie pas encore la signature : voir la documentation). */
    private function firebaseToken(string $uid): string
    {
        $b64 = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');

        return $b64('{"alg":"none"}') . '.' . $b64(json_encode(['sub' => $uid])) . '.x';
    }

    // ─── Format ────────────────────────────────────────────────────────────

    public function test_numbers_are_stored_in_international_format(): void
    {
        // Bénin et Côte d'Ivoire : le 0 initial fait partie du numéro (10 chiffres).
        $this->assertSame('+2290197000000', PhoneNumber::toE164('bj', '01 97 00 00 00'));
        $this->assertSame('+2250707070707', PhoneNumber::toE164('ci', '07.07.07.07.07'));
        // Ailleurs, le 0 initial est le préfixe national.
        $this->assertSame('+33612345678', PhoneNumber::toE164('fr', '06 12 34 56 78'));
        $this->assertSame('+2348031234567', PhoneNumber::toE164('ng', '0803 123 4567'));
        $this->assertSame('+237677123456', PhoneNumber::toE164('cm', '677 12 34 56'));
        // Saisi au format international : accepté s'il correspond à l'indicatif.
        $this->assertSame('+33612345678', PhoneNumber::toE164('fr', '+33 6 12 34 56 78'));
        $this->assertSame('+33612345678', PhoneNumber::toE164('fr', '0033 6 12 34 56 78'));
        $this->assertNull(PhoneNumber::toE164('fr', '+229 01 97 00 00 00'));
        // Invalides.
        $this->assertNull(PhoneNumber::toE164('bj', 'abc'));
        $this->assertNull(PhoneNumber::toE164('bj', '12'));
        $this->assertNull(PhoneNumber::toE164('bj', str_repeat('9', 16)));
        $this->assertNull(PhoneNumber::toE164('zz', '97000000'));

        $this->assertSame('+229 0197000000', PhoneNumber::display('+2290197000000', 'bj'));
        $this->assertSame('0197000000', PhoneNumber::national('+2290197000000', 'bj'));
    }

    // ─── Inscription et profil ─────────────────────────────────────────────

    public function test_registration_form_shows_phone_after_country(): void
    {
        $html = $this->get('/register')->assertOk()
            ->assertSee('name="phone_country"', false)
            ->assertSee('data-follow-country="country"', false)
            ->assertSee('data-label="+229"', false)
            ->assertSee('(obligatoire pour une organisation)')
            ->getContent();

        $this->assertLessThan(strpos($html, 'name="phone"'), strpos($html, 'name="country"'));
        $this->assertStringContainsString('<option value="Bénin" data-code="bj"', $html);
    }

    public function test_person_can_register_with_or_without_phone(): void
    {
        $this->register()->assertSessionHasNoErrors();
        $this->assertNull(User::where('email', 'jean@example.com')->value('phone'));

        $this->post('/logout');
        $this->register(['email' => 'marie@example.com', 'phone_country' => 'bj', 'phone' => '01 97 00 00 00'])->assertSessionHasNoErrors();

        $user = User::where('email', 'marie@example.com')->firstOrFail();
        $this->assertSame('+2290197000000', $user->phone);
        $this->assertSame('bj', $user->phone_country);
        $this->assertNull($user->phone_verified_at, 'Un numéro saisi sur le site n\'est pas vérifié.');
    }

    public function test_organization_must_give_a_valid_unique_phone(): void
    {
        $org = [
            'account_type' => 'organization', 'organization_name' => 'Église de la Grâce', 'display_name' => '',
            'email' => 'eglise@example.com',
        ];

        $this->from('/register')->register($org)
            ->assertSessionHasErrors(['phone' => 'Le numéro de téléphone est obligatoire pour une organisation.']);

        $this->from('/register')->register($org + ['phone_country' => 'bj', 'phone' => '12'])
            ->assertSessionHasErrors(['phone' => 'Numéro de téléphone invalide pour l\'indicatif choisi.']);

        $this->from('/register')->register($org + ['phone' => '01 97 00 00 00'])
            ->assertSessionHasErrors(['phone_country']);

        $this->user(['phone' => '+2290197000000']);
        $this->from('/register')->register($org + ['phone_country' => 'bj', 'phone' => '0197000000'])
            ->assertSessionHasErrors(['phone' => 'Ce numéro est déjà associé à un autre compte.']);

        $this->assertDatabaseMissing('users', ['email' => 'eglise@example.com']);
    }

    public function test_profile_updates_an_unverified_phone_but_keeps_a_verified_one(): void
    {
        $user = $this->user(['phone' => '+2290197000000', 'phone_country' => 'bj']);

        $this->actingAs($user)->get('/profile/edit')->assertOk()
            ->assertSee('value="0197000000"', false);
        $this->actingAs($user)->put('/profile', ['display_name' => 'Awa', 'phone_country' => 'fr', 'phone' => '06 12 34 56 78'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['+33612345678', 'fr'], [$user->fresh()->phone, $user->fresh()->phone_country]);

        // Numéro vérifié par SMS : affiché, non modifiable depuis le site.
        $user->forceFill(['phone_verified_at' => now()])->save();
        $this->actingAs($user)->get('/profile/edit')->assertOk()
            ->assertSee('Vérifié par SMS')
            ->assertDontSee('name="phone"', false);
        $this->actingAs($user)->put('/profile', ['display_name' => 'Awa', 'phone_country' => 'bj', 'phone' => '0100000000'])
            ->assertSessionHasNoErrors();
        $this->assertSame('+33612345678', $user->fresh()->phone);
        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_api_profile_update_unverifies_a_changed_phone(): void
    {
        $user  = $this->user(['phone' => '+2290197000000', 'phone_verified_at' => now()]);
        $other = $this->user(['email' => 'autre@example.com', 'phone' => '+2290100000000']);

        $this->actingAs($user)->putJson('/api/v1/users/me', ['phone' => '+2290100000000'])->assertStatus(422);

        $this->actingAs($user)->putJson('/api/v1/users/me', ['phone' => '+2290197000000'])->assertOk();
        $this->assertNotNull($user->fresh()->phone_verified_at, 'Même numéro : toujours vérifié.');

        $this->actingAs($user)->putJson('/api/v1/users/me', ['phone' => '+2290155555555'])->assertOk()
            ->assertJsonPath('data.phone', '+2290155555555')
            ->assertJsonPath('data.is_phone_verified', false);
        $this->assertNull($user->fresh()->phone_verified_at);
        $this->assertSame('+2290100000000', $other->fresh()->phone);
    }

    public function test_mobile_api_accepts_dial_code_and_national_number(): void
    {
        // Inscription depuis l'application : indicatif + numéro national.
        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Awa', 'last_name' => 'K', 'display_name' => 'Awa K', 'email' => 'awa2@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'country' => "Côte d'Ivoire",
            'phone_country' => 'ci', 'phone' => '07 07 07 07 07',
        ])->assertCreated()
            ->assertJsonPath('data.user.phone', '+2250707070707')
            ->assertJsonPath('data.user.phone_country', 'ci')
            ->assertJsonPath('data.user.is_phone_verified', false);

        // Sans numéro (anciennes versions de l'application) : toujours accepté, même pour une organisation.
        $this->postJson('/api/v1/auth/register', [
            'account_type' => 'organization', 'first_name' => 'Église', 'last_name' => '', 'display_name' => 'Église',
            'email' => 'eglise2@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123',
            'organization_name' => 'Église', 'organization_type' => 'church', 'organization_city' => 'Abidjan',
        ])->assertCreated();

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'B', 'last_name' => 'B', 'display_name' => 'B', 'email' => 'b@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'phone_country' => 'ci', 'phone' => '12',
        ])->assertStatus(422)->assertJsonValidationErrors('phone');

        // Profil depuis l'application : même format, converti en international.
        $user = User::where('email', 'awa2@example.com')->firstOrFail();
        $this->actingAs($user)->putJson('/api/v1/users/me', ['phone_country' => 'bj', 'phone' => '01 97 00 00 00'])->assertOk()
            ->assertJsonPath('data.phone', '+2290197000000')
            ->assertJsonPath('data.phone_country', 'bj');

        // Numéro vérifié par SMS : non modifiable par ce moyen.
        $user->forceFill(['phone_verified_at' => now()])->save();
        $this->actingAs($user)->putJson('/api/v1/users/me', ['phone_country' => 'fr', 'phone' => '06 12 34 56 78'])->assertOk()
            ->assertJsonPath('data.phone', '+2290197000000')
            ->assertJsonPath('data.is_phone_verified', true);
    }

    // ─── Connexion par téléphone ───────────────────────────────────────────

    public function test_an_unverified_phone_never_opens_the_account_that_declared_it(): void
    {
        // Quelqu'un déclare sur le site le numéro d'une autre personne.
        $claimer = $this->user(['email' => 'claim@example.com', 'phone' => '+2290197000000']);

        // La vraie titulaire se connecte par SMS avec ce numéro : un nouveau compte est créé pour elle.
        $response = $this->postJson('/api/v1/auth/phone', [
            'firebase_token' => $this->firebaseToken('firebase-uid-awa'), 'phone' => '+2290197000000', 'first_name' => 'Awa',
        ])->assertOk();

        $this->assertNotSame($claimer->id, $response->json('data.user.id'));
        $this->assertNull($claimer->fresh()->phone, 'Le numéro revient à la personne qui l\'a confirmé par SMS.');
        $owner = User::where('firebase_uid', 'firebase-uid-awa')->firstOrFail();
        $this->assertSame('+2290197000000', $owner->phone);
        $this->assertTrue($owner->hasVerifiedPhone());

        // Sans profil fourni : « nouveau profil requis », jamais le compte qui avait déclaré le numéro.
        $this->user(['email' => 'claim2@example.com', 'phone' => '+2290122222222']);
        $this->postJson('/api/v1/auth/phone', ['firebase_token' => $this->firebaseToken('uid-2'), 'phone' => '+2290122222222'])
            ->assertOk()->assertJsonPath('data.is_new_user', true)->assertJsonMissingPath('data.access_token');
    }

    public function test_a_verified_phone_still_signs_in_to_its_account(): void
    {
        $user = $this->user(['phone' => '+2290197000000', 'phone_verified_at' => now(), 'firebase_uid' => 'ancien-uid']);

        $this->postJson('/api/v1/auth/phone', ['firebase_token' => $this->firebaseToken('nouvel-uid'), 'phone' => '+2290197000000'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.phone', '+2290197000000');
    }

    // ─── Confidentialité ───────────────────────────────────────────────────

    public function test_contact_details_are_private_in_the_api(): void
    {
        $author = $this->user(['phone' => '+2290197000000', 'phone_country' => 'bj']);
        $t = Testimony::create([
            'user_id' => $author->id, 'title' => 'Témoignage', 'type' => 'text', 'category_slug' => 'autre', 'body_text' => 'Texte',
            'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ]);

        $this->getJson("/api/v1/testimonies/{$t->id}")->assertOk()
            ->assertJsonPath('data.user.email', null)
            ->assertJsonPath('data.user.phone', null);

        $admin = $this->user(['email' => 'admin@example.com', 'role' => 'administrateur']);
        $this->actingAs($admin)->getJson("/api/v1/testimonies/{$t->id}")->assertOk()
            ->assertJsonPath('data.user.phone', '+2290197000000');

        $this->actingAs($admin)->get("/admin/users/{$author->id}")->assertOk()
            ->assertSee('+229 0197000000')
            ->assertSee('Non vérifié');
    }
}
