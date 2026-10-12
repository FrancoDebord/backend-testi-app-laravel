<?php

namespace Tests\Feature;

use App\Models\AccountDelegateAction;
use App\Models\AppNotification;
use App\Models\PrayerRequest;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Administrateurs de compte : 2 personnes au plus agissent au nom d'un compte
 * (en-tête X-Act-As / compte choisi sur le site), journal des actions, actions
 * réservées au titulaire. Voir docs/fonctionnalites/administrateurs-de-compte.md
 */
class AccountManagersTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $marie;
    private User $paul;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Category::create(['slug' => 'guerison', 'name' => 'Guérison', 'is_active' => true]);
        $this->owner = $this->makeUser('pasteur', ['display_name' => 'Pasteur Jean']);
        $this->marie = $this->makeUser('marie', ['display_name' => 'Marie']);
        $this->paul  = $this->makeUser('paul', ['display_name' => 'Paul']);
    }

    private function makeUser(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => ucfirst($name), 'email' => "{$name}@example.com",
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    private function nominate(User $who): void
    {
        $this->actingAs($this->owner)->postJson('/api/v1/users/me/managers', ['user_id' => $who->id])->assertOk();
    }

    private function asAccount(User $actor, ?User $account = null): self
    {
        return $this->actingAs($actor)->withHeader('X-Act-As', ($account ?? $this->owner)->id);
    }

    public function test_un_compte_personnel_nomme_deux_administrateurs_au_plus(): void
    {
        $this->nominate($this->marie);
        $this->nominate($this->paul);
        $third = $this->makeUser('luc');

        $this->actingAs($this->owner)->postJson('/api/v1/users/me/managers', ['user_id' => $third->id])
            ->assertStatus(422);
        $this->actingAs($this->owner)->getJson('/api/v1/users/me/managers')
            ->assertOk()->assertJsonCount(2, 'data.managers')->assertJsonPath('data.max', 2);

        $this->assertTrue(AppNotification::where('recipient_id', $this->marie->id)
            ->where('type', 'account_manager_added')->exists());
    }

    public function test_l_administrateur_voit_ses_comptes_et_publie_au_nom_du_compte(): void
    {
        $this->nominate($this->marie);

        $this->actingAs($this->marie)->getJson('/api/v1/users/me/accounts')
            ->assertOk()
            ->assertJsonCount(2, 'data.accounts')
            ->assertJsonPath('data.accounts.0.id', $this->marie->id)
            ->assertJsonPath('data.accounts.0.role', 'owner')
            ->assertJsonPath('data.accounts.1.id', $this->owner->id)
            ->assertJsonPath('data.accounts.1.role', 'manager');

        // Avec l'en-tête, le sélecteur répond toujours pour la personne réelle.
        $this->asAccount($this->marie)->getJson('/api/v1/users/me/accounts')
            ->assertJsonPath('data.actingAs', $this->owner->id)
            ->assertJsonPath('data.accounts.0.id', $this->marie->id);

        $this->asAccount($this->marie)->getJson('/api/v1/auth/me')->assertStatus(403);
        $id = $this->asAccount($this->marie)->postJson('/api/v1/testimonies', [
            'title' => 'Guérison', 'type' => 'text', 'category' => 'guerison', 'body_text' => 'Dieu a agi.',
        ])->assertCreated()->assertHeader('X-Acting-As', $this->owner->id)->json('data.id');

        $this->assertSame($this->owner->id, Testimony::find($id)->user_id);

        $prayer = $this->asAccount($this->marie)->postJson('/api/v1/prayer/requests', [
            'body' => 'Priez pour notre campagne d\'évangélisation de samedi.',
        ])->assertCreated()->json('data.id');
        $this->assertSame($this->owner->id, PrayerRequest::find($prayer)->user_id);

        // Journal du titulaire.
        $this->assertSame(2, AccountDelegateAction::where('account_id', $this->owner->id)->where('actor_id', $this->marie->id)->count());
        $this->actingAs($this->owner)->getJson('/api/v1/users/me/managers/activity')
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.action', 'A publié un témoignage')
            ->assertJsonPath('data.1.actorName', 'Marie');
    }

    public function test_users_me_suit_le_compte_administre_sans_ses_identifiants(): void
    {
        $this->nominate($this->marie);

        $res = $this->asAccount($this->marie)->getJson('/api/v1/users/me')->assertOk()
            ->assertJsonPath('data.id', $this->owner->id)
            ->assertJsonPath('data.acting_as', true)
            ->assertJsonPath('data.acting_actor.id', $this->marie->id);
        $this->assertArrayNotHasKey('email', $res->json('data'));

        // Modifier le profil : le nom change, l'e-mail est ignoré.
        $this->asAccount($this->marie)->putJson('/api/v1/users/me', ['display_name' => 'Ministère Jean', 'email' => 'pirate@example.com'])
            ->assertOk();
        $this->owner->refresh();
        $this->assertSame('Ministère Jean', $this->owner->display_name);
        $this->assertSame('pasteur@example.com', $this->owner->email);
    }

    public function test_sans_etre_nomme_on_ne_peut_pas_agir_au_nom_du_compte(): void
    {
        $this->asAccount($this->paul)->postJson('/api/v1/prayer/requests', ['body' => 'Une requête au nom d\'un autre compte.'])
            ->assertStatus(403)->assertJsonPath('code', 'acting_as_forbidden');

        $this->nominate($this->paul);
        $this->actingAs($this->owner)->deleteJson("/api/v1/users/me/managers/{$this->paul->id}")->assertOk();
        $this->asAccount($this->paul)->getJson('/api/v1/users/me/accounts')->assertStatus(403);
        $this->assertTrue(AppNotification::where('recipient_id', $this->paul->id)->where('type', 'account_manager_removed')->exists());
    }

    public function test_les_actions_sensibles_restent_au_titulaire(): void
    {
        $this->nominate($this->marie);

        $this->asAccount($this->marie)->postJson('/api/v1/users/me/managers', ['user_id' => $this->paul->id])
            ->assertStatus(403)->assertJsonPath('code', 'owner_only');
        $this->asAccount($this->marie)->postJson('/api/v1/auth/change-password', ['current_password' => 'secret123', 'password' => 'nouveau123', 'password_confirmation' => 'nouveau123'])
            ->assertStatus(403);
        $this->asAccount($this->marie)->deleteJson('/api/v1/users/me')->assertStatus(403);
        $this->assertNotNull(User::find($this->owner->id));
        $this->assertSame(0, $this->owner->organizationManagers()->whereKey($this->paul->id)->count());
    }

    public function test_un_compte_de_l_equipe_ne_se_confie_pas(): void
    {
        $admin = $this->makeUser('admin', ['role' => 'administrateur']);
        $this->actingAs($admin)->postJson('/api/v1/users/me/managers', ['user_id' => $this->marie->id])->assertStatus(422);

        // Même nommée directement en base, la délégation est refusée.
        $admin->organizationManagers()->attach($this->marie->id);
        $this->asAccount($this->marie, $admin)->getJson('/api/v1/admin/stats')->assertStatus(403);
    }

    public function test_l_administrateur_peut_se_retirer(): void
    {
        $this->nominate($this->marie);
        $this->actingAs($this->marie)->deleteJson("/api/v1/users/me/managed-organizations/{$this->owner->id}")->assertOk();
        $this->assertSame(0, $this->owner->organizationManagers()->count());
    }

    public function test_site_choisir_le_compte_puis_revenir(): void
    {
        $this->nominate($this->marie);

        $this->actingAs($this->marie)->post(route('accounts.switch', $this->owner->id))->assertRedirect();
        $this->assertSame($this->owner->id, session('acting_as'));
        $this->actingAs($this->marie)->withSession(['acting_as' => $this->owner->id])
            ->get('/')->assertOk()->assertSee('Vous agissez au nom de');

        $this->actingAs($this->marie)->withSession(['acting_as' => $this->owner->id])
            ->post(route('accounts.switch', $this->marie->id))->assertRedirect();
        $this->assertNull(session('acting_as'));

        // Pas administratrice de Paul : refusé.
        $this->actingAs($this->marie)->post(route('accounts.switch', $this->paul->id))->assertForbidden();
    }
}
