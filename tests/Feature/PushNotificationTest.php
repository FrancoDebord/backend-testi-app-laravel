<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotificationJob;
use App\Models\AppNotification;
use App\Models\DeviceToken;
use App\Models\Testimony;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\FcmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Notifications push FCM (API HTTP v1). Voir docs/fonctionnalites/notifications-push.md
 */
class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const OAUTH_URL = 'https://oauth2.googleapis.com/token';
    private const SEND_URL = 'https://fcm.googleapis.com/v1/projects/testi-test/messages:send';

    private string $dir;
    private string $publicKey;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('framework/testing/fcm');
        File::ensureDirectoryExists($this->dir);
        $this->configureFcm($this->writeServiceAccount());
        Http::preventStrayRequests();

        $this->user = User::create([
            'display_name' => 'Marie', 'email' => 'marie@example.com',
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /** Compte de service factice avec une clé RSA générée pour le test. */
    private function writeServiceAccount(): string
    {
        // Sous Windows, openssl_pkey_new exige un fichier openssl.cnf : un fichier minimal suffit.
        $cnf = $this->dir . '/openssl.cnf';
        File::put($cnf, "[ req ]\ndistinguished_name = req_dn\n[ req_dn ]\n");
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $cnf];

        $key = openssl_pkey_new($options);
        $this->assertNotFalse($key, 'Génération de la clé RSA impossible : ' . openssl_error_string());
        openssl_pkey_export($key, $privatePem, null, $options);
        $this->publicKey = openssl_pkey_get_details($key)['key'];

        $path = $this->dir . '/service-account.json';
        File::put($path, json_encode([
            'type'         => 'service_account',
            'project_id'   => 'testi-test',
            'private_key'  => $privatePem,
            'client_email' => 'push@testi-test.iam.gserviceaccount.com',
            'token_uri'    => self::OAUTH_URL,
        ]));

        return $path;
    }

    private function configureFcm(?string $credentials, array $extra = []): void
    {
        config(['services.fcm' => array_merge([
            'credentials'     => $credentials,
            'project_id'      => null,
            'enabled'         => null,
            'android_channel' => 'testi_notifications',
            'timeout'         => 5,
        ], $extra)]);
        $this->app->forgetInstance(FcmClient::class);
        Cache::flush();
    }

    private function fakeFcm($sendResponse = null): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.jeton-test', 'expires_in' => 3599, 'token_type' => 'Bearer']),
            'fcm.googleapis.com/*'    => $sendResponse ?? Http::response(['name' => 'projects/testi-test/messages/1']),
        ]);
    }

    private function device(string $token = 'jeton-appareil-1', ?User $user = null): DeviceToken
    {
        return DeviceToken::create(['user_id' => ($user ?? $this->user)->id, 'token' => $token, 'platform' => 'android']);
    }

    private function notify(string $type, array $attrs = []): AppNotification
    {
        return AppNotification::create(array_merge([
            'recipient_id' => $this->user->id,
            'actor_id'     => null,
            'actor_name'   => 'Paul',
            'type'         => $type,
            'message'      => 'Paul a commenté votre témoignage',
            'created_at'   => now(),
        ], $attrs));
    }

    public function test_notification_sends_fcm_v1_message_with_app_payload(): void
    {
        $this->fakeFcm();
        $this->device();
        $testimonyId = '11111111-2222-3333-4444-555555555555';

        $notification = $this->notify('comment', ['testimony_id' => $testimonyId, 'testimony_title' => 'Guérison']);

        // Jeton OAuth2 : JWT RS256 signé avec la clé du compte de service.
        Http::assertSent(function (Request $request) {
            if ($request->url() !== self::OAUTH_URL) {
                return false;
            }
            $this->assertSame('urn:ietf:params:oauth:grant-type:jwt-bearer', $request['grant_type']);
            [$header, $claimsPart, $signature] = explode('.', $request['assertion']);
            $decode = fn ($s) => base64_decode(strtr($s, '-_', '+/'));
            $this->assertSame(['alg' => 'RS256', 'typ' => 'JWT'], json_decode($decode($header), true));
            $claims = json_decode($decode($claimsPart), true);
            $this->assertSame('push@testi-test.iam.gserviceaccount.com', $claims['iss']);
            $this->assertSame(FcmClient::SCOPE, $claims['scope']);
            $this->assertSame(self::OAUTH_URL, $claims['aud']);
            $this->assertSame(3600, $claims['exp'] - $claims['iat']);
            $this->assertSame(1, openssl_verify("{$header}.{$claimsPart}", $decode($signature), $this->publicKey, OPENSSL_ALGO_SHA256));
            return true;
        });

        Http::assertSent(function (Request $request) use ($testimonyId, $notification) {
            if ($request->url() !== self::SEND_URL) {
                return false;
            }
            $this->assertSame('Bearer ya29.jeton-test', $request->header('Authorization')[0]);
            $message = $request['message'];
            $this->assertSame('jeton-appareil-1', $message['token']);
            $this->assertSame(['title' => 'Nouveau commentaire', 'body' => 'Paul a commenté votre témoignage'], $message['notification']);
            $this->assertSame([
                'type'            => 'comment',
                'testimony_id'    => $testimonyId,
                'notification_id' => $notification->id,
            ], $message['data']);
            $this->assertSame('high', $message['android']['priority']);
            $this->assertSame('testi_notifications', $message['android']['notification']['channel_id']);
            $this->assertSame('default', $message['apns']['payload']['aps']['sound']);
            return true;
        });
    }

    public function test_access_token_is_cached_between_sends(): void
    {
        $this->fakeFcm();
        $this->device('a');
        $this->device('b');

        $this->notify('follow');
        $this->notify('like');

        $oauth = Http::recorded(fn (Request $r) => $r->url() === self::OAUTH_URL);
        $sends = Http::recorded(fn (Request $r) => $r->url() === self::SEND_URL);
        $this->assertCount(1, $oauth);
        $this->assertCount(4, $sends);
    }

    public function test_unregistered_token_is_deleted(): void
    {
        $this->fakeFcm(Http::response(['error' => [
            'code' => 404, 'message' => 'Requested entity was not found.', 'status' => 'NOT_FOUND',
            'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']],
        ]], 404));
        $this->device('perime');
        $other = $this->device('autre-utilisateur', User::create([
            'display_name' => 'Jean', 'email' => 'jean@example.com', 'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ]));

        $this->notify('follow');

        $this->assertDatabaseMissing('device_tokens', ['token' => 'perime']);
        $this->assertDatabaseHas('device_tokens', ['id' => $other->id]);
    }

    public function test_invalid_token_argument_deletes_token_but_other_errors_do_not(): void
    {
        $fcm = FcmClient::fromConfig();

        $this->fakeFcm(Http::sequence()
            ->push(['error' => [
                'code' => 400, 'message' => 'The registration token is not a valid FCM registration token', 'status' => 'INVALID_ARGUMENT',
                'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'INVALID_ARGUMENT']],
            ]], 400)
            // Erreur sur un autre champ du message : le jeton est conservé.
            ->push(['error' => [
                'code' => 400, 'message' => 'Invalid value at message.data', 'status' => 'INVALID_ARGUMENT',
                'details' => [['@type' => 'type.googleapis.com/google.rpc.BadRequest', 'fieldViolations' => [['field' => 'message.data[0].value']]]],
            ]], 400));

        $this->device('mal-forme');
        $this->assertSame(FcmClient::INVALID_TOKEN, $fcm->send('mal-forme', 'T', 'B'));
        $this->assertDatabaseMissing('device_tokens', ['token' => 'mal-forme']);

        $this->device('valide');
        $this->assertSame(FcmClient::FAILED, $fcm->send('valide', 'T', 'B'));
        $this->assertDatabaseHas('device_tokens', ['token' => 'valide']);
    }

    public function test_server_error_is_retried_by_the_job(): void
    {
        $this->fakeFcm(Http::response(['error' => ['code' => 503, 'status' => 'UNAVAILABLE']], 503));
        $this->device();

        $this->expectException(\RuntimeException::class);
        (new SendPushNotificationJob($this->user->id, 'T', 'B'))->handle(app(FcmClient::class));
    }

    public function test_nothing_is_sent_when_fcm_is_not_configured(): void
    {
        Http::fake();
        $this->device();

        foreach ([
            [null, []],                                                        // pas de fichier
            [$this->dir . '/absent.json', []],                                 // fichier introuvable
            [$this->dir . '/service-account.json', ['enabled' => false]],      // FCM_ENABLED=false
        ] as [$path, $extra]) {
            $this->configureFcm($path, $extra);
            $fcm = app(FcmClient::class);
            $this->assertFalse($fcm->isConfigured());
            $this->assertSame(FcmClient::DISABLED, $fcm->send('jeton-appareil-1', 'T', 'B'));
            $this->notify('follow');
            (new SendPushNotificationJob($this->user->id, 'T', 'B'))->handle($fcm);
        }

        Http::assertNothingSent();
        $this->assertSame(3, AppNotification::count());
    }

    public function test_observer_dispatches_job_with_french_title_and_navigation_data(): void
    {
        Queue::fake();
        $this->device();

        $this->notify('testimony_approved', [
            'testimony_id' => 'abc', 'testimony_title' => 'Ma guérison',
            'message' => 'Votre témoignage "Ma guérison" a été approuvé',
        ]);

        Queue::assertPushed(SendPushNotificationJob::class, function (SendPushNotificationJob $job) {
            return $job->userId === $this->user->id
                && $job->title === 'Témoignage approuvé'
                && $job->body === 'Votre témoignage "Ma guérison" a été approuvé'
                && $job->data['type'] === 'testimony_approved'
                && $job->data['testimony_id'] === 'abc';
        });
    }

    public function test_no_job_without_device_or_configuration(): void
    {
        Queue::fake();
        $this->notify('follow'); // aucun appareil
        Queue::assertNothingPushed();

        $this->device();
        $this->configureFcm(null);
        $this->notify('follow'); // FCM non configuré
        Queue::assertNothingPushed();
    }

    public function test_user_push_preferences_are_respected(): void
    {
        Queue::fake();
        $this->device();
        UserSetting::create([
            'user_id' => $this->user->id, 'push_comments' => false, 'push_likes' => true,
            'push_prayers' => true, 'push_approval' => false, 'push_new_followed' => false,
        ]);

        $this->notify('comment');
        $this->notify('reply');
        $this->notify('testimony_rejected');
        $this->notify('new_followed_testimony');
        Queue::assertNothingPushed();

        $this->notify('like');
        $this->notify('follow');
        $this->notify('organization_verified');
        Queue::assertPushed(SendPushNotificationJob::class, 3);
        $this->assertSame(7, AppNotification::count());
    }

    public function test_approval_notifies_followers_by_push(): void
    {
        Queue::fake();
        $moderator = User::create([
            'display_name' => 'Modo', 'email' => 'modo@example.com', 'password' => 'secret123', 'role' => 'moderateur', 'status' => 'active',
        ]);
        $follower = User::create([
            'display_name' => 'Abonné', 'email' => 'abonne@example.com', 'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ]);
        DB::table('follows')->insert(['follower_id' => $follower->id, 'following_id' => $this->user->id, 'created_at' => now()]);
        $this->device('auteur');
        $this->device('abonne', $follower);
        $testimony = Testimony::create([
            'user_id' => $this->user->id, 'title' => 'Délivrance', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Texte', 'visibility' => 'public', 'status' => 'pending',
        ]);

        Sanctum::actingAs($moderator);
        $this->postJson("/api/v1/moderation/{$testimony->id}/approve")->assertOk();

        $this->assertDatabaseHas('app_notifications', ['recipient_id' => $follower->id, 'type' => 'new_followed_testimony']);
        Queue::assertPushed(SendPushNotificationJob::class, fn ($job) => $job->userId === $this->user->id && $job->data['type'] === 'testimony_approved');
        Queue::assertPushed(SendPushNotificationJob::class, fn ($job) => $job->userId === $follower->id
            && $job->title === 'Nouveau témoignage'
            && $job->data['testimony_id'] === $testimony->id
            && isset($job->data['notification_id']));
    }

    public function test_push_test_command(): void
    {
        $this->fakeFcm();
        $this->device();

        $this->artisan('push:test', ['user' => 'marie@example.com'])
            ->expectsOutputToContain('1 / 1 appareil(s) servi(s).')
            ->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r->url() === self::SEND_URL
            && $r['message']['notification']['title'] === 'Test TestiApp'
            && $r['message']['data'] === ['type' => 'test']);

        $this->artisan('push:test', ['user' => 'inconnu@example.com'])->assertFailed();
    }
}
