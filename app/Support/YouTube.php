<?php

namespace App\Support;

/**
 * Vidéos YouTube des témoignages (publication par lien, administrateurs).
 * Lecture par le lecteur intégré « youtube-nocookie » sur le site, le lecteur YouTube dans l'application.
 * Voir docs/fonctionnalites/videos-youtube.md
 */
final class YouTube
{
    /**
     * Identifiant (11 caractères) d'un lien YouTube : youtube.com/watch?v=…, youtu.be/…,
     * /shorts/…, /embed/…, /live/…, m.youtube.com, music.youtube.com, ou l'identifiant seul.
     */
    public static function parseId(?string $input): ?string
    {
        $input = trim((string) $input);
        if ($input === '') return null;
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) return $input;

        $url = preg_match('#^https?://#i', $input) ? $input : 'https://' . $input;
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $host = preg_replace('/^(www\.|m\.|music\.)/', '', $host);
        $path = $parts['path'] ?? '';

        $id = null;
        if ($host === 'youtu.be') {
            $id = explode('/', ltrim($path, '/'))[0] ?? null;
        } elseif (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
            if ($path === '/watch') {
                parse_str($parts['query'] ?? '', $query);
                $id = $query['v'] ?? null;
            } elseif (preg_match('#^/(?:shorts|embed|live|v)/([^/?]+)#', $path, $m)) {
                $id = $m[1];
            }
        }

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : null;
    }

    public static function watchUrl(string $id): string
    {
        return 'https://www.youtube.com/watch?v=' . $id;
    }

    /** Lecteur intégré sans cookie publicitaire avant la lecture. */
    public static function embedUrl(string $id): string
    {
        return 'https://www.youtube-nocookie.com/embed/' . $id . '?rel=0&modestbranding=1&playsinline=1';
    }

    /** Miniature (480 × 360, toujours disponible). */
    public static function thumbnailUrl(string $id): string
    {
        return 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg';
    }
}
