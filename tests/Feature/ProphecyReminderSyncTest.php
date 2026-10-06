<?php

namespace Tests\Feature;

use App\Jobs\SyncProphecyReminderJob;
use App\Models\DeviceToken;
use App\Models\Prophecy;
use App\Models\User;
use App\Services\FcmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Message silencieux aux téléphones quand une parole change (site, autre appareil) :
 * l'application reprogramme son rappel local. Voir docs/fonctionnalites/paroles-prophetiques.md#rappels
 */
class ProphecyReminderSyncTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/fcm-prophecy');
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

    private function writeServiceAccount(): string
    {
        // Sous Windows, openssl_pkey_new exige un fichier openssl.cnf : un fichier minimal suffit.
        $cnf = $this->dir . '/openssl.cnf';
        File::put($cnf, "[ req ]\ndistinguished_name = req_dn\n[ req_dn ]\n");
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $cnf];
        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, $privatePem, null, $options);

        $path = $this->dir . '/service-account.json';
        File::put($path, json_encode([
            'type' => 'service_account', 'project_id' => 'testi-test', 'private_key' => $privatePem,
            'client_email' => 'push@testi-test.iam.gserviceaccount.com', 'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));

        return $path;
    }

    private function configureFcm(?string $credentials): void
    {
        config(['services.fcm' => [
            'credentials' => $credentials, 'project_id' => null, 'enabled' => null,
            'android_channel' => 'testi_notifications', 'timeout' => 5,
        ]]);
        $this->app->forgetInstance(FcmClient::class);
        Cache::flush();
    }

    private function device(string $platform = 'android'): void
    {
        DeviceToken::create(['user_id' => $this->user->id, 'token' => "jeton-{$platform}", 'platform' => $platform]);
    }

    private function prophecy(array $attrs = []): Prophecy
    {
        return $this->user->prophecies()->create(array_merge([
            'received_on' => '2026-09-01', 'body_text' => "Je t'ouvrirai des portes.",
        ], $attrs))->refresh();
    }

    public function test_changes_from_web_and_api_notify_the_phones(): void
    {
        $this->device();
        Queue::fake();

        $this->actingAs($this->user)->post('/carnet/paroles', [
            'received_on' => '2026-09-01', 'body_text' => 'Une parole',
            'reminder_frequency' => 'daily', 'reminder_time' => '07:00',
        ])->assertRedirect();
        $p = $this->user->prophecies()->first();
        Queue::assertPushed(SyncProphecyReminderJob::class, fn ($job) => $job->userId === $this->user->id && $job->prophecyId === $p->id);

        // Une prière ne change pas le rappel : pas de message.
        Queue::fake();
        $this->actingAs($this->user)->post("/carnet/paroles/{$p->id}/prieres", [])->assertRedirect();
        Queue::assertNotPushed(SyncProphecyReminderJob::class);

        // Accomplie, puis supprimée (site et API).
        $this->actingAs($this->user)->post("/carnet/paroles/{$p->id}/accomplie")->assertRedirect();
        Queue::assertPushed(SyncProphecyReminderJob::class, 1);
        $this->actingAs($this->user)->deleteJson("/api/v1/prophecies/{$p->id}")->assertOk();
        Queue::assertPushed(SyncProphecyReminderJob::class, 2);
    }

    public function test_nothing_is_queued_without_phone_or_fcm(): void
    {
        Queue::fake();
        $this->device('web');
        $this->actingAs($this->user)->postJson('/api/v1/prophecies', ['body_text' => 'Sans téléphone'])->assertCreated();

        $this->device();
        $this->configureFcm(null);
        $this->actingAs($this->user)->postJson('/api/v1/prophecies', ['body_text' => 'Sans FCM'])->assertCreated();

        Queue::assertNotPushed(SyncProphecyReminderJob::class);
    }

    public function test_job_sends_a_silent_message_with_the_reminder(): void
    {
        $this->device();
        $this->device('ios');
        $p = $this->prophecy(['title' => 'Portes ouvertes', 'reminder_frequency' => 'weekly', 'reminder_time' => '06:30', 'reminder_weekday' => 7]);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3599]),
            'fcm.googleapis.com/*'    => Http::response(['name' => 'projects/testi-test/messages/1']),
        ]);

        (new SyncProphecyReminderJob($this->user->id, $p->id))->handle(app(FcmClient::class));

        $sent = Http::recorded(fn (Request $r) => str_contains($r->url(), 'messages:send'));
        $this->assertCount(2, $sent);
        $message = $sent->first()[0]['message'];
        $this->assertArrayNotHasKey('notification', $message); // rien d'affiché
        $this->assertSame([
            'type' => 'prophecy_sync', 'prophecy_id' => $p->id, 'deleted' => 'false', 'status' => 'waiting',
            'display_title' => 'Portes ouvertes', 'reminder_frequency' => 'weekly', 'reminder_time' => '06:30', 'reminder_weekday' => '7',
        ], $message['data']);
        $this->assertSame(1, $message['apns']['payload']['aps']['content-available']);
    }

    public function test_payload_without_reminder_and_after_deletion(): void
    {
        $p = $this->prophecy();
        $data = SyncProphecyReminderJob::payload($p, $p->id);
        $this->assertSame("Je t'ouvrirai des portes.", $data['display_title']);
        $this->assertArrayNotHasKey('reminder_frequency', $data);

        $p->delete();
        $this->assertSame(
            ['type' => 'prophecy_sync', 'prophecy_id' => $p->id, 'deleted' => 'true'],
            SyncProphecyReminderJob::payload(Prophecy::withTrashed()->find($p->id), $p->id)
        );
    }
}
