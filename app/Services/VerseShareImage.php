<?php

namespace App\Services;

use App\Models\DailyVerse;
use App\Support\VerseColors as C;
use App\Support\VerseDesigns;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Image de partage du verset du jour (aperçu WhatsApp, Facebook…) : 1080 × 1350, dessinée avec
 * Intervention Image + GD (pas de navigateur : hébergement mutualisé). Mêmes couches et couleurs que
 * le site et l'app (config/verse_design_colors.php), dessinées à l'échelle 3 (360 × 450 px logiques).
 * Générée une seule fois : storage/app/public/verse-images/{date}-{design}.png
 * Voir docs/fonctionnalites/designs-du-verset.md
 */
class VerseShareImage
{
    public const WIDTH = 1080;
    public const HEIGHT = 1350;
    private const DIR = 'verse-images';

    /** Chemin (disque public) de l'image, générée au premier appel. */
    public function path(DailyVerse $verse, string $date, string $designId): string
    {
        $file = self::DIR . "/{$date}-{$designId}.png";
        $disk = Storage::disk('public');
        if (!$disk->exists($file)) {
            $disk->put($file, $this->render($verse, $designId));
        }

        return $disk->path($file);
    }

    /** Images d'un verset (ses dates) ; toutes si le verset sert de repli (sans date) ou change d'état. */
    public static function forgetFor(DailyVerse $verse): void
    {
        $disk = Storage::disk('public');
        if (!$disk->exists(self::DIR)) {
            return;
        }
        $dates = array_filter([$verse->scheduled_date?->toDateString(), $verse->getOriginal('scheduled_date')
            ? \Illuminate\Support\Carbon::parse($verse->getOriginal('scheduled_date'))->toDateString() : null]);
        if (!$dates || $verse->wasChanged('is_active') || !$verse->exists) {
            // Le verset de repli d'une date dépend de l'ensemble des versets actifs.
            $disk->deleteDirectory(self::DIR);

            return;
        }
        foreach ($disk->files(self::DIR) as $f) {
            foreach ($dates as $d) {
                if (str_starts_with(basename($f), $d . '-')) {
                    $disk->delete($f);
                }
            }
        }
    }

    /** PNG de l'image. */
    public function render(DailyVerse $verse, string $designId): string
    {
        $design = VerseDesigns::colors($designId);
        $layers = C::decodeLayers($design['layers']);
        $scale = (int) config('verse_designs.share_scale', 3);
        [$W, $H] = [self::WIDTH, self::HEIGHT];

        $image = (new ImageManager(new Driver()))->create($W, $H);
        $gd = $image->core()->native();
        imagealphablending($gd, true);

        $this->paintSmooth($gd, $layers, $W, $H);
        foreach ($layers as $l) {
            match ($l['type']) {
                'blob'    => $this->paintBlob($gd, $l, $W, $H),
                'pattern' => $this->paintPattern($gd, $l, $W, $H, $scale),
                'grain'   => $this->paintGrain($gd, $l['opacity'], $W, $H),
                default   => null,
            };
        }
        if ($design['scrim']) {
            $this->paintScrim($gd, C::hex($design['scrim']['color']), $design['scrim']['opacity'], $W, $H);
        }
        $this->paintText($gd, $verse, $design, $W, $H, $scale);

        return (string) $image->toPng();
    }

    // ─── Fond ────────────────────────────────────────────────────────────────

    /** Dégradé et halos : calculés au sixième de la résolution puis agrandis (lisses, rapides). */
    private function paintSmooth(\GdImage $gd, array $layers, int $W, int $H): void
    {
        $w = intdiv($W, 6);
        $h = intdiv($H, 6);
        $small = imagecreatetruecolor($w, $h);
        $smooth = array_values(array_filter($layers, fn ($l) => in_array($l['type'], ['linear', 'halo'], true)));
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = [0.5, 0.5, 0.5];
                foreach ($smooth as $l) {
                    $c = C::sample($l, $c, $x + 0.5, $y + 0.5, $w, $h)[0];
                }
                [$r, $g, $b] = C::rgb255($c);
                imagesetpixel($small, $x, $y, ($r << 16) | ($g << 8) | $b);
            }
        }
        imagecopyresampled($gd, $small, 0, 0, 0, 0, $W, $H, $w, $h);
        imagedestroy($small);
    }

    private function paintBlob(\GdImage $gd, array $l, int $W, int $H): void
    {
        $points = [];
        foreach (C::blobPolygon($l, $W, $H) as [$x, $y]) {
            $points[] = (int) round($x);
            $points[] = (int) round($y);
        }
        imagefilledpolygon($gd, $points, $this->color($gd, $l['color'], $l['opacity']));
    }

    private function paintPattern(\GdImage $gd, array $l, int $W, int $H, int $s): void
    {
        $color = $this->color($gd, $l['color'], $l['opacity']);
        $sp = $l['spacing'] * $s;
        imagesetthickness($gd, $s);
        switch ($l['kind']) {
            case 'dots':
                for ($y = $sp / 2; $y < $H; $y += $sp) {
                    for ($x = $sp / 2; $x < $W; $x += $sp) {
                        imagefilledellipse($gd, (int) $x, (int) $y, (int) (2.2 * $s), (int) (2.2 * $s), $color);
                    }
                }
                break;
            case 'stars':
                $rnd = C::rng(7);
                $count = (int) round(($W / $s) * ($H / $s) / ($l['spacing'] ** 2 * 4));
                for ($i = 0; $i < $count; $i++) {
                    [$x, $y, $r, $a] = [$rnd() * $W, $rnd() * $H, (0.5 + $rnd() * 1.3) * $s, 0.4 + $rnd() * 0.6];
                    $d = max(2, (int) round($r * 2));
                    imagefilledellipse($gd, (int) $x, (int) $y, $d, $d, $this->color($gd, $l['color'], $l['opacity'] * $a));
                }
                break;
            case 'diamonds':
                for ($x = -$H; $x < $W + $H; $x += $sp * 1.6) {
                    imageline($gd, (int) $x, 0, (int) ($x + $H), $H, $color);
                    imageline($gd, (int) ($x + $H), 0, (int) $x, $H, $color);
                }
                break;
            case 'arcs':
                for ($r = $sp * 2; $r < max($W, $H) * 1.5; $r += $sp * 1.4) {
                    imagearc($gd, $W, $H, (int) ($r * 2), (int) ($r * 2), 0, 360, $color);
                }
                break;
            case 'lines':
                for ($y = $sp; $y < $H; $y += $sp) {
                    imageline($gd, 0, (int) $y, $W, (int) $y, $color);
                }
                break;
        }
        imagesetthickness($gd, 1);
    }

    /** Grain fin : tuile de bruit (déterministe) répétée. */
    private function paintGrain(\GdImage $gd, float $opacity, int $W, int $H): void
    {
        $size = 96;
        $tile = imagecreatetruecolor($size, $size);
        imagealphablending($tile, false);
        imagesavealpha($tile, true);
        $rnd = C::rng(11);
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $v = $rnd() < 0.5 ? 0 : 255;
                $alpha = 127 - (int) round(127 * $opacity * $rnd());
                imagesetpixel($tile, $x, $y, imagecolorallocatealpha($tile, $v, $v, $v, $alpha));
            }
        }
        for ($y = 0; $y < $H; $y += $size) {
            for ($x = 0; $x < $W; $x += $size) {
                imagecopy($gd, $tile, $x, $y, 0, 0, $size, $size);
            }
        }
        imagedestroy($tile);
    }

    /** Voile derrière le texte (atténué sur les bords haut et bas), comme Scrim dans l'app. */
    private function paintScrim(\GdImage $gd, array $color, float $opacity, int $W, int $H): void
    {
        $edge = C::TEXT_ZONE_INSET * $H;
        for ($y = 0; $y < $H; $y++) {
            $d = min($y, $H - 1 - $y);
            $o = $d >= $edge ? $opacity : $opacity * (0.6 + 0.4 * $d / $edge);
            imageline($gd, 0, $y, $W, $y, $this->color($gd, $color, $o));
        }
    }

    // ─── Texte ───────────────────────────────────────────────────────────────

    private function paintText(\GdImage $gd, DailyVerse $verse, array $design, int $W, int $H, int $s): void
    {
        $ink = $this->color($gd, C::hex($design['ink']), 1);
        $accent = $this->color($gd, C::hex($design['accent']), 1);
        $serif = $this->font('lora-400');
        $serifBold = $this->font('lora-600');
        $sans = $this->font('inter-600');

        $pad = 28 * $s;
        $maxWidth = $W - 2 * $pad;

        // En-tête : « Verset du jour » et ornement.
        $labelSize = 13 * $s;
        $this->text($gd, $sans, $labelSize, $pad, $pad + $labelSize, $accent, 'VERSET DU JOUR');

        // Pied : nom de l'app.
        $footSize = 12 * $s;
        $footY = $H - $pad;
        $this->text($gd, $sans, $footSize, $pad, $footY, $ink, 'ARISE & SHINE Krea · My Testimony');

        // Verset : taille de l'app, réduite de 1 en 1 jusqu'à ce que le texte tienne (min 18).
        $text = '« ' . trim($verse->verse_text) . ' »';
        $reference = trim((string) $verse->reference);
        $range = config('verse_designs.text_size_range.share');
        $lineHeight = (float) config('verse_designs.line_height', 1.4);
        $refSize = 16 * $s;
        $top = $pad + $labelSize + 28 * $s;
        $bottom = $footY - $footSize - 28 * $s;
        $available = $bottom - $top - $refSize * 2.2;

        $size = (int) floor(VerseDesigns::textSize($text, 'share'));
        do {
            $px = $size * $s;
            $lines = $this->wrap($serif, $px, $text, $maxWidth);
            $height = count($lines) * $px * $lineHeight;
        } while ($height > $available && --$size >= $range['min']);
        $size = max($size, $range['min']);
        $px = $size * $s;

        // Bloc centré verticalement entre l'en-tête et le pied.
        $block = $height + $refSize * 2.2;
        $y = $top + max(0, ($bottom - $top - $block) / 2);
        if ($design['ornament'] === 'line') {
            imagefilledrectangle($gd, $pad, (int) ($y - 14 * $s), $pad + 32 * $s, (int) ($y - 12 * $s), $this->color($gd, C::hex($design['accent']), 0.9));
        } elseif ($design['ornament'] === 'quote') {
            $this->text($gd, $serif, 72 * $s, $W - $pad - 48 * $s, $top + 40 * $s, $this->color($gd, C::hex($design['accent']), 0.18), '”');
        }
        foreach ($lines as $line) {
            // Ligne de base : un interligne de 1,4 réparti autour du corps du texte.
            $this->text($gd, $serif, $px, $pad, (int) round($y + $px * (($lineHeight - 1) / 2 + 0.8)), $ink, $line);
            $y += $px * $lineHeight;
        }
        $this->text($gd, $serifBold, $refSize, $pad, (int) round($y + $refSize * 1.6), $accent, $reference);
    }

    /** Retour à la ligne automatique (mots entiers ; un mot trop long reste seul sur sa ligne). */
    private function wrap(string $font, float $px, string $text, float $maxWidth): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/u', $text) as $word) {
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

    private function color(\GdImage $gd, array $c, float $opacity): int
    {
        [$r, $g, $b] = C::rgb255($c);

        return imagecolorallocatealpha($gd, $r, $g, $b, 127 - (int) round(127 * max(0, min(1, $opacity))));
    }
}
