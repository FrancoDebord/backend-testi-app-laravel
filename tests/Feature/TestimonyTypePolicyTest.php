<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Testimony;
use App\Models\User;
use App\Services\TestimonyTypePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Types de témoignages autorisés — docs/fonctionnalites/types-de-temoignages.md */
class TestimonyTypePolicyTest extends TestCase
{
    use RefreshDatabase;

    private const LIBRARY = '777434';

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['name' => 'Guérison', 'slug' => 'guerison', 'is_active' => true, 'display_order' => 1]);
    }

    private function user(string $name, string $role = 'utilisateur'): User
    {
        return User::create([
            'display_name' => $name, 'email' => str()->slug($name) . '@example.com', 'password' => 'secret123',
            'role' => $role, 'status' => 'active',
        ]);
    }

    private function disable(string $type): void
    {
        AppSetting::set(TestimonyTypePolicy::settingKey($type), false);
    }

    private function override(User $user, array $overrides): void
    {
        app(TestimonyTypePolicy::class)->updateOverrides($user, $overrides);
        $user->refresh();
    }

    private function apiPayload(array $extra = []): array
    {
        return array_merge(['title' => 'Guéri', 'type' => 'text', 'category' => 'guerison', 'body_text' => 'Mon histoire'], $extra);
    }

    private function webForm(array $extra = []): array
    {
        return array_merge([
            'title' => 'Guéri', 'type' => 'text', 'category' => 'guerison', 'body_text' => 'Mon histoire',
            'visibility' => 'public', 'consent_given' => '1',
        ], $extra);
    }

    // ─── Réglages et valeurs par défaut ────────────────────────────────────

    public function test_settings_are_seeded_and_returned_by_admin_settings(): void
    {
        $admin = $this->user('Admin', 'administrateur');
        $res = $this->actingAs($admin)->getJson('/api/v1/admin/settings')->assertOk();
        foreach (['text', 'audio', 'video', 'youtube'] as $type) {
            $res->assertJsonPath("data.publishing.testimony_type_{$type}_enabled", true);
        }

        // Le PUT générique les modifie.
        $this->actingAs($admin)->putJson('/api/v1/admin/settings', ['testimony_type_audio_enabled' => false])->assertOk()
            ->assertJsonPath('data.publishing.testimony_type_audio_enabled', false);
        $this->assertSame(['text', 'video'], app(TestimonyTypePolicy::class)->allowed($this->user('Marie')));
    }

    public function test_me_exposes_allowed_types(): void
    {
        $marie = $this->user('Marie');
        $this->actingAs($marie)->getJson('/api/v1/me')->assertOk()
            ->assertJsonPath('data.allowed_testimony_types', ['text', 'audio', 'video']);
        $this->actingAs($marie)->getJson('/api/v1/users/me')->assertOk()
            ->assertJsonPath('data.allowed_testimony_types', ['text', 'audio', 'video']);
        $this->actingAs($this->user('Admin', 'administrateur'))->getJson('/api/v1/auth/me')->assertOk()
            ->assertJsonPath('data.allowed_testimony_types', ['text', 'audio', 'video', 'youtube']);

        $this->disable('video');
        $this->actingAs($marie)->getJson('/api/v1/me/publishing')->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.allowed', ['text', 'audio'])
            ->assertJsonPath('data.global', ['text' => true, 'audio' => true, 'video' => false, 'youtube' => true]);

        // Profil public (autre personne) : jamais la liste.
        $this->actingAs($this->user('Paul'))->getJson("/api/v1/users/{$marie->id}")->assertJsonMissingPath('data.allowed_testimony_types');
    }

    // ─── Application des règles ────────────────────────────────────────────

    public function test_global_switch_off_refuses_api_web_and_journal(): void
    {
        $marie = $this->user('Marie');
        $this->disable('text');

        $this->actingAs($marie)->postJson('/api/v1/testimonies', $this->apiPayload())->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'La publication de témoignages texte est désactivée.')
            ->assertJsonPath('errors.type.0', 'La publication de témoignages texte est désactivée.');

        // Carnet privé : même règle.
        $this->actingAs($marie)->postJson('/api/v1/testimonies', $this->apiPayload(['visibility' => 'private']))->assertForbidden();

        // Site : formulaire classique (erreur sous le type) et appel JSON (403).
        $this->actingAs($marie)->post('/testimonies', $this->webForm())
            ->assertSessionHasErrors(['type' => 'La publication de témoignages texte est désactivée.']);
        $this->actingAs($marie)->postJson('/testimonies', $this->webForm())->assertForbidden()
            ->assertJsonPath('errors.type.0', 'La publication de témoignages texte est désactivée.');
        $this->assertSame(0, Testimony::count());

        // Les autres types restent ouverts ; en anglais, message traduit.
        $this->actingAs($marie)->postJson('/api/v1/testimonies', $this->apiPayload(['type' => 'audio', 'media_url' => 'https://exemple.test/a.mp3']))->assertCreated();
        $this->actingAs($marie)->withHeader('Accept-Language', 'en')->postJson('/api/v1/testimonies', $this->apiPayload())->assertForbidden()
            ->assertJsonPath('errors.type.0', 'Publishing text testimonies is disabled.');
    }

    public function test_upload_init_refuses_disabled_video(): void
    {
        config(['services.bunny_stream' => ['library_id' => self::LIBRARY, 'api_key' => 'cle', 'cdn_host' => 'vz-test.b-cdn.net']]);
        Http::fake();
        $this->disable('video');

        $this->actingAs($this->user('Marie'))->postJson('/api/v1/videos/upload-init', ['title' => 'Vidéo', 'category' => 'guerison'])
            ->assertForbidden()->assertJsonPath('errors.type.0', 'La publication de témoignages vidéo est désactivée.');
        $this->assertSame(0, Testimony::count());
        Http::assertNothingSent();
    }

    public function test_profile_overrides_win_over_the_global_switch(): void
    {
        $marie = $this->user('Marie');
        $paul  = $this->user('Paul');

        // Global coupé, exception « autorisé » : Marie publie, Paul non.
        $this->disable('text');
        $this->override($marie, ['text' => true]);
        $this->actingAs($marie)->postJson('/api/v1/testimonies', $this->apiPayload())->assertCreated();
        $this->actingAs($paul)->postJson('/api/v1/testimonies', $this->apiPayload())->assertForbidden();

        // Global ouvert, exception « interdit ».
        $this->override($paul, ['audio' => false]);
        $this->actingAs($paul)->postJson('/api/v1/testimonies', $this->apiPayload(['type' => 'audio', 'media_url' => 'https://exemple.test/a.mp3']))
            ->assertForbidden()->assertJsonPath('errors.type.0', 'La publication de témoignages audio est désactivée.');
        $this->assertSame(['video'], app(TestimonyTypePolicy::class)->allowed($paul));

        // Retour au réglage global (null).
        $this->override($paul, ['audio' => null]);
        $this->assertSame(['audio', 'video'], app(TestimonyTypePolicy::class)->allowed($paul->refresh()));
    }

    public function test_editing_an_existing_testimony_is_never_blocked(): void
    {
        $marie = $this->user('Marie');
        $id = $this->actingAs($marie)->postJson('/api/v1/testimonies', $this->apiPayload())->assertCreated()->json('data.id');
        $this->disable('text');

        $this->actingAs($marie)->putJson("/api/v1/testimonies/{$id}", ['title' => 'Nouveau titre'])->assertOk();
        $this->assertSame('Nouveau titre', Testimony::find($id)->title);
    }

    public function test_youtube_is_admin_only_by_default_and_can_be_granted_to_a_profile(): void
    {
        $payload = $this->apiPayload(['type' => 'video', 'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ']);
        $marie = $this->user('Marie');
        $admin = $this->user('Admin', 'administrateur');

        $this->actingAs($marie)->postJson('/api/v1/testimonies', $payload)->assertForbidden()
            ->assertJsonPath('errors.type.0', 'La publication de témoignages YouTube est désactivée.');
        $this->actingAs($admin)->postJson('/api/v1/testimonies', $payload)->assertCreated();

        // Accordé à Marie par un administrateur.
        $this->override($marie, ['youtube' => true]);
        $this->actingAs($marie)->postJson('/api/v1/testimonies', $payload)->assertCreated()
            ->assertJsonPath('data.youtubeId', 'dQw4w9WgXcQ');
        $this->actingAs($marie)->get('/publish')->assertOk()->assertSee('value="youtube"', false);

        // Global coupé : même les administrateurs ne publient plus de lien YouTube… sauf exception.
        $this->disable('youtube');
        $this->actingAs($admin)->postJson('/api/v1/testimonies', $payload)->assertForbidden();
        $this->actingAs($marie)->postJson('/api/v1/testimonies', $payload)->assertCreated();
    }

    public function test_publish_page_hides_disabled_types_and_explains_when_none_is_allowed(): void
    {
        $marie = $this->user('Marie');
        $this->disable('audio');
        $this->actingAs($marie)->get('/publish')->assertOk()
            ->assertSee('value="text"', false)->assertSee('value="video"', false)
            ->assertDontSee('name="type" value="audio"', false)->assertDontSee('value="youtube"', false);

        $this->disable('text');
        $this->disable('video');
        $this->actingAs($marie)->get('/publish')->assertOk()
            ->assertSee('Publication indisponible')->assertDontSee('id="testimony-form"', false);
    }

    // ─── Administration ────────────────────────────────────────────────────

    public function test_admin_endpoints_read_and_update_profile_overrides(): void
    {
        $admin = $this->user('Admin', 'administrateur');
        $marie = $this->user('Marie');
        $url = "/api/v1/admin/users/{$marie->id}/publishing";

        $this->actingAs($admin)->getJson($url)->assertOk()
            ->assertJsonPath('data.global', ['text' => true, 'audio' => true, 'video' => true, 'youtube' => true])
            ->assertJsonPath('data.overrides', ['text' => null, 'audio' => null, 'video' => null, 'youtube' => null])
            ->assertJsonPath('data.allowed', ['text', 'audio', 'video']);

        $this->actingAs($admin)->putJson($url, ['youtube' => true, 'audio' => false])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.overrides', ['text' => null, 'audio' => false, 'video' => null, 'youtube' => true])
            ->assertJsonPath('data.allowed', ['text', 'video', 'youtube']);

        // Clés absentes inchangées ; null = retour au réglage global.
        $this->actingAs($admin)->putJson($url, ['audio' => null])->assertOk()
            ->assertJsonPath('data.overrides', ['text' => null, 'audio' => null, 'video' => null, 'youtube' => true]);
        $this->actingAs($admin)->putJson($url, ['text' => 'oui'])->assertUnprocessable();

        $this->actingAs($marie->refresh())->getJson('/api/v1/me')->assertJsonPath('data.allowed_testimony_types', ['text', 'audio', 'video', 'youtube']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'user.testimony_types', 'subject_id' => $marie->id]);

        // Modérateurs et membres : refusé.
        $this->actingAs($this->user('Modo', 'moderateur'))->getJson($url)->assertForbidden();
        $this->actingAs($this->user('Modo 2', 'moderateur'))->putJson($url, ['text' => false])->assertForbidden();
        $this->actingAs($this->user('Paul'))->getJson($url)->assertForbidden();
        $this->actingAs($admin)->getJson('/api/v1/admin/users/' . str()->uuid() . '/publishing')->assertNotFound();
    }

    public function test_web_admin_pages_offer_switches_and_three_state_choice(): void
    {
        $admin = $this->user('Admin', 'administrateur');
        $marie = $this->user('Marie');

        $this->actingAs($admin)->get('/admin/settings')->assertOk()
            ->assertSee('Publication')->assertSee('testimony_type_youtube_enabled');

        $this->actingAs($admin)->get("/admin/users/{$marie->id}")->assertOk()
            ->assertSee('Types de témoignages autorisés')->assertSee('name="types[youtube]"', false);

        $this->actingAs($admin)->put("/admin/users/{$marie->id}/publication", ['types' => ['text' => 'deny', 'audio' => 'default', 'video' => 'default', 'youtube' => 'allow']])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(['text' => false, 'audio' => null, 'video' => null, 'youtube' => true], app(TestimonyTypePolicy::class)->overrides($marie->refresh()));

        $this->actingAs($this->user('Modo', 'moderateur'))->put("/admin/users/{$marie->id}/publication", ['types' => ['text' => 'allow']])->assertForbidden();
    }

    public function test_acting_account_rights_apply(): void
    {
        // Compte administré par un administrateur du site : ce sont les droits du compte qui s'appliquent.
        $account = $this->user('Pasteur Jean');
        $admin   = $this->user('Admin', 'administrateur');
        $this->actingAs($account)->postJson('/api/v1/users/me/managers', ['user_id' => $admin->id])->assertOk();
        $this->override($account, ['text' => false]);
        $this->app['auth']->forgetGuards();

        $asAccount = fn () => $this->actingAs($admin)->withHeader('X-Act-As', $account->id);
        $asAccount()->getJson('/api/v1/me/publishing')->assertOk()->assertJsonPath('data.allowed', ['audio', 'video']);
        $asAccount()->postJson('/api/v1/testimonies', $this->apiPayload())->assertForbidden();
        $asAccount()->postJson('/api/v1/testimonies', $this->apiPayload(['type' => 'video', 'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ']))
            ->assertForbidden();
        $this->assertSame(0, Testimony::count());
    }
}
