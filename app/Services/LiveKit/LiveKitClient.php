<?php

namespace App\Services\LiveKit;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Accès au serveur LiveKit : jetons d'accès (JWT HS256), API serveur (Twirp) et webhooks.
 * Référence : https://docs.livekit.io/home/get-started/authentication/
 */
class LiveKitClient
{
    public function __construct(
        private readonly ?string $url,
        private readonly ?string $apiKey,
        private readonly ?string $apiSecret,
    ) {}

    public static function fromConfig(): self
    {
        return new self(config('livekit.url'), config('livekit.api_key'), config('livekit.api_secret'));
    }

    public function isConfigured(): bool
    {
        return filled($this->url) && filled($this->apiKey) && filled($this->apiSecret);
    }

    /** Adresse WebSocket utilisée par les navigateurs et l'application mobile. */
    public function url(): string
    {
        return (string) $this->url;
    }

    // ─── Jetons d'accès ──────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $grants  permissions « video » (canPublish, canSubscribe, hidden…)
     */
    public function accessToken(string $identity, string $name, string $room, array $grants, int $ttl, array $metadata = []): string
    {
        $this->ensureConfigured();
        $now = time();

        return $this->jwt([
            'iss'      => $this->apiKey,
            'sub'      => $identity,
            'name'     => $name,
            'nbf'      => $now - 10,
            'exp'      => $now + $ttl,
            'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : '',
            'video'    => array_merge(['room' => $room, 'roomJoin' => true], $grants),
        ]);
    }

    // ─── API serveur (RoomService) ───────────────────────────────────────────

    public function createRoom(string $room, int $emptyTimeout, int $maxParticipants = 0): array
    {
        return $this->call('CreateRoom', [
            'name'             => $room,
            'empty_timeout'    => $emptyTimeout,
            'departure_timeout' => $emptyTimeout,
            'max_participants' => $maxParticipants,
        ], $room);
    }

    public function deleteRoom(string $room): void
    {
        $this->call('DeleteRoom', ['room' => $room], $room);
    }

    /** @return array<int, array<string, mixed>> */
    public function listParticipants(string $room): array
    {
        return $this->call('ListParticipants', ['room' => $room], $room)['participants'] ?? [];
    }

    public function removeParticipant(string $room, string $identity): void
    {
        $this->call('RemoveParticipant', ['room' => $room, 'identity' => $identity], $room);
    }

    /**
     * Change les droits d'un participant déjà connecté (intervenant à l'antenne ou retour en spectateur).
     * Retirer « canPublish » dépublie aussitôt son micro et sa caméra.
     *
     * @param array<string, mixed> $permission ParticipantPermission (can_publish, can_publish_sources, hidden…)
     */
    public function updateParticipant(string $room, string $identity, array $permission): void
    {
        $this->call('UpdateParticipant', ['room' => $room, 'identity' => $identity, 'permission' => $permission], $room);
    }

    /** Diffuse un message à tous les participants (commentaires, réactions, fin du direct). */
    public function sendData(string $room, array $payload, string $topic = 'live'): void
    {
        $this->call('SendData', [
            'room'  => $room,
            'data'  => base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE)),
            'kind'  => 'RELIABLE',
            'topic' => $topic,
        ], $room);
    }

    // ─── Enregistrement (Egress) ─────────────────────────────────────────────

    /**
     * Enregistre la salle (mise en page « speaker ») dans un fichier MP4 déposé sur un stockage S3.
     * @return array<string, mixed> EgressInfo
     */
    public function startRoomRecording(string $room, string $filepath, array $s3, string $layout, string $preset): array
    {
        return $this->call('StartRoomCompositeEgress', [
            'room_name'    => $room,
            'layout'       => $layout,
            'preset'       => $preset,
            'file_outputs' => [[
                'file_type' => 'MP4',
                'filepath'  => $filepath,
                's3'        => array_filter([
                    'access_key'       => $s3['access_key'] ?? null,
                    'secret'           => $s3['secret'] ?? null,
                    'bucket'           => $s3['bucket'] ?? null,
                    'region'           => $s3['region'] ?? null,
                    'endpoint'         => $s3['endpoint'] ?? null,
                    'force_path_style' => (bool) ($s3['force_path_style'] ?? false),
                ], fn ($v) => $v !== null && $v !== ''),
            ]],
        ], $room, 'Egress', ['roomRecord' => true]);
    }

    // ─── Caméra IP / encodeur (Ingress) ──────────────────────────────────────

    /**
     * Point d'entrée d'un flux externe vers la salle : RTMP (la caméra ou l'encodeur pousse le flux,
     * avec une adresse et une clé) ou URL (LiveKit lit le flux : HLS, HTTP, SRT…). Le flux apparaît
     * dans la salle sous l'identité $identity. Voir docs/fonctionnalites/lives-camera-ip.md
     * @return array<string, mixed> IngressInfo (ingress_id, url, stream_key…)
     */
    public function createIngress(string $room, string $inputType, string $identity, string $name, ?string $url = null): array
    {
        return $this->call('CreateIngress', array_filter([
            'input_type'           => $inputType, // RTMP_INPUT ou URL_INPUT
            'name'                 => $room,
            'room_name'            => $room,
            'participant_identity' => $identity,
            'participant_name'     => $name,
            'url'                  => $url,
            'enable_transcoding'   => true,       // plusieurs qualités pour les spectateurs
        ], fn ($v) => $v !== null), $room, 'Ingress', ['ingressAdmin' => true]);
    }

    public function deleteIngress(string $ingressId): void
    {
        $this->call('DeleteIngress', ['ingress_id' => $ingressId], '', 'Ingress', ['ingressAdmin' => true]);
    }

    public function stopEgress(string $egressId): array
    {
        return $this->call('StopEgress', ['egress_id' => $egressId], '', 'Egress', ['roomRecord' => true]);
    }

    /** @return array<string, mixed>|null EgressInfo */
    public function getEgress(string $egressId): ?array
    {
        return $this->call('ListEgress', ['egress_id' => $egressId], '', 'Egress', ['roomRecord' => true])['items'][0] ?? null;
    }

    private function call(string $method, array $body, string $room, string $service = 'RoomService', ?array $grant = null): array
    {
        $this->ensureConfigured();

        $token = $this->jwt([
            'iss'   => $this->apiKey,
            'nbf'   => time() - 10,
            'exp'   => time() + 60,
            'video' => $grant ?? ['room' => $room, 'roomAdmin' => true, 'roomCreate' => true, 'roomList' => true],
        ]);

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(15)
            ->post($this->httpUrl() . "/twirp/livekit.{$service}/{$method}", $body);

        if ($response->failed()) {
            Log::warning('LiveKit ' . $method . ' a échoué', ['room' => $room, 'status' => $response->status(), 'body' => $response->body()]);
            throw new LiveKitException("LiveKit {$method} : " . ($response->json('msg') ?? $response->status()), $response->status());
        }

        return $response->json() ?? [];
    }

    private function httpUrl(): string
    {
        return rtrim(preg_replace('#^ws(s?)://#', 'http$1://', (string) $this->url), '/');
    }

    // ─── Webhooks ────────────────────────────────────────────────────────────

    /**
     * Vérifie un webhook LiveKit : l'en-tête Authorization contient un JWT signé avec
     * la clé secrète, dont la revendication « sha256 » est l'empreinte du corps reçu.
     */
    public function verifyWebhook(string $body, ?string $authorization): bool
    {
        if (!$this->isConfigured() || !$authorization) {
            return false;
        }
        $claims = $this->decodeJwt(preg_replace('/^Bearer\s+/i', '', $authorization));

        return $claims !== null
            && ($claims['iss'] ?? null) === $this->apiKey
            && hash_equals((string) ($claims['sha256'] ?? ''), base64_encode(hash('sha256', $body, true)));
    }

    // ─── JWT HS256 ───────────────────────────────────────────────────────────

    private function jwt(array $claims): string
    {
        $segments = [
            $this->b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])),
            $this->b64(json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ];
        $segments[] = $this->b64(hash_hmac('sha256', implode('.', $segments), (string) $this->apiSecret, true));

        return implode('.', $segments);
    }

    /** @return array<string, mixed>|null revendications si la signature et la validité sont correctes */
    private function decodeJwt(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $payload, $signature] = $parts;
        $expected = $this->b64(hash_hmac('sha256', "{$header}.{$payload}", (string) $this->apiSecret, true));
        if (!hash_equals($expected, $signature)) {
            return null;
        }
        $claims = json_decode($this->unb64($payload), true);
        if (!is_array($claims) || (isset($claims['exp']) && $claims['exp'] < time() - 60)) {
            return null;
        }

        return $claims;
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function unb64(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    private function ensureConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('LiveKit n\'est pas configuré (LIVEKIT_URL, LIVEKIT_API_KEY, LIVEKIT_API_SECRET).');
        }
    }
}
