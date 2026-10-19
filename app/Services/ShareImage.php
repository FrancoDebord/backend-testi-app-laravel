<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Boîte à outils des images d'aperçu de partage (WhatsApp, Facebook, Telegram, X, LinkedIn) :
 * 1200 × 630, JPEG qualité 82, dessinées avec Intervention Image + GD (hébergement mutualisé).
 * Voir docs/fonctionnalites/apercu-de-partage.md
 *
 *  - photo (miniature vidéo, couverture) recadrée en 1200 × 630, bouton lecture « YouTube », pastilles ;
 *  - carte de la marque (dégradé bleu, rayons de soleil, titre, auteur, nom de l'app) sans image ;
 *  - cache disque : storage/app/share-images/{type}/{id}-{empreinte}.jpg, recréé quand le contenu change.
 *    Image de secours (miniature distante indisponible) : « .fb.jpg », retentée après une heure.
 *
 * Servies par une route Laravel (/partage/…), jamais par /storage (refusé par Apache en production).
 */
class ShareImage
{
    public const WIDTH = 1200;
    public const HEIGHT = 630;
    public const QUALITY = 82;
    /** À incrémenter quand le dessin change : toutes les images sont recréées. */
    public const VERSION = 1;
    /** Délai (s) avant de retenter une image de secours. */
    public const FALLBACK_TTL = 3600;
    /** Délai maximal de téléchargement d'une miniature distante (s). */
    public const FETCH_TIMEOUT = 5;

    public const SITE_NAME = 'TestiApp · ARISE & SHINE Krea';

    // Couleurs de la marque.
    private const BLUE = [0x18, 0x47, 0x97];
    private const NAVY = [0x0B, 0x2B, 0x66];
    private const ORANGE = [0xF1, 0x87, 0x17];
    private const YELLOW = [0xFC, 0xC1, 0x1D];
    private const WHITE = [0xFF, 0xFF, 0xFF];

    private ?ImageManager $manager = null;

    // ─── Cache ──────────────────────────────────────────────────────────────

    /**
     * Fichier en cache pour ce contenu ; $render (=> [jpeg, complet]) n'est appelé qu'au premier besoin.
     *
     * @param  callable(): array{0: string, 1: bool}  $render
     * @return array{path: string, fallback: bool}
     */
    public function cached(string $kind, string $id, string $fingerprint, callable $render): array
    {
        $dir = $this->root() . DIRECTORY_SEPARATOR . $kind;
        $base = $dir . DIRECTORY_SEPARATOR . $id . '-' . substr($fingerprint, 0, 16);

        if (is_file("{$base}.jpg")) {
            return ['path' => "{$base}.jpg", 'fallback' => false];
        }
        if (is_file("{$base}.fb.jpg") && time() - filemtime("{$base}.fb.jpg") < self::FALLBACK_TTL) {
            return ['path' => "{$base}.fb.jpg", 'fallback' => true];
        }

        File::ensureDirectoryExists($dir);
        // Anciennes versions de ce contenu (titre ou image changés).
        foreach (glob($dir . DIRECTORY_SEPARATOR . $id . '-*.jpg') ?: [] as $old) {
            @unlink($old);
        }

        [$jpeg, $complete] = $render();
        $file = $complete ? "{$base}.jpg" : "{$base}.fb.jpg";
        File::put($file, $jpeg);

        return ['path' => $file, 'fallback' => !$complete];
    }

    /** Images d'un contenu (suppression ou passage en privé). */
    public function forget(string $kind, string $id): void
    {
        foreach (glob($this->root() . DIRECTORY_SEPARATOR . $kind . DIRECTORY_SEPARATOR . $id . '-*.jpg') ?: [] as $file) {
            @unlink($file);
        }
    }

    public function root(): string
    {
        return rtrim((string) config('share_images.root', storage_path('app/share-images')), '/\\');
    }

    /** Empreinte d'un ensemble de valeurs (change dès que l'une d'elles change). */
    public static function fingerprint(array $values): string
    {
        return sha1(json_encode([self::VERSION, ...$values], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    // ─── Sources ────────────────────────────────────────────────────────────

    /**
     * Première image lisible parmi ces adresses, recadrée en 1200 × 630 ; null si aucune.
     * Fichiers du site (/storage/…) : lus sur le disque (pas de requête HTTP, Apache les refuse).
     */
    public function photo(array $urls): ?ImageInterface
    {
        $deadline = microtime(true) + self::FETCH_TIMEOUT + 1;
        foreach (array_filter($urls) as $url) {
            // Budget total de quelques secondes : un hôte injoignable ne retarde pas l'aperçu deux fois.
            if (microtime(true) > $deadline) {
                break;
            }
            $bytes = $this->fetch($url);
            if ($bytes === null) {
                continue;
            }
            try {
                $image = $this->manager()->read($bytes);
                // Miniature YouTube absente : petite image grise (120 × 90) — on passe à la suivante.
                if ($image->width() < 200 || $image->height() < 120) {
                    continue;
                }

                return $image->cover(self::WIDTH, self::HEIGHT);
            } catch (\Throwable $e) {
                Log::info('share-image: image illisible', ['url' => $url, 'error' => $e->getMessage()]);
            }
        }

        return null;
    }

    /** Contenu d'une image (fichier du site ou adresse distante, 5 s au plus), null en cas d'échec. */
    public function fetch(string $url): ?string
    {
        if ($local = $this->localFile($url)) {
            return $local;
        }
        if (!preg_match('#^https?://#i', $url)) {
            return null;
        }

        try {
            // Le CDN Bunny refuse les requêtes sans Referer (protection contre le hotlinking).
            $response = Http::withHeaders([
                'Referer'    => rtrim((string) config('app.url'), '/') . '/',
                'User-Agent' => 'TestiApp-SharePreview/1.0',
                'Accept'     => 'image/*',
            ])->connectTimeout(3)->timeout(self::FETCH_TIMEOUT)->get($url);

            $body = $response->successful() ? $response->body() : '';

            return $body !== '' && strlen($body) < 15 * 1024 * 1024 ? $body : null;
        } catch (\Throwable $e) {
            Log::info('share-image: téléchargement impossible', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** Fichier du disque public désigné par une adresse /storage/… du site, sinon null. */
    private function localFile(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = (string) parse_url($url, PHP_URL_PATH);
        $ownHosts = array_filter([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            parse_url((string) config('app.share_url'), PHP_URL_HOST),
            request()?->getHost(),
        ]);
        if (!str_starts_with($path, '/storage/') || ($host && !in_array($host, $ownHosts, true))) {
            return null;
        }
        $relative = rawurldecode(substr($path, strlen('/storage/')));
        if (str_contains($relative, '..')) {
            return null;
        }
        $disk = Storage::disk('public');

        return $disk->exists($relative) ? $disk->get($relative) : null;
    }

    // ─── Carte de la marque ─────────────────────────────────────────────────

    /**
     * Carte sans photo : dégradé bleu, rayons de soleil, libellé, titre (3 lignes au plus), lignes
     * secondaires (auteur, date, lieu…) et nom de l'app en pied.
     *
     * @param  array{label?: string, title: string, lines?: array<int, string>, quote?: bool, side?: int}  $card
     *         side : largeur réservée à droite (bouton lecture).
     */
    public function brandCard(array $card): ImageInterface
    {
        [$W, $H] = [self::WIDTH, self::HEIGHT];
        $image = $this->manager()->create($W, $H);
        $gd = $image->core()->native();
        imagealphablending($gd, true);

        $sun = [$W - 120, 92];
        $this->paintBackground($gd, $sun);
        $this->paintRays($gd, $sun);
        $this->paintSun($gd, $sun);

        $pad = 72;
        $maxWidth = $W - 2 * $pad - (int) ($card['side'] ?? 0);
        $bold = $this->font('inter-600');
        $regular = $this->font('inter-400');

        // Libellé (catégorie, type) en jaune.
        $y = $pad + 26;
        if (filled($card['label'] ?? null)) {
            $label = mb_strtoupper($this->clean($card['label']));
            $label = $this->ellipsize($bold, 24, $label, $W - 2 * $pad - 220);
            $this->text($gd, $bold, 24, $pad, $y, $this->color($gd, self::YELLOW), $label);
            imagefilledrectangle($gd, $pad, $y + 18, $pad + 56, $y + 22, $this->color($gd, self::ORANGE));
        }

        // Titre : 68 px réduits jusqu'à 46 px pour tenir en 3 lignes, puis « … ».
        $title = $this->clean($card['title']);
        if (!empty($card['quote'])) {
            $title = '« ' . $title . ' »';
        }
        $lines = $card['lines'] ?? [];
        $size = 68;
        do {
            $wrapped = $this->wrap($bold, $size, $title, $maxWidth);
        } while (count($wrapped) > 3 && ($size -= 4) >= 46);
        $size = max($size, 46);
        $wrapped = $this->clamp($bold, $size, $wrapped, 3, $maxWidth);

        $lineHeight = (int) round($size * 1.18);
        $subSize = 30;
        $blockHeight = count($wrapped) * $lineHeight + count($lines) * ($subSize + 16) + (count($lines) ? 18 : 0);
        $top = $pad + 70;
        $bottom = $H - 130;
        $y = $top + max(0, (int) (($bottom - $top - $blockHeight) / 2)) + (int) round($size * 0.92);

        $white = $this->color($gd, self::WHITE);
        foreach ($wrapped as $line) {
            $this->text($gd, $bold, $size, $pad, $y, $white, $line);
            $y += $lineHeight;
        }
        $y += 18 - $lineHeight + $subSize + 16;
        $soft = $this->color($gd, self::WHITE, 0.82);
        foreach ($lines as $line) {
            $line = $this->ellipsize($regular, $subSize, $this->clean($line), $maxWidth);
            $this->text($gd, $regular, $subSize, $pad, $y, $soft, $line);
            $y += $subSize + 16;
        }

        // Pied : étoile de la marque et nom de l'app.
        $footY = $H - 58;
        imagefilledrectangle($gd, 0, $H - 112, $W, $H - 111, $this->color($gd, self::WHITE, 0.14));
        $logo = public_path('icons/arise-shine-krea-star.png');
        $x = $pad;
        if (is_file($logo) && ($star = @imagecreatefrompng($logo))) {
            imagecopyresampled($gd, $star, $pad, $footY - 34, 0, 0, 44, 44, imagesx($star), imagesy($star));
            imagedestroy($star);
            $x += 58;
        }
        $this->text($gd, $bold, 26, $x, $footY, $this->color($gd, self::WHITE, 0.92), self::SITE_NAME);

        return $image;
    }

    /** Dégradé #184797 → #0B2B66 et halo chaud autour du soleil, calculés petits puis agrandis (lisses). */
    private function paintBackground(\GdImage $gd, array $sun): void
    {
        [$W, $H] = [self::WIDTH, self::HEIGHT];
        $w = intdiv($W, 8);
        $h = intdiv($H, 8);
        $small = imagecreatetruecolor($w, $h);
        $radius = 0.75 * $W;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $t = min(1, max(0, 0.65 * $x / $w + 0.55 * $y / $h - 0.1));
                $c = $this->mix(self::BLUE, self::NAVY, $t);
                $d = hypot($x * 8 - $sun[0], $y * 8 - $sun[1]) / $radius;
                $glow = max(0, 1 - $d) ** 2.2;
                $c = $this->mix($c, self::ORANGE, 0.42 * $glow);
                imagesetpixel($small, $x, $y, ($c[0] << 16) | ($c[1] << 8) | $c[2]);
            }
        }
        imagecopyresampled($gd, $small, 0, 0, 0, 0, $W, $H, $w, $h);
        imagedestroy($small);
    }

    /** Rayons discrets, orange et jaune en alternance, partant du soleil. */
    private function paintRays(\GdImage $gd, array $sun): void
    {
        $count = 18;
        $length = 1600;
        for ($i = 0; $i < $count; $i++) {
            $a = deg2rad($i * 360 / $count + 4);
            $half = deg2rad(3.2);
            $points = [
                $sun[0], $sun[1],
                (int) ($sun[0] + cos($a - $half) * $length), (int) ($sun[1] + sin($a - $half) * $length),
                (int) ($sun[0] + cos($a + $half) * $length), (int) ($sun[1] + sin($a + $half) * $length),
            ];
            imagefilledpolygon($gd, $points, $this->color($gd, $i % 2 ? self::YELLOW : self::ORANGE, 0.07));
        }
    }

    private function paintSun(\GdImage $gd, array $sun): void
    {
        $r = 64;
        $this->smooth($gd, $sun[0] - 2 * $r, $sun[1] - 2 * $r, 4 * $r, 4 * $r, function (\GdImage $c, int $s) use ($r) {
            $center = 2 * $r * $s;
            imagefilledellipse($c, $center, $center, 4 * $r * $s, 4 * $r * $s, $this->color($c, self::YELLOW, 0.08));
            imagefilledellipse($c, $center, $center, 3 * $r * $s, 3 * $r * $s, $this->color($c, self::YELLOW, 0.12));
            imagefilledellipse($c, $center, $center, 2 * $r * $s, 2 * $r * $s, $this->color($c, self::YELLOW, 0.95));
        });
    }

    // ─── Calques ────────────────────────────────────────────────────────────

    /** Bouton lecture « YouTube » : disque sombre translucide, anneau clair et triangle blanc. */
    public function playButton(ImageInterface $image, ?int $cx = null, ?int $cy = null, int $diameter = 156): void
    {
        $gd = $image->core()->native();
        $cx ??= intdiv(self::WIDTH, 2);
        $cy ??= intdiv(self::HEIGHT, 2);
        $box = $diameter + 8;
        $this->smooth($gd, $cx - intdiv($box, 2), $cy - intdiv($box, 2), $box, $box, function (\GdImage $c, int $s) use ($box, $diameter) {
            $m = (int) ($box * $s / 2);
            $d = $diameter * $s;
            imagefilledellipse($c, $m, $m, $d + 8 * $s, $d + 8 * $s, $this->color($c, self::WHITE, 0.85));
            imagealphablending($c, false);
            imagefilledellipse($c, $m, $m, $d, $d, $this->color($c, [0, 0, 0], 0.6));
            imagealphablending($c, true);
            $t = $d * 0.36; // triangle, légèrement décalé à droite (centre optique)
            imagefilledpolygon($c, [
                (int) ($m - $t * 0.42), (int) ($m - $t * 0.58),
                (int) ($m - $t * 0.42), (int) ($m + $t * 0.58),
                (int) ($m + $t * 0.62), $m,
            ], $this->color($c, self::WHITE));
        });
    }

    /** Durée en bas à droite (« 3:25 »), comme sur les miniatures vidéo. */
    public function durationBadge(ImageInterface $image, string $label): void
    {
        $gd = $image->core()->native();
        $font = $this->font('inter-600');
        $size = 28;
        $w = (int) $this->width($font, $size, $label) + 28;
        $h = 46;
        $x = self::WIDTH - 28 - $w;
        $y = self::HEIGHT - 28 - $h;
        $this->roundedRect($gd, $x, $y, $w, $h, 8, [0, 0, 0], 0.8);
        $this->text($gd, $font, $size, $x + 14, $y + 34, $this->color($gd, self::WHITE), $label);
    }

    /** Pastille en bas à gauche : écouteurs (audio) ou point rouge (direct) suivis d'un libellé. */
    public function pillBadge(ImageInterface $image, string $label, string $glyph = 'headphones'): void
    {
        $gd = $image->core()->native();
        $font = $this->font('inter-600');
        $size = 28;
        $h = 56;
        $w = (int) $this->width($font, $size, $label) + 92;
        $x = 32;
        $y = self::HEIGHT - 32 - $h;
        $this->roundedRect($gd, $x, $y, $w, $h, 28, [0, 0, 0], 0.72);
        $this->smooth($gd, $x + 18, $y + 12, 36, 32, function (\GdImage $c, int $s) use ($glyph) {
            $white = $this->color($c, self::WHITE);
            if ($glyph === 'live') {
                imagefilledellipse($c, 18 * $s, 16 * $s, 18 * $s, 18 * $s, $this->color($c, [0xEF, 0x44, 0x44]));

                return;
            }
            // Écouteurs : arceau et deux oreillettes.
            imagesetthickness($c, 4 * $s);
            imagearc($c, 18 * $s, 18 * $s, 28 * $s, 28 * $s, 180, 360, $white);
            imagesetthickness($c, 1);
            imagefilledrectangle($c, 2 * $s, 16 * $s, 10 * $s, 31 * $s, $white);
            imagefilledrectangle($c, 26 * $s, 16 * $s, 34 * $s, 31 * $s, $white);
        });
        $this->text($gd, $font, $size, $x + 64, $y + 38, $this->color($gd, self::WHITE), $label);
    }

    /** Voile sombre léger (lisibilité du bouton lecture sur une image très claire). */
    public function dim(ImageInterface $image, float $opacity = 0.12): void
    {
        $gd = $image->core()->native();
        imagefilledrectangle($gd, 0, 0, self::WIDTH, self::HEIGHT, $this->color($gd, [0, 0, 0], $opacity));
    }

    public function jpeg(ImageInterface $image): string
    {
        return (string) $image->toJpeg(self::QUALITY);
    }

    // ─── Texte ──────────────────────────────────────────────────────────────

    /** Texte dessinable : sans émojis ni caractères de contrôle (pas de police couleur sous GD). */
    public function clean(string $text): string
    {
        $text = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE00}-\x{FE0F}\x{200B}-\x{200D}\x{E000}-\x{F8FF}\x{1F1E6}-\x{1F1FF}]/u', '', $text) ?? $text;
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function wrap(string $font, float $px, string $text, float $maxWidth): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $try = $line === '' ? $word : $line . ' ' . $word;
            if ($line !== '' && $this->width($font, $px, $try) > $maxWidth) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    /** $max lignes au plus ; la dernière se termine par « … » si le texte est coupé. */
    private function clamp(string $font, float $px, array $lines, int $max, float $maxWidth): array
    {
        $lines = array_map(fn ($l) => $this->ellipsize($font, $px, $l, $maxWidth), $lines);
        if (count($lines) <= $max) {
            return $lines;
        }
        $kept = array_slice($lines, 0, $max);
        $kept[$max - 1] = $this->ellipsize($font, $px, rtrim($kept[$max - 1], ' ,.;:') . ' …', $maxWidth, true);

        return $kept;
    }

    private function ellipsize(string $font, float $px, string $text, float $maxWidth, bool $forced = false): string
    {
        if ($this->width($font, $px, $text) <= $maxWidth) {
            return $text;
        }
        $base = $forced ? rtrim(mb_substr($text, 0, -2)) : $text;
        while (mb_strlen($base) > 1 && $this->width($font, $px, $base . '…') > $maxWidth) {
            $base = rtrim(mb_substr($base, 0, -1));
        }

        return $base . '…';
    }

    /** GD mesure en points (96 ppp) : px × 0,75. */
    private function width(string $font, float $px, string $text): float
    {
        $box = imagettfbbox($px * 0.75, 0, $font, $text);

        return abs($box[2] - $box[0]);
    }

    private function text(\GdImage $gd, string $font, float $px, float $x, float $baseline, int $color, string $text): void
    {
        imagettftext($gd, $px * 0.75, 0, (int) round($x), (int) round($baseline), $color, $font, $text);
    }

    private function font(string $name): string
    {
        return resource_path("fonts/ttf/{$name}.ttf");
    }

    // ─── Dessin ─────────────────────────────────────────────────────────────

    /** Dessine à l'échelle $s sur un calque transparent puis le réduit : bords lissés (GD n'anticrénelle pas les formes). */
    private function smooth(\GdImage $gd, int $x, int $y, int $w, int $h, callable $draw, int $s = 4): void
    {
        $layer = imagecreatetruecolor($w * $s, $h * $s);
        imagealphablending($layer, false);
        imagesavealpha($layer, true);
        imagefilledrectangle($layer, 0, 0, $w * $s, $h * $s, imagecolorallocatealpha($layer, 0, 0, 0, 127));
        imagealphablending($layer, true);
        $draw($layer, $s);
        imagealphablending($gd, true);
        imagecopyresampled($gd, $layer, $x, $y, 0, 0, $w, $h, $w * $s, $h * $s);
        imagedestroy($layer);
    }

    private function roundedRect(\GdImage $gd, int $x, int $y, int $w, int $h, int $r, array $rgb, float $opacity): void
    {
        $this->smooth($gd, $x, $y, $w, $h, function (\GdImage $c, int $s) use ($w, $h, $r, $rgb, $opacity) {
            imagealphablending($c, false); // une seule couche : pas de chevauchement plus sombre
            $color = $this->color($c, $rgb, $opacity);
            [$W, $H, $R] = [$w * $s, $h * $s, min($r, intdiv($h, 2)) * $s];
            imagefilledrectangle($c, $R, 0, $W - $R - 1, $H - 1, $color);
            imagefilledrectangle($c, 0, $R, $W - 1, $H - $R - 1, $color);
            foreach ([[$R, $R], [$W - $R - 1, $R], [$R, $H - $R - 1], [$W - $R - 1, $H - $R - 1]] as [$cx, $cy]) {
                imagefilledellipse($c, $cx, $cy, 2 * $R, 2 * $R, $color);
            }
            imagealphablending($c, true);
        });
    }

    private function mix(array $a, array $b, float $t): array
    {
        return [
            (int) round($a[0] + ($b[0] - $a[0]) * $t),
            (int) round($a[1] + ($b[1] - $a[1]) * $t),
            (int) round($a[2] + ($b[2] - $a[2]) * $t),
        ];
    }

    private function color(\GdImage $gd, array $rgb, float $opacity = 1.0): int
    {
        return imagecolorallocatealpha($gd, $rgb[0], $rgb[1], $rgb[2], 127 - (int) round(127 * max(0, min(1, $opacity))));
    }

    private function manager(): ImageManager
    {
        return $this->manager ??= new ImageManager(new Driver());
    }
}
