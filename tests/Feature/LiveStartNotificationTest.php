<?php

namespace Tests\Feature;

use App\Enums\LiveStatus;
use App\Jobs\SendPushNotificationJob;
use App\Models\AppNotification;
use App\Models\DeviceToken;
use App\Models\LiveSession;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\FcmClient;
use App\Services\LiveKit\LiveKitClient;
use App\Services\LiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Notification « live_started » aux abonnés du diffuseur (docs/fonctionnalites/lives.md,
 * docs/fonctionnalites/notifications-push.md).
 */
class LiveStartNotificationTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;
    private User $host;
    private User $follower;
    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'livekit.url'        => 'wss://lk.test',
            'livekit.api_key'    => 'devkey',
            'livekit.api_secret' => 'secret-de-test-suffisamment-long',
        ]);
        $this->app->forgetInstance(LiveKitClient::class);
        Http::fake(['lk.test/*' => Http::response(['participants' => []], 200)]);

        // FCM « configuré » (les envois sont interceptés par Queue::fake) : clé factice suffisante.
        $this->dir = storage_path('framework/testing/fcm-live');
        File::ensureDirectoryExists($this->dir);
        File::put($this->dir . '/sa.json', json_encode([
            'project_id' => 'testi-test', 'client_email' => 'push@testi-test.iam.gserviceaccount.com', 'private_key' => 'factice',
        ]));
        config(['services.fcm' => [
            'credentials' => $this->dir . '/sa.json', 'project_id' => null, 'enabled' => null,
            'android_channel' => 'testi_notifications', 'timeout' => 5,
        ]]);
        $this->app->forgetInstance(FcmClient::class);
        Queue::fake();

        $this->host     = $this->makeUser('moderateur', 'Pasteur Jean');
        $this->follower = $this->makeUser('utilisateur', 'Marie');
        $this->stranger = $this->makeUser('utilisateur', 'Paul');
        DB::table('follows')->insert(['follower_id' => $this->follower->id, 'following_id' => $this->host->id, 'created_at' => now()]);

        foreach ([$this->host, $this->follower, $this->stranger] as $u) {
            DeviceToken::create(['user_id' => $u->id, 'token' => 'jeton-' . $u->id, 'platform' => 'android']);
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function makeUser(string $role, string $name): User
    {
        return User::create([
            'display_name' => $name, 'email' => str_replace(' ', '', strtolower($name)) . '@example.com',
            'password' => 'secret123', 'role' => $role, 'status' => 'active',
        ]);
    }

    private function startLive(): LiveSession
    {
        $this->actingAs($this->host)->postJson('/api/v1/lives', ['title' => 'Soirée de louange'])->assertCreated();

        return LiveSession::where('host_id', $this->host->id)->latest()->firstOrFail();
    }

    private function webhook(array $event)
    {
        $body = json_encode($event);
        $b64 = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $head = $b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $claims = $b64(json_encode(['iss' => 'devkey', 'exp' => time() + 60, 'sha256' => base64_encode(hash('sha256', $body, true))]));
        $jwt = "{$head}.{$claims}." . $b64(hash_hmac('sha256', "{$head}.{$claims}", config('livekit.api_secret'), true));

        return $this->call('POST', '/api/v1/livekit/webhook', [], [], [], [
            'HTTP_AUTHORIZATION' => $jwt, 'CONTENT_TYPE' => 'application/webhook+json',
        ], $body);
    }

    public function test_preparing_a_live_notifies_nobody(): void
    {
        $this->startLive();

        $this->assertSame(0, AppNotification::where('type', 'live_started')->count());
        Queue::assertNotPushed(SendPushNotificationJob::class);
    }

    public function test_followers_are_notified_once_when_the_live_starts(): void
    {
        $live = $this->startLive();
        $stale = LiveSession::find($live->id); // encore « en préparation » en mémoire

        $this->actingAs($this->host)->postJson("/api/v1/lives/{$live->id}/go-live")->assertOk();
        // Rejeu : nouvel appel, webhook LiveKit, et appel concurrent avec un modèle périmé.
        $this->actingAs($this->host)->postJson("/api/v1/lives/{$live->id}/go-live")->assertOk();
        $this->webhook(['event' => 'track_published', 'room' => ['name' => $live->room_name], 'participant' => ['identity' => "host-{$this->host->id}"]])
            ->assertNoContent();
        app(LiveService::class)->goLive($stale, $this->host);

        $this->assertSame(LiveStatus::Live, $live->fresh()->status);

        $notifications = AppNotification::where('type', 'live_started')->get();
        $this->assertCount(1, $notifications);
        $n = $notifications->first();
        $this->assertSame($this->follower->id, $n->recipient_id);
        $this->assertSame($this->host->id, $n->actor_id);
        $this->assertSame('Pasteur Jean est en direct : Soirée de louange', $n->message);
        $this->assertSame($live->id, $n->payload['live_id']);

        Queue::assertPushed(SendPushNotificationJob::class, 1);
        Queue::assertPushed(SendPushNotificationJob::class, fn ($job) => $job->userId === $this->follower->id
            && $job->title === 'En direct'
            && $job->body === 'Pasteur Jean est en direct : Soirée de louange'
            && $job->data['type'] === 'live_started'
            && $job->data['live_id'] === $live->id
            && $job->data['notification_id'] === $n->id);

        // L'abonné voit la notification (avec l'identifiant du direct) dans l'application.
        $this->actingAs($this->follower)->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonFragment(['type' => 'live_started', 'liveId' => $live->id]);
    }

    public function test_webhook_start_also_notifies_followers(): void
    {
        $live = $this->startLive();

        $this->webhook(['event' => 'track_published', 'room' => ['name' => $live->room_name], 'participant' => ['identity' => "host-{$this->host->id}"]])
            ->assertNoContent();
        $this->webhook(['event' => 'track_published', 'room' => ['name' => $live->room_name], 'participant' => ['identity' => "host-{$this->host->id}"]])
            ->assertNoContent();

        $this->assertSame(1, AppNotification::where('type', 'live_started')->count());
        $this->assertDatabaseMissing('app_notifications', ['recipient_id' => $this->stranger->id]);
        $this->assertDatabaseMissing('app_notifications', ['recipient_id' => $this->host->id]);
        Queue::assertPushed(SendPushNotificationJob::class, 1);
    }

    public function test_push_respects_the_followed_accounts_preference(): void
    {
        UserSetting::create(['user_id' => $this->follower->id, 'push_new_followed' => false]);
        $live = $this->startLive();

        $this->actingAs($this->host)->postJson("/api/v1/lives/{$live->id}/go-live")->assertOk();

        // La notification reste visible dans l'application, seul le push est coupé.
        $this->assertDatabaseHas('app_notifications', ['recipient_id' => $this->follower->id, 'type' => 'live_started']);
        Queue::assertNotPushed(SendPushNotificationJob::class);
    }
}
