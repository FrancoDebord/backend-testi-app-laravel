<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Envoi des notifications push par Firebase Cloud Messaging, API HTTP v1
 * (docs/fonctionnalites/notifications-push.md).
 *
 * Aucune dépendance : le jeton OAuth2 est obtenu en signant un JWT (RS256) avec la clé
 * du compte de service, puis gardé en cache jusqu'à 5 minutes avant son expiration.
 * Sans configuration, chaque envoi est ignoré (journal de niveau debug).
 */
class FcmClient
{
    public const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    public const DEFAULT_TOKEN_URI = 'https://oauth2.googleapis.com/token';
    public const SEND_URL = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';

    /** Résultats de send(). */
    public const SENT = 'sent';
    public const INVALID_TOKEN = 'invalid_token'; // jeton supprimé de device_tokens
    public const RETRY = 'retry';                 // erreur temporaire (429, 5xx)
    public const FAILED = 'failed';               // erreur définitive (message refusé…)
    public const DISABLED = 'disabled';           // FCM non configuré

    private ?array $credentials = null;
    private bool $credentialsLoaded = false;

    public function __construct(
        private ?string $credentialsPath,
        private ?string $projectId = null,
        private ?bool $enabled = null,
        private ?string $androidChannel = null,
        private int $timeout = 10,
    ) {}

    public static function fromConfig(): self
    {
        $config = config('services.fcm', []);
        $enabled = $config['enabled'] ?? null;
        if (!is_bool($enabled)) {
            $enabled = ($enabled === null || $enabled === '')
                ? null
                : filter_var($enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return new self(
            $config['credentials'] ?? null ?: null,
            $config['project_id'] ?? null ?: null,
            $enabled,
            $config['android_channel'] ?? null ?: null,
            (int) ($config['timeout'] ?? 10) ?: 10,
        );
    }

    /** Activé (FCM_ENABLED différent de false) et compte de service lisible. */
    public function isConfigured(): bool
    {
        return $this->enabled !== false
            && $this->credentials() !== null
            && $this->projectId() !== null;
    }

    public function projectId(): ?string
    {
        return $this->projectId ?: ($this->credentials()['project_id'] ?? null);
    }

    /**
     * Envoie une notification à un appareil. Renvoie l'une des constantes SENT, INVALID_TOKEN,
     * RETRY, FAILED ou DISABLED. Lève une RuntimeException si le jeton OAuth2 ne peut être obtenu.
     *
     * @param array<string, mixed> $data converti en chaînes (exigence de FCM)
     */
    public function send(string $token, string $title, string $body, array $data = []): string
    {
        if (!$this->isConfigured()) {
            Log::debug('FCM non configuré : notification push ignorée.', ['title' => $title]);
            return self::DISABLED;
        }

        $payload = $this->buildMessage($token, $title, $body, $data);

        $response = $this->post($payload);
        if ($response->status() === 401) {
            // Jeton OAuth2 révoqué ou expiré avant l'heure : un nouvel essai avec un jeton neuf.
            Cache::forget($this->cacheKey());
            $response = $this->post($payload);
        }

        if ($response->successful()) {
            return self::SENT;
        }

        $context = [
            'status' => $response->status(),
            'error'  => $response->json('error.message'),
            'token'  => mb_substr($token, 0, 12) . '…',
        ];

        if ($this->isInvalidTokenError($response)) {
            DeviceToken::where('token', $token)->delete();
            Log::info('FCM : jeton d\'appareil invalide supprimé.', $context);
            return self::INVALID_TOKEN;
        }

        if ($response->status() === 429 || $response->serverError()) {
            Log::warning('FCM : erreur temporaire.', $context);
            return self::RETRY;
        }

        Log::warning('FCM : notification refusée.', $context);
        return self::FAILED;
    }

    /** Corps de la requête messages:send (public pour les tests et la commande push:test). */
    public function buildMessage(string $token, string $title, string $body, array $data = []): array
    {
        $android = ['priority' => 'high'];
        $androidNotification = ['sound' => 'default'];
        if ($this->androidChannel) {
            $androidNotification['channel_id'] = $this->androidChannel;
        }
        $android['notification'] = $androidNotification;

        $message = [
            'token'        => $token,
            'notification' => ['title' => $title, 'body' => $body],
            'android'      => $android,
            'apns'         => [
                'headers' => ['apns-priority' => '10'],
                'payload' => ['aps' => ['sound' => 'default']],
            ],
        ];

        $data = self::stringifyData($data);
        if ($data !== []) {
            $message['data'] = $data;
        }

        return ['message' => $message];
    }

    /** FCM n'accepte que des chaînes dans `data` ; les valeurs nulles sont retirées. */
    public static function stringifyData(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            $out[(string) $key] = match (true) {
                is_bool($value)                    => $value ? 'true' : 'false',
                is_array($value), is_object($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
                default                            => (string) $value,
            };
        }
        return $out;
    }

    /** Jeton d'accès OAuth2 (portée firebase.messaging), en cache jusqu'à 5 min avant expiration. */
    public function accessToken(): string
    {
        $cached = Cache::get($this->cacheKey());
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $credentials = $this->credentials();
        if ($credentials === null) {
            throw new RuntimeException('FCM : compte de service Firebase introuvable ou invalide.');
        }

        $tokenUri = $credentials['token_uri'] ?? self::DEFAULT_TOKEN_URI;
        $response = Http::asForm()->timeout($this->timeout)->post($tokenUri, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $this->signedJwt($credentials, $tokenUri),
        ]);

        $accessToken = $response->json('access_token');
        if (!$response->successful() || !is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('FCM : échec de l\'obtention du jeton OAuth2 (HTTP ' . $response->status() . ') '
                . ($response->json('error_description') ?? $response->json('error') ?? ''));
        }

        $ttl = (int) ($response->json('expires_in') ?? 3600) - 300;
        if ($ttl > 0) {
            Cache::put($this->cacheKey(), $accessToken, $ttl);
        }

        return $accessToken;
    }

    private function post(array $payload): Response
    {
        return Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout($this->timeout)
            ->post(sprintf(self::SEND_URL, $this->projectId()), $payload);
    }

    /**
     * Jeton à supprimer : UNREGISTERED (HTTP 404 : application désinstallée, jeton expiré) ou jeton
     * mal formé (INVALID_ARGUMENT portant sur le jeton). Un INVALID_ARGUMENT portant sur un autre
     * champ, un 404 sans UNREGISTERED ou un SENDER_ID_MISMATCH (souvent un mauvais projet configuré
     * côté serveur) ne suppriment rien : sinon une erreur de configuration effacerait tous les jetons.
     */
    private function isInvalidTokenError(Response $response): bool
    {
        $status = $response->status();
        $errorStatus = (string) $response->json('error.status');
        $message = strtolower((string) $response->json('error.message'));
        $codes = [];
        $fields = [];
        foreach ((array) $response->json('error.details', []) as $detail) {
            if (!is_array($detail)) {
                continue;
            }
            if (isset($detail['errorCode'])) {
                $codes[] = $detail['errorCode'];
            }
            foreach ((array) ($detail['fieldViolations'] ?? []) as $violation) {
                $fields[] = $violation['field'] ?? null;
            }
        }

        if (in_array('UNREGISTERED', $codes, true)) {
            return true;
        }

        $invalidArgument = $status === 400
            && ($errorStatus === 'INVALID_ARGUMENT' || in_array('INVALID_ARGUMENT', $codes, true));

        return $invalidArgument && (
            in_array('message.token', $fields, true)
            || str_contains($message, 'registration token')
        );
    }

    private function signedJwt(array $credentials, string $audience): string
    {
        $now = time();
        $segments = [
            self::base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            self::base64Url(json_encode([
                'iss'   => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud'   => $audience,
                'iat'   => $now,
                'exp'   => $now + 3600,
            ])),
        ];

        $key = openssl_pkey_get_private($credentials['private_key']);
        if ($key === false || !openssl_sign(implode('.', $segments), $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('FCM : clé privée du compte de service illisible.');
        }
        $segments[] = self::base64Url($signature);

        return implode('.', $segments);
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function credentials(): ?array
    {
        if ($this->credentialsLoaded) {
            return $this->credentials;
        }
        $this->credentialsLoaded = true;

        $path = $this->resolvePath();
        if ($path === null) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (!is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            Log::warning('FCM : fichier de compte de service invalide (client_email / private_key manquants).', ['path' => $path]);
            return null;
        }

        return $this->credentials = $json;
    }

    private function resolvePath(): ?string
    {
        if (!$this->credentialsPath) {
            return null;
        }
        foreach ([$this->credentialsPath, base_path($this->credentialsPath)] as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private function cacheKey(): string
    {
        return 'fcm.access_token.' . md5(($this->credentials()['client_email'] ?? '') . '|' . $this->projectId());
    }
}
