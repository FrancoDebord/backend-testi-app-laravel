<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** POST /api/v1/auth/google { id_token } : même réponse que la connexion e-mail / mot de passe. */
class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private const WEB_CLIENT = '566166419345-b78faa624rdlcqeigmublhhbseqvhofr.apps.googleusercontent.com';

    /** Réponse courante de tokeninfo (Http::fake garde la première règle enregistrée : une seule, qui lit ceci). */
    private array $claims = [];
    private int $status = 200;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.web_client_id' => self::WEB_CLIENT, 'services.google.client_id' => '']);
        Http::fake(['oauth2.googleapis.com/tokeninfo*' => fn () => Http::response($this->claims, $this->status)]);
    }

    private function makeUser(array $attrs): User
    {
        return User::create($attrs + [
            'display_name' => 'Awa D.', 'role' => 'utilisateur', 'status' => 'active', 'is_active' => true,
        ]);
    }

    private function fakeGoogle(array $claims = [], int $status = 200): void
    {
        $this->status = $status;
        $this->claims = $claims + [
            'iss' => 'https://accounts.google.com', 'aud' => self::WEB_CLIENT, 'sub' => 'g-123',
            'email' => 'awa@example.com', 'email_verified' => 'true',
            'name' => 'Awa', 'picture' => 'https://example.com/a.jpg',
        ];
    }

    public function test_google_sign_in_creates_account_and_returns_same_format_as_login(): void
    {
        $this->fakeGoogle();

        $res = $this->postJson('/api/v1/auth/google', ['id_token' => 'jeton'])->assertOk()
            ->assertJsonStructure(['success', 'message', 'data' => ['user' => ['id', 'email'], 'access_token', 'token_type']])
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.is_new_user', true)
            ->assertJsonPath('data.user.email', 'awa@example.com');

        $user = User::where('email', 'awa@example.com')->sole();
        $this->assertSame('g-123', $user->google_id);
        $this->assertSame('Awa', $user->display_name);
        $this->assertSame('https://example.com/a.jpg', $user->avatar_url);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);
        $this->assertSame('mobile-google', $user->tokens()->sole()->name);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'id_token=jeton'));

        // Le jeton Sanctum ouvre bien la session.
        $this->withToken($res->json('data.access_token'))->getJson('/api/v1/auth/me')
            ->assertOk()->assertJsonPath('data.email', 'awa@example.com');

        // Seconde connexion : même compte, plus « nouveau ».
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/google', ['id_token' => 'jeton'])->assertOk()->assertJsonPath('data.is_new_user', false);
        $this->assertSame(1, User::where('email', 'awa@example.com')->count());
    }

    public function test_existing_email_account_is_linked_and_keeps_its_password(): void
    {
        $user = $this->makeUser([
            'email' => 'awa@example.com', 'password' => 'secret-123', 'avatar_url' => null, 'email_verified_at' => null,
        ]);
        $this->fakeGoogle();

        $this->postJson('/api/v1/auth/google', ['id_token' => 'jeton'])->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.is_new_user', false);

        $user->refresh();
        $this->assertSame('g-123', $user->google_id);
        $this->assertSame('https://example.com/a.jpg', $user->avatar_url);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(1, User::count());

        // La connexion e-mail / mot de passe fonctionne toujours.
        $this->postJson('/api/v1/auth/login', ['email' => 'awa@example.com', 'password' => 'secret-123'])->assertOk();
    }

    public function test_linking_keeps_an_existing_avatar(): void
    {
        $user = $this->makeUser(['email' => 'awa@example.com', 'avatar_url' => 'https://example.com/mien.jpg']);
        $this->fakeGoogle();

        $this->postJson('/api/v1/auth/google', ['id_token' => 'jeton'])->assertOk();

        $this->assertSame('https://example.com/mien.jpg', $user->refresh()->avatar_url);
    }

    public function test_token_for_another_audience_is_refused(): void
    {
        $this->fakeGoogle(['aud' => 'client-d-une-autre-app.apps.googleusercontent.com']);

        $this->postJson('/api/v1/auth/google', ['id_token' => 'autre'])->assertStatus(401)
            ->assertJsonPath('success', false);
        $this->assertSame(0, User::count());
    }

    public function test_invalid_issuer_unverified_email_and_rejected_tokens_are_refused(): void
    {
        $this->postJson('/api/v1/auth/google', [])->assertStatus(422);

        $this->fakeGoogle(['iss' => 'https://evil.example.com']);
        $this->postJson('/api/v1/auth/google', ['id_token' => 'a'])->assertStatus(401);

        $this->fakeGoogle(['email_verified' => 'false']);
        $this->postJson('/api/v1/auth/google', ['id_token' => 'b'])->assertStatus(401);

        $this->fakeGoogle(['error' => 'invalid_token'], 400);
        $this->postJson('/api/v1/auth/google', ['id_token' => 'c'])->assertStatus(401);

        $this->assertSame(0, User::count());
    }

    // ─── Site web : bouton « Se connecter avec Google » de /login ───────────────

    public function test_login_page_shows_google_button_only_when_configured(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee(route('login.google'), false)
            ->assertSee(self::WEB_CLIENT, false);

        config(['services.google.web_client_id' => null]);
        $this->get('/login')->assertOk()->assertDontSee(route('login.google'), false);
    }

    public function test_register_page_shows_google_button_only_when_configured(): void
    {
        // Même bouton, même parcours que /login : compte créé s'il est nouveau, connexion sinon.
        $this->get('/register')->assertOk()
            ->assertSee('id="google-signin-button"', false)
            ->assertSee(route('login.google'), false)
            ->assertSee(self::WEB_CLIENT, false)
            ->assertSee('signup_with', false);

        config(['services.google.web_client_id' => null]);
        $this->get('/register')->assertOk()->assertDontSee(route('login.google'), false);
    }

    public function test_web_google_sign_in_from_register_page_creates_the_account(): void
    {
        $this->fakeGoogle();

        $this->from('/register')->post('/login/google', ['credential' => 'jeton'])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs(User::where('google_id', 'g-123')->sole());
    }

    public function test_web_google_sign_in_opens_a_session(): void
    {
        $this->fakeGoogle();

        $this->post('/login/google', ['credential' => 'jeton'])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs(User::where('google_id', 'g-123')->sole());
    }

    public function test_web_google_sign_in_links_an_existing_account(): void
    {
        $user = $this->makeUser(['email' => 'awa@example.com', 'password' => 'secret-123']);
        $this->fakeGoogle();

        $this->post('/login/google', ['credential' => 'jeton'])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('g-123', $user->refresh()->google_id);
    }

    public function test_web_google_sign_in_refusal_returns_to_login_with_message(): void
    {
        $this->fakeGoogle(['aud' => 'client-d-une-autre-app.apps.googleusercontent.com']);

        $this->from('/login')->post('/login/google', ['credential' => 'jeton'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Jeton Google non autorisé pour cette application.']);
        $this->assertGuest();

        $this->makeUser(['email' => 'awa@example.com', 'status' => 'banned']);
        $this->fakeGoogle();
        $this->post('/login/google', ['credential' => 'jeton'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_legacy_social_route_accepts_the_token_field_sent_by_the_app(): void
    {
        $this->fakeGoogle();

        $this->postJson('/api/v1/auth/social', ['provider' => 'google', 'token' => 'jeton'])->assertOk()
            ->assertJsonPath('data.user.email', 'awa@example.com');
    }
}
