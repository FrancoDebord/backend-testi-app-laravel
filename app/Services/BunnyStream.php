<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * API Bunny Stream (docs/fonctionnalites/videos-bunny-stream.md).
 *
 * Les fichiers vidéo ne passent jamais par ce serveur : l'application les envoie directement à Bunny
 * (protocole TUS, autorisé par uploadSignature) ; la reprise des anciennes vidéos demande à Bunny
 * d'aller chercher le fichier lui-même (fetchFromUrl).
 */
class BunnyStream
{
    public const API_BASE     = 'https://video.bunnycdn.com';
    public const TUS_ENDPOINT = 'https://video.bunnycdn.com/tusupload';

    /** Durée de validité d'une autorisation d'envoi TUS, en secondes. */
    public const SIGNATURE_TTL = 7200;

    /** Qualités MP4 proposées (option « MP4 Fallback » de la bibliothèque). */
    public const MP4_HEIGHTS = [240, 360, 480, 720];

    // Statuts d'une vidéo renvoyés par GET /library/{id}/videos/{guid}.
    public const STATUS_CREATED       = 0;
    public const STATUS_UPLOADED      = 1;
    public const STATUS_PROCESSING    = 2;
    public const STATUS_TRANSCODING   = 3;
    public const STATUS_FINISHED      = 4;
    public const STATUS_ERROR         = 5;
    public const STATUS_UPLOAD_FAILED = 6;

    public function isConfigured(): bool
    {
        return filled($this->libraryId()) && filled($this->apiKey()) && filled($this->cdnHost());
    }

    public function libraryId(): string
    {
        return trim((string) config('services.bunny_stream.library_id'));
    }

    /** Crée la vidéo (vide) dans la bibliothèque et renvoie son guid. */
    public function createVideo(string $title): string
    {
        $response = $this->client()
            ->post("/library/{$this->libraryId()}/videos", ['title' => $title])
            ->throw();

        return $this->guidFrom($response->json());
    }

    /**
     * Autorisation d'envoi TUS pour une vidéo : en-têtes AuthorizationSignature, AuthorizationExpire,
     * VideoId et LibraryId de la requête TUS.
     *
     * @return array{signature: string, expiration: int}
     */
    public function uploadSignature(string $guid): array
    {
        $expiration = time() + self::SIGNATURE_TTL;

        return [
            'signature'  => hash('sha256', $this->libraryId() . $this->apiKey() . $expiration . $guid),
            'expiration' => $expiration,
        ];
    }

    /** @return array<string, mixed> vidéo telle que décrite par Bunny (status, length, availableResolutions…) */
    public function getVideo(string $guid): array
    {
        return (array) $this->client()
            ->get("/library/{$this->libraryId()}/videos/{$guid}")
            ->throw()
            ->json();
    }

    public function deleteVideo(string $guid): void
    {
        $this->client()
            ->delete("/library/{$this->libraryId()}/videos/{$guid}")
            ->throw();
    }

    /** Bunny télécharge lui-même le fichier à cette URL (aucun transfert par ce serveur) ; renvoie le guid. */
    public function fetchFromUrl(string $url, string $title): string
    {
        $response = $this->client()
            ->post("/library/{$this->libraryId()}/videos/fetch", ['url' => $url, 'title' => $title])
            ->throw();

        return $this->guidFrom($response->json());
    }

    // ---------- URL de lecture (CDN) ----------

    public function hlsUrl(string $guid): string
    {
        return "https://{$this->cdnHost()}/{$guid}/playlist.m3u8";
    }

    public function thumbnailUrl(string $guid): string
    {
        return "https://{$this->cdnHost()}/{$guid}/thumbnail.jpg";
    }

    /** Lecteur intégré Bunny (données structurées VideoObject.embedUrl, og:video : docs/fonctionnalites/referencement.md). */
    public function embedUrl(string $guid): string
    {
        return "https://iframe.mediadelivery.net/embed/{$this->libraryId()}/{$guid}";
    }

    public function mp4Url(string $guid, int $height): string
    {
        return "https://{$this->cdnHost()}/{$guid}/play_{$height}p.mp4";
    }

    // ---------- Interne ----------

    private function apiKey(): string
    {
        return trim((string) config('services.bunny_stream.api_key'));
    }

    /** Nom d'hôte du CDN, toléré avec https:// ou une barre finale (erreur de saisie fréquente dans le .env). */
    public function cdnHost(): string
    {
        return rtrim((string) preg_replace('#^https?://#i', '', trim((string) config('services.bunny_stream.cdn_host'))), '/');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(self::API_BASE)
            ->withHeaders(['AccessKey' => $this->apiKey()])
            ->acceptJson()
            ->timeout(15);
    }

    /** guid de la réponse (création : guid ; récupération par URL : guid ou id selon la version de l'API). */
    private function guidFrom(mixed $json): string
    {
        $guid = is_array($json) ? ($json['guid'] ?? $json['id'] ?? null) : null;
        if (!is_string($guid) || $guid === '') {
            throw new RuntimeException('Bunny Stream : réponse sans identifiant de vidéo.');
        }

        return $guid;
    }
}
