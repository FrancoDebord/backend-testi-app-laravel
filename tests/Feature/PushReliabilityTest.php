<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\FcmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * Fiabilité des push : enregistrement du jeton (session de l'appareil), suppression à la déconnexion et à la
 * suppression du compte, POST /me/push-test (résultat par jeton, jeton invalide supprimé, échecs journalisés,
 * texte dans la langue du membre), bouton de test du back-office. Voir docs/fonctionnalites/notifications-push.md
 */
class PushReliabilityTest extends TestCase
{
    use RefreshDatabase;

    private const SEND_URL = 'https://fcm.googleapis.com/v1/projects/testi-test/messages:send';

    private string $dir;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/fcm-reliability');
        File::ensureDirectoryExists($this->dir);
        $this->configureFcm();
        Http::preventStrayRequests();
        $this->user = $this->makeUser('marie');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function makeUser(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => ucfirst($name), 'email' => "{$name}@example.com",
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    /** Compte de service avec une vraie clé RSA (le JWT OAuth2 est signé). */
    private function configureFcm(): void
    {
        $cnf = $this->dir . '/openssl.cnf';
        File::put($cnf, "[ req ]\ndistinguished_name = req_dn\n[ req_dn ]\n");
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $cnf];
        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, $privatePem, null, $options);
        File::put($this->dir . '/sa.json', json_encode([
            'type' => 'service_account', 'project_id' => 'testi-test', 'private_key' => $privatePem,
            'client_email' => 'push@testi-test.iam.gserviceaccount.com', 'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
        config(['services.fcm' => [
            'credentials' => $this->dir . '/sa.json', 'project_id' => null, 'enabled' => null,
            'android_channel' => 'testi_notifications', 'timeout' => 5, 'delivery' => 'queue', 'queue' => 'push',
        ]]);
        $this->app->forgetInstance(FcmClient::class);
        Cache::flush();
    }

    /** OAuth2 accepté ; FCM : jeton « mort » → UNREGISTERED, les autres acceptés. */
    private function fakeFcm(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3599]),
            'fcm.googleapis.com/*'    => function (Request $request) {
                if ($request['message']['token'] === 'jeton-mort') {
                    return Http::response(['error' => ['code' => 404, 'message' => 'Requested entity was not found.', 'status' => 'NOT_FOUND',
                        'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']]]], 404);
                }
                if ($request['message']['token'] === 'jeton-mal-forme') {
                    return Http::response(['error' => ['code' => 400, 'message' => 'The registration token is not a valid FCM registration token', 'status' => 'INVALID_ARGUMENT',
                        'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'INVALID_ARGUMENT']]]], 400);
                }

                return Http::response(['name' => 'projects/testi-test/messages/1']);
            },
        ]);
    }

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $user->createToken('mobile-app')->plainTextToken];
    }

    public function test_token_registration_links_the_app_session(): void
    {
        $headers = $this->bearer($this->user);
        $this->postJson('/api/v1/devices/token', ['token' => 'jeton-a'], $headers)->assertOk();

        $device = DeviceToken::where('token', 'jeton-a')->firstOrFail();
        $this->assertNull($device->platform); // facultative
        $this->assertNotNull($device->access_token_id);
        $this->assertSame($this->user->tokens()->value('id'), $device->access_token_id);

        // Mise à jour : plateforme envoyée, même ligne ; jeton repris par un autre compte.
        $this->postJson('/api/v1/devices/token', ['token' => 'jeton-a', 'platform' => 'ios'], $headers)->assertOk();
        $this->assertSame(1, DeviceToken::count());
        $this->assertSame('ios', $device->fresh()->platform);

        $other = $this->makeUser('paul');
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/devices/token', ['token' => 'jeton-a', 'platform' => 'android'], $this->bearer($other))->assertOk();
        $this->assertSame($other->id, $device->fresh()->user_id);
    }

    public function test_logout_removes_the_tokens_of_this_device_only(): void
    {
        $phone = $this->bearer($this->user);
        $this->postJson('/api/v1/devices/token', ['token' => 'jeton-telephone', 'platform' => 'android'], $phone)->assertOk();
        $this->app['auth']->forgetGuards();
        $tablet = $this->bearer($this->user);
        $this->postJson('/api/v1/devices/token', ['token' => 'jeton-tablette', 'platform' => 'android'], $tablet)->assertOk();
        DeviceToken::create(['user_id' => $this->user->id, 'token' => 'jeton-ancien', 'platform' => 'ios']);
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/auth/logout', ['device_token' => 'jeton-ancien'], $phone)->assertOk();

        $this->assertEqualsCanonicalizing(['jeton-tablette'], DeviceToken::pluck('token')->all());
    }

    public function test_refresh_keeps_the_device_linked_and_account_deletion_removes_tokens(): void
    {
        $headers = $this->bearer($this->user);
        $this->postJson('/api/v1/devices/token', ['token' => 'jeton-a', 'platform' => 'android'], $headers)->assertOk();

        $newToken = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => 'x'], $headers)->assertOk()->json('data.access_token');
        $this->assertSame($this->user->tokens()->value('id'), DeviceToken::where('token', 'jeton-a')->value('access_token_id'));

        $this->app['auth']->forgetGuards();
        $this->deleteJson('/api/v1/users/me', ['password' => 'secret123'], ['Authorization' => "Bearer {$newToken}"])->assertOk();
        $this->assertSame(0, DeviceToken::count());
    }

    public function test_push_test_reports_each_token_removes_invalid_ones_and_logs_failures(): void
    {
        $this->fakeFcm();
        Log::spy();
        $this->user->update(['locale' => 'en']);
        foreach (['jeton-valide' => 'android', 'jeton-mort' => 'ios', 'jeton-mal-forme' => 'android'] as $token => $platform) {
            DeviceToken::create(['user_id' => $this->user->id, 'token' => $token, 'platform' => $platform]);
        }

        $response = $this->postJson('/api/v1/me/push-test', [], $this->bearer($this->user))->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.sent', 1)
            ->assertJsonPath('message', 'Notification envoyée à 1 appareil(s) sur 3.');

        $results = collect($response->json('data.devices'))->keyBy('token'); // début du jeton seulement
        $this->assertSame('sent', $results['jeton-valide…']['result']);
        $this->assertSame('invalid_token', $results['jeton-mort…']['result']);
        $this->assertStringContainsString('HTTP 404', $results['jeton-mort…']['error']);
        $this->assertSame('invalid_token', $results['jeton-mal-fo…']['result']);
        $this->assertSame(['jeton-valide'], DeviceToken::pluck('token')->all());

        // Notification de test : type « test », canal Android, son, texte dans la langue du membre.
        Http::assertSent(fn (Request $r) => $r->url() === self::SEND_URL && $r['message']['token'] === 'jeton-valide'
            && $r['message']['notification']['title'] === 'Test notification'
            && $r['message']['data']['type'] === 'test'
            && $r['message']['data']['id'] === $this->user->id
            && $r['message']['android']['notification']['channel_id'] === 'testi_notifications'
            && $r['message']['android']['notification']['sound'] === 'default'
            && $r['message']['apns']['payload']['aps']['sound'] === 'default');

        // Chaque échec est journalisé avec le compte.
        Log::shouldHaveReceived('warning')->with('FCM : notification non délivrée.', Mockery::on(
            fn ($context) => $context['user_id'] === $this->user->id && $context['result'] === 'invalid_token'))->twice();
    }

    public function test_push_test_without_device_or_without_configuration(): void
    {
        $headers = $this->bearer($this->user);
        $this->postJson('/api/v1/me/push-test', [], $headers + ['Accept-Language' => 'en'])->assertOk()
            ->assertJsonPath('data.total', 0)->assertJsonPath('data.devices', [])
            ->assertJsonPath('message', 'No device registered: open the app while signed in to receive notifications.');

        config(['services.fcm.enabled' => false]);
        $this->app->forgetInstance(FcmClient::class);
        DeviceToken::create(['user_id' => $this->user->id, 'token' => 'jeton-a', 'platform' => 'android']);
        $this->postJson('/api/v1/me/push-test', [], $headers)->assertOk()
            ->assertJsonPath('data.disabled', true)
            ->assertJsonPath('data.devices.0.result', 'disabled')
            ->assertJsonPath('message', "L'envoi des notifications push n'est pas configuré sur le serveur.");
    }

    public function test_back_office_button_is_reserved_to_super_admins(): void
    {
        $this->fakeFcm();
        DeviceToken::create(['user_id' => $this->user->id, 'token' => 'jeton-valide', 'platform' => 'android']);
        $admin = $this->makeUser('admin', ['role' => 'administrateur']);
        $super = $this->makeUser('super', ['role' => 'administrateur']);
        $super->forceFill(['is_super_admin' => true])->save();

        $this->actingAs($admin)->get(route('admin.users.show', $this->user->id))->assertOk()->assertDontSee('Envoyer une notification de test');
        $this->actingAs($admin)->post(route('admin.users.push-test', $this->user->id))->assertForbidden();

        $this->actingAs($super)->get(route('admin.users.show', $this->user->id))->assertOk()->assertSee('Envoyer une notification de test');
        $this->actingAs($super)->from(route('admin.users.show', $this->user->id))
            ->post(route('admin.users.push-test', $this->user->id))
            ->assertRedirect(route('admin.users.show', $this->user->id))
            ->assertSessionHas('success', 'Notification envoyée à 1 appareil(s) sur 1.');
        $this->actingAs($super)->get(route('admin.users.show', $this->user->id))->assertOk()
            ->assertSee('Résultat de la notification de test')->assertSee('Reçue par FCM');
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'user.push_test', 'subject_id' => $this->user->id]);
    }
}
