<?php

namespace App\Services;

use App\Support\YouTube;
use Closure;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Aperçu d'un lien collé dans un texte (miniature, titre, description, site) : balises Open Graph
 * de la page, lues par le serveur et gardées en cache. Le texte enregistré ne change pas.
 *
 * Sécurité : seulement http(s) sur les ports 80/443, jamais une adresse privée, locale ou réservée
 * (vérifiée à chaque redirection, IP figée pour la connexion), 3 redirections, 4 s, 512 Ko de HTML.
 * Voir docs/fonctionnalites/liens-et-apercus.md
 */
final class LinkPreviews
{
    private const CACHE_PREFIX = 'link-preview:';
    private const TTL          = 7 * 86400;  // aperçu trouvé
    private const TTL_MISSING  = 6 * 3600;   // page sans aperçu ou injoignable
    private const MAX_REDIRECTS = 3;
    private const MAX_BYTES    = 512 * 1024;
    private const TIMEOUT      = 4;

    /** Résolution DNS remplaçable dans les tests : fn (string $host): string[] (adresses IP). */
    public static ?Closure $resolveUsing = null;

    /**
     * Aperçu déjà en cache, sans requête réseau.
     *
     * @return array{url: string, title: ?string, description: ?string, image: ?string, siteName: ?string, host: string}|false|null
     *         l'aperçu, false si la page n'en a pas, null s'il n'a pas encore été cherché
     */
    public static function cached(string $url): array|false|null
    {
        $value = Cache::get(self::key($url));

        return $value === null ? null : ($value ?: false);
    }

    /** @return array{url: string, title: ?string, description: ?string, image: ?string, siteName: ?string, host: string}|null */
    public static function get(string $url): ?array
    {
        if (!self::isAllowedUrl($url)) {
            return null;
        }

        $cached = self::cached($url);
        if ($cached !== null) {
            return $cached ?: null;
        }

        $preview = rescue(fn () => self::fetch($url), null, false);
        // false (et non null) : « déjà cherché, rien trouvé ».
        Cache::put(self::key($url), $preview ?? false, $preview ? self::TTL : self::TTL_MISSING);

        return $preview;
    }

    /** Adresse http(s) avec un hôte, sans identifiants, sur le port 80 ou 443. */
    public static function isAllowedUrl(string $url): bool
    {
        if (mb_strlen($url) > 2048) {
            return false;
        }
        $parts = parse_url($url);

        return in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && ($parts['host'] ?? '') !== ''
            && !isset($parts['user'])
            && in_array($parts['port'] ?? null, [null, 80, 443], true);
    }

    private static function key(string $url): string
    {
        return self::CACHE_PREFIX . sha1($url);
    }

    private static function fetch(string $url): ?array
    {
        // YouTube : miniature connue sans lire la page (lourde) ; titre par oEmbed.
        if ($id = YouTube::parseId($url)) {
            return self::youtube($url, $id);
        }

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $response = self::request($url, 'text/html,application/xhtml+xml');
            if ($response === null) {
                return null;
            }
            if (in_array($response['status'], [301, 302, 303, 307, 308], true) && $response['location']) {
                $url = self::absolute($response['location'], $url);
                if ($url === null || !self::isAllowedUrl($url)) {
                    return null;
                }
                continue;
            }
            if ($response['status'] !== 200 || !str_contains(strtolower($response['type']), 'html')) {
                return null;
            }

            return self::parse($response['body'], $url, $response['type']);
        }

        return null;
    }

    private static function youtube(string $url, string $id): array
    {
        $title = null;
        $site  = 'YouTube';
        $oembed = self::request('https://www.youtube.com/oembed?format=json&url=' . rawurlencode(YouTube::watchUrl($id)), 'application/json');
        if ($oembed && $oembed['status'] === 200) {
            $data  = json_decode($oembed['body'], true) ?: [];
            $title = self::clean($data['title'] ?? null, 200);
            if (filled($data['author_name'] ?? null)) {
                $site = 'YouTube · ' . self::clean($data['author_name'], 80);
            }
        }

        return [
            'url'         => $url,
            'title'       => $title,
            'description' => null,
            'image'       => YouTube::thumbnailUrl($id),
            'siteName'    => $site,
            'host'        => 'youtube.com',
        ];
    }

    /**
     * Une requête GET vers une adresse publique, connexion sur l'IP vérifiée (pas de second DNS).
     *
     * @return array{status: int, type: string, location: ?string, body: string}|null
     */
    private static function request(string $url, string $accept): ?array
    {
        $parts = parse_url($url);
        $host  = strtolower(trim($parts['host'] ?? '', '[]'));
        $port  = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);
        $ip    = self::publicIp($host);
        if ($ip === null) {
            return null;
        }

        $response = Http::withHeaders([
                'User-Agent'      => 'Mozilla/5.0 (compatible; MyTestimonyBot/1.0; +' . config('app.url') . ')',
                'Accept'          => $accept,
                'Accept-Language' => app()->getLocale() . ',fr;q=0.9,en;q=0.8',
            ])
            ->timeout(self::TIMEOUT)
            ->connectTimeout(3)
            ->withOptions([
                'allow_redirects' => false,
                'stream'          => true,
                'curl'            => [CURLOPT_RESOLVE => [$host . ':' . $port . ':' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)]],
            ])
            ->get($url);

        $stream = $response->toPsrResponse()->getBody();
        $body   = '';
        while (!$stream->eof() && strlen($body) < self::MAX_BYTES) {
            $body .= $stream->read(16384);
        }

        return [
            'status'   => $response->status(),
            'type'     => (string) $response->header('Content-Type'),
            'location' => $response->header('Location') ?: null,
            'body'     => substr($body, 0, self::MAX_BYTES),
        ];
    }

    /** Première IP de l'hôte si toutes ses adresses sont publiques ; null sinon. */
    private static function publicIp(string $host): ?string
    {
        $ips = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : (self::$resolveUsing ? (self::$resolveUsing)($host) : self::resolve($host));

        if ($ips === []) {
            return null;
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }
        }

        return $ips[0];
    }

    /** @return string[] */
    private static function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(fn ($r) => $r['ip'] ?? $r['ipv6'] ?? null, $records)));
    }

    private static function parse(string $html, string $url, string $contentType): ?array
    {
        $charset = preg_match('/charset=["\']?([\w-]+)/i', $contentType, $m) ? $m[1]
            : (preg_match('/<meta[^>]+charset=["\']?([\w-]+)/i', $html, $m) ? $m[1] : 'UTF-8');
        if (strcasecmp($charset, 'UTF-8') !== 0) {
            $html = @mb_convert_encoding($html, 'UTF-8', $charset) ?: $html;
        }

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $meta = [];
        /** @var \DOMElement $node */
        foreach ((new DOMXPath($dom))->query('//meta[@content]') as $node) {
            $name = strtolower($node->getAttribute('property') ?: $node->getAttribute('name'));
            if ($name !== '' && !isset($meta[$name])) {
                $meta[$name] = trim($node->getAttribute('content'));
            }
        }
        $titleTag = $dom->getElementsByTagName('title')->item(0)?->textContent;

        $title = self::clean($meta['og:title'] ?? $meta['twitter:title'] ?? $titleTag, 200);
        $image = $meta['og:image:secure_url'] ?? $meta['og:image'] ?? $meta['og:image:url'] ?? $meta['twitter:image'] ?? $meta['twitter:image:src'] ?? null;
        $image = $image ? self::absolute($image, $url) : null;
        // Images en https seulement : le site est en https (contenu mixte bloqué).
        if ($image && !str_starts_with(strtolower($image), 'https://')) {
            $image = null;
        }

        if ($title === null && $image === null) {
            return null;
        }

        $host = preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));

        return [
            'url'         => $url,
            'title'       => $title,
            'description' => self::clean($meta['og:description'] ?? $meta['twitter:description'] ?? $meta['description'] ?? null, 300),
            'image'       => $image,
            'siteName'    => self::clean($meta['og:site_name'] ?? null, 80),
            'host'        => $host,
        ];
    }

    private static function clean(?string $text, int $max): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

        return $text === '' ? null : Str::limit($text, $max);
    }

    /** Adresse absolue (relative à $base) en http(s), ou null. */
    private static function absolute(string $href, string $base): ?string
    {
        $href = trim($href);
        if (preg_match('~^https?://~i', $href)) {
            return $href;
        }
        $b = parse_url($base);
        if (!isset($b['scheme'], $b['host'])) {
            return null;
        }
        $origin = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($href, '//')) {
            return $b['scheme'] . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $origin . $href;
        }
        if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $href)) {
            return null; // data:, javascript:…
        }
        $dir = preg_replace('~/[^/]*$~', '/', $b['path'] ?? '/');

        return $origin . $dir . $href;
    }
}
