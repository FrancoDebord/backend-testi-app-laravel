<?php

namespace Tests\Feature;

use App\Enums\LiveStatus;
use App\Models\LiveSession;
use App\Models\User;
use App\Services\LiveKit\LiveKitClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Direct depuis une caméra IP ou un encodeur (LiveKit Ingress). docs/fonctionnalites/lives-camera-ip.md */
class LiveCameraTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'livekit.url'        => 'wss://lk.test',
            'livekit.api_key'    => 'devkey',
            'livekit.api_secret' => 'secret-de-test-suffisamment-long',
        ]);
        $this->app->forgetInstance(LiveKitClient::class);
        Http::fake([
            'lk.test/twirp/livekit.Ingress/CreateIngress' => Http::response([
                'ingress_id' => 'IN_abc', 'url' => 'rtmps://lk.test:443/x', 'stream_key' => 'cle-secrete',
            ], 200),
            'lk.test/*' => Http::response(['participants' => []], 200),
        ]);
        $this->host = User::create([
            'display_name' => 'Pasteur', 'email' => 'pasteur@example.com', 'password' => 'secret123',
            'role' => 'moderateur', 'status' => 'active',
        ]);
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

    public function test_rtmp_camera_gets_an_ingress_with_address_and_key_for_the_host_only(): void
    {
        $this->actingAs($this->host)->postJson('/api/v1/lives', ['title' => 'Culte', 'source' => 'rtmp'])->assertCreated()
            ->assertJsonPath('data.live.source', 'rtmp')
            ->assertJsonPath('data.live.camera.url', 'rtmps://lk.test:443/x')
            ->assertJsonPath('data.live.camera.streamKey', 'cle-secrete');

        $live = LiveSession::firstOrFail();
        $this->assertSame('IN_abc', $live->ingress_id);
        $this->assertNotSame('cle-secrete', \DB::table('live_sessions')->value('ingress_stream_key')); // chiffrée en base

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/livekit.Ingress/CreateIngress')
            && $r['input_type'] === 'RTMP_INPUT'
            && $r['room_name'] === $live->room_name
            && $r['participant_identity'] === 'host-camera-' . $live->id);

        // Studio : panneau de connexion ; un autre modérateur ne voit pas la clé via l'API
        $this->actingAs($this->host)->get("/lives/{$live->id}/studio")->assertOk()
            ->assertSee('Caméra IP')->assertSee('rtmps://lk.test:443/x')->assertSee('"source":"rtmp"', false);
        $other = User::create(['display_name' => 'Autre', 'email' => 'autre@example.com', 'password' => 'secret123', 'role' => 'moderateur', 'status' => 'active']);
        $this->app['auth']->forgetGuards();
        $this->actingAs($other)->getJson("/api/v1/lives/{$live->id}")->assertOk()->assertJsonMissingPath('data.camera');
    }

    public function test_url_camera_requires_a_valid_address_and_web_form_skips_device_checks(): void
    {
        $this->actingAs($this->host)->postJson('/api/v1/lives', ['title' => 'Culte', 'source' => 'url'])
            ->assertStatus(422)->assertJsonValidationErrors('camera_url');
        $this->actingAs($this->host)->postJson('/api/v1/lives', ['title' => 'Culte', 'source' => 'url', 'camera_url' => 'ftp://cam'])
            ->assertStatus(422);

        // Site : pas de vérification de la caméra de l'appareil pour une caméra IP
        $this->actingAs($this->host)->post('/lives', [
            'title' => 'Culte', 'source' => 'url', 'camera_url' => 'rtsp://user:pass@cam.example.org:554/stream1',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $live = LiveSession::firstOrFail();
        $this->assertSame('rtsp://user:pass@cam.example.org:554/stream1', $live->camera_url);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/CreateIngress')
            && $r['input_type'] === 'URL_INPUT' && $r['url'] === 'rtsp://user:pass@cam.example.org:554/stream1');
    }

    public function test_camera_stream_does_not_go_live_alone_and_ending_removes_the_ingress(): void
    {
        $this->actingAs($this->host)->postJson('/api/v1/lives', ['title' => 'Culte', 'source' => 'rtmp'])->assertCreated();
        $live = LiveSession::firstOrFail();

        $this->webhook(['event' => 'track_published', 'room' => ['name' => $live->room_name], 'participant' => ['identity' => $live->cameraIdentity()]]);
        $this->assertSame(LiveStatus::Preparing, $live->fresh()->status);

        $this->actingAs($this->host)->postJson("/api/v1/lives/{$live->id}/go-live")->assertOk();
        $this->assertSame(LiveStatus::Live, $live->fresh()->status);

        $this->actingAs($this->host)->postJson("/api/v1/lives/{$live->id}/end")->assertOk();
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/livekit.Ingress/DeleteIngress') && $r['ingress_id'] === 'IN_abc');
    }

    public function test_ingress_failure_ends_the_live_with_a_clear_message(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake([
            'lk.test/twirp/livekit.Ingress/CreateIngress' => Http::response(['msg' => 'unsupported'], 400),
            'lk.test/*' => Http::response(['participants' => []], 200),
        ]);

        $this->actingAs($this->host)->postJson('/api/v1/lives', ['title' => 'Culte', 'source' => 'url', 'camera_url' => 'rtsp://cam.example.org/s'])
            ->assertStatus(503)->assertJsonPath('message', "Le service vidéo n'accepte pas cette adresse de flux. Vérifiez-la (HLS, HTTP, SRT, RTMP) ou utilisez le mode RTMP.");
        $this->assertSame(LiveStatus::Ended, LiveSession::firstOrFail()->status);
    }

    public function test_camera_password_is_never_returned_nor_flashed(): void
    {
        // Site : formulaire de la caméra IP (préréglages, mot de passe sans attribut name).
        $this->actingAs($this->host)->get('/lives/create')->assertOk()
            ->assertSee('Modèle de caméra')->assertSee('Hikvision', false)->assertSee('data-ipcam-composed', false)
            ->assertDontSee('name="camera_password"', false);

        // Adresse composée par le formulaire (identifiants encodés) : le diffuseur la reçoit sans mot de passe.
        $this->actingAs($this->host)->postJson('/api/v1/lives', [
            'title' => 'Culte', 'source' => 'url', 'camera_url' => 'rtsp://admin:p%40ss%3Aw0rd@cam.example.org:554/Streaming/Channels/101',
        ])->assertCreated()
            ->assertJsonPath('data.live.camera.sourceUrl', 'rtsp://admin:••••@cam.example.org:554/Streaming/Channels/101');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/CreateIngress')
            && $r['url'] === 'rtsp://admin:p%40ss%3Aw0rd@cam.example.org:554/Streaming/Channels/101');

        $this->assertSame('srt://h.org:9000?streamid=a&passphrase=••••',
            \App\Http\Resources\LiveSessionResource::maskCameraUrl('srt://h.org:9000?streamid=a&passphrase=0123456789'));
        $this->assertSame('rtsp://cam.org:554/s', \App\Http\Resources\LiveSessionResource::maskCameraUrl('rtsp://cam.org:554/s'));

        // Site : erreur de validation → l'adresse n'est pas remise en session.
        $this->actingAs($this->host)->post('/lives', [
            'source' => 'url', 'camera_url' => 'rtsp://admin:secret@cam.example.org/s', 'camera_host' => 'cam.example.org',
        ])->assertSessionHasErrors('title');
        $this->assertNull(session()->getOldInput('camera_url'));
        $this->assertSame('cam.example.org', session()->getOldInput('camera_host'));
    }
}
