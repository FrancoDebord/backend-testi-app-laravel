<?php

namespace App\Support;

/**
 * Calcul des couleurs des designs du verset du jour : portage PHP de docs/flutter/verse_design.dart
 * (VerseTones, échantillonnage des couches, contraste WCAG, voile). Sert au script
 * scripts/generate-verse-colors.php, à l'image de partage et au test de contraste.
 *
 * Une couleur est un tableau [r, g, b] de flottants 0 à 1 (sRGB). Les couches sont des tableaux :
 *   linear  : colors, stops (null = réguliers), begin, end (alignements [x, y] de -1 à 1)
 *   halo    : center, radius (fraction du plus grand côté), color, opacity
 *   blob    : center, radius, color, opacity, seed, lobes, wobble
 *   pattern : kind (dots|stars|diamonds|arcs|lines), color, opacity, spacing
 *   grain   : opacity
 *   scrim   : color, opacity
 * Voir docs/fonctionnalites/designs-du-verset.md
 */
final class VerseColors
{
    /** Contraste minimal visé (WCAG AA) et marge de sécurité (grain, anticrénelage), comme Flutter. */
    public const MIN_CONTRAST = 4.5;
    public const TARGET = 4.65;

    /** Marge (fraction de chaque côté) hors de la zone où du texte peut être posé. */
    public const TEXT_ZONE_INSET = 0.035;

    // ─── Couleurs ────────────────────────────────────────────────────────────

    public static function hex(string $hex): array
    {
        $h = ltrim($hex, '#');

        return [hexdec(substr($h, 0, 2)) / 255, hexdec(substr($h, 2, 2)) / 255, hexdec(substr($h, 4, 2)) / 255];
    }

    public static function toHex(array $c): string
    {
        return sprintf('#%02x%02x%02x', ...array_map(fn ($v) => (int) round(max(0, min(1, $v)) * 255), $c));
    }

    /** Composantes 0 à 255 (GD). */
    public static function rgb255(array $c): array
    {
        return array_map(fn ($v) => (int) round(max(0, min(1, $v)) * 255), $c);
    }

    public static function lerp(array $a, array $b, float $t): array
    {
        return [$a[0] + ($b[0] - $a[0]) * $t, $a[1] + ($b[1] - $a[1]) * $t, $a[2] + ($b[2] - $a[2]) * $t];
    }

    /** $top posée sur $bottom avec l'opacité $opacity. */
    public static function over(array $bottom, array $top, float $opacity): array
    {
        return self::lerp($bottom, $top, max(0.0, min(1.0, $opacity)));
    }

    public static function shade(array $c, float $f): array
    {
        return self::lerp($c, [0, 0, 0], $f);
    }

    public static function tint(array $c, float $f): array
    {
        return self::lerp($c, [1, 1, 1], $f);
    }

    /** Version « profonde » d'une couleur (luminosité HSL ≤ $max), comme VerseTones.deep. */
    public static function deep(array $c, float $max = 0.36): array
    {
        [$h, $s, $l] = self::toHsl($c);

        // HSLColor.toColor() arrondit chaque composante à 8 bits.
        return $l <= $max ? $c : self::hex(self::toHex(self::fromHsl($h, $s, $max)));
    }

    public static function luminance(array $c): float
    {
        $lin = fn ($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $lin($c[0]) + 0.7152 * $lin($c[1]) + 0.0722 * $lin($c[2]);
    }

    public static function contrast(array $a, array $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    public static function minContrast(array $ink, array $samples): float
    {
        $m = 21.0;
        foreach ($samples as $c) {
            $m = min($m, self::contrast($ink, $c));
        }

        return $m;
    }

    private static function toHsl(array $c): array
    {
        [$r, $g, $b] = $c;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;
        if ($d == 0) {
            return [0.0, 0.0, $l];
        }
        $s = $d / (1 - abs(2 * $l - 1));
        $h = match (true) {
            $max == $r => 60 * fmod(($g - $b) / $d, 6),
            $max == $g => 60 * (($b - $r) / $d + 2),
            default    => 60 * (($r - $g) / $d + 4),
        };

        return [$h < 0 ? $h + 360 : $h, $s, $l];
    }

    private static function fromHsl(float $h, float $s, float $l): array
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match (true) {
            $h < 60  => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default  => [$c, 0, $x],
        };

        return [$r + $m, $g + $m, $b + $m];
    }

    // ─── Géométrie ───────────────────────────────────────────────────────────

    /** Point d'un alignement Flutter ([x, y] de -1 à 1) dans un rectangle w × h. */
    public static function at(array $align, float $w, float $h): array
    {
        return [($align[0] + 1) / 2 * $w, ($align[1] + 1) / 2 * $h];
    }

    /** Position t (0 à 1) d'un point le long du dégradé linéaire. */
    public static function linearT(array $layer, float $x, float $y, float $w, float $h): float
    {
        [$ax, $ay] = self::at($layer['begin'], $w, $h);
        [$bx, $by] = self::at($layer['end'], $w, $h);
        $dx = $bx - $ax;
        $dy = $by - $ay;
        $len2 = $dx * $dx + $dy * $dy;

        return $len2 == 0 ? 0.0 : max(0.0, min(1.0, (($x - $ax) * $dx + ($y - $ay) * $dy) / $len2));
    }

    public static function stops(array $layer): array
    {
        $n = count($layer['colors']);

        return $layer['stops'] ?? ($n === 1 ? [0.0] : array_map(fn ($i) => $i / ($n - 1), range(0, $n - 1)));
    }

    public static function linearColor(array $layer, float $t): array
    {
        $colors = $layer['colors'];
        if (count($colors) === 1) {
            return $colors[0];
        }
        $s = self::stops($layer);
        for ($i = 0; $i < count($s) - 1; $i++) {
            if ($t <= $s[$i + 1]) {
                $span = $s[$i + 1] - $s[$i];

                return self::lerp($colors[$i], $colors[$i + 1], $span == 0 ? 0 : ($t - $s[$i]) / $span);
            }
        }

        return end($colors);
    }

    /** Opacité relative d'un halo à la distance réduite d (0 au centre, 1 au bord) : arrêts 0 / 0,45 / 1. */
    public static function haloAlpha(float $d): float
    {
        if ($d >= 1) {
            return 0.0;
        }

        return $d <= 0.45 ? 1 - 0.55 * ($d / 0.45) : 0.45 * (1 - ($d - 0.45) / 0.55);
    }

    // ─── Échantillonnage (contraste) ─────────────────────────────────────────

    /**
     * Couleurs possibles d'une couche en un point (plusieurs quand la couche est une texture ou un
     * contour incertain). Les formes organiques (blob) sont prises comme des cercles : dedans, dehors,
     * ou les deux dans la bande d'ondulation (prudent).
     */
    public static function sample(array $layer, array $under, float $x, float $y, float $w, float $h): array
    {
        $long = max($w, $h);

        switch ($layer['type']) {
            case 'linear':
                return [self::linearColor($layer, self::linearT($layer, $x, $y, $w, $h))];
            case 'halo':
                [$cx, $cy] = self::at($layer['center'], $w, $h);
                $d = hypot($x - $cx, $y - $cy) / ($layer['radius'] * $long);

                return [self::over($under, $layer['color'], $layer['opacity'] * self::haloAlpha($d))];
            case 'blob':
                [$cx, $cy] = self::at($layer['center'], $w, $h);
                $d = hypot($x - $cx, $y - $cy) / ($layer['radius'] * $long);
                $in = self::over($under, $layer['color'], $layer['opacity']);
                $band = ($layer['wobble'] ?? 0.22) + 0.06;
                if ($d < 1 - $band) {
                    return [$in];
                }

                return $d > 1 + $band ? [$under] : [$under, $in];
            case 'pattern':
                $once = self::over($under, $layer['color'], $layer['opacity']);

                return in_array($layer['kind'], ['diamonds', 'arcs'], true)
                    ? [$under, $once, self::over($once, $layer['color'], $layer['opacity'])]
                    : [$under, $once];
            case 'grain':
                return [self::over($under, [0, 0, 0], $layer['opacity']), self::over($under, [1, 1, 1], $layer['opacity'])];
            case 'scrim':
                return [self::over($under, $layer['color'], $layer['opacity'])];
        }

        return [$under];
    }

    /** Couleurs possibles sous la zone de texte (grille 13 × 13 + centre de chaque halo). */
    public static function backgroundSamples(array $layers, float $w, float $h, int $grid = 13): array
    {
        $i = self::TEXT_ZONE_INSET;
        [$l, $t, $r, $b] = [$w * $i, $h * $i, $w * (1 - $i), $h * (1 - $i)];
        $points = [];
        for ($iy = 0; $iy < $grid; $iy++) {
            for ($ix = 0; $ix < $grid; $ix++) {
                $points[] = [$l + ($r - $l) * $ix / ($grid - 1), $t + ($b - $t) * $iy / ($grid - 1)];
            }
        }
        foreach ($layers as $layer) {
            if ($layer['type'] === 'halo') {
                [$cx, $cy] = self::at($layer['center'], $w, $h);
                $points[] = [max($l, min($r, $cx)), max($t, min($b, $cy))];
            }
        }

        $out = [];
        foreach ($points as [$x, $y]) {
            $colors = [[0.5, 0.5, 0.5]];
            foreach ($layers as $layer) {
                $next = [];
                foreach ($colors as $c) {
                    foreach (self::sample($layer, $c, $x, $y, $w, $h) as $s) {
                        $next[self::toHex($s) . implode(',', array_map(fn ($v) => round($v, 4), $s))] = $s;
                    }
                }
                $colors = array_values($next);
            }
            array_push($out, ...$colors);
        }

        return $out;
    }

    /**
     * Encre, accent et voile d'un design (ResolvedVerseDesign._compute), sur la réunion des
     * échantillons de plusieurs formats ($sizes : [[w, h], …]) : un seul réglage valable partout.
     */
    public static function resolve(array $layers, array $tones, array $sizes): array
    {
        $bg = [];
        foreach ($sizes as [$w, $h]) {
            array_push($bg, ...self::backgroundSamples($layers, $w, $h));
        }

        $light = self::minContrast($tones['inkLight'], $bg);
        $dark  = self::minContrast($tones['inkDark'], $bg);
        $lightInk = $light >= $dark;
        $ink = $lightInk ? $tones['inkLight'] : $tones['inkDark'];

        $scrim = 0.0;
        $scrimColor = null;
        $samples = $bg;
        if (max($light, $dark) < self::TARGET) {
            $scrimColor = $lightInk ? self::shade($tones['primary'], 0.7) : $tones['surface'];
            for ($scrim = 0.04; $scrim <= 0.9; $scrim += 0.02) {
                $s = array_map(fn ($c) => self::over($c, $scrimColor, $scrim), $bg);
                if (self::minContrast($ink, $s) >= self::TARGET) {
                    break;
                }
            }
            $scrim = round(min($scrim, 0.9), 2);
            $samples = array_map(fn ($c) => self::over($c, $scrimColor, $scrim), $bg);
        }

        $inkContrast = self::minContrast($ink, $samples);
        $accent = $ink;
        $accentContrast = $inkContrast;
        foreach ($lightInk ? $tones['accentsOnDark'] : $tones['accentsOnLight'] as $a) {
            $c = self::minContrast($a, $samples);
            if ($c >= self::TARGET) {
                $accent = $a;
                $accentContrast = $c;
                break;
            }
        }

        return [
            'lightInk'       => $lightInk,
            'ink'            => $ink,
            'accent'         => $accent,
            'scrim'          => $scrimColor ? ['color' => $scrimColor, 'opacity' => $scrim] : null,
            'inkContrast'    => $inkContrast,
            'accentContrast' => $accentContrast,
        ];
    }

    // ─── Palette et designs ──────────────────────────────────────────────────

    /** Teintes de travail (VerseTones, thème clair) d'après les couleurs de l'app. */
    public static function tones(array $scheme): array
    {
        $p = self::hex($scheme['primary']);
        $s = self::hex($scheme['secondary']);
        $t = self::hex($scheme['tertiary']);
        $surface = self::hex($scheme['surface']);
        $primary = self::deep($p, 0.34);

        return [
            'schemePrimary'   => $p,
            'schemeSecondary' => $s,
            'primary'         => $primary,
            'secondary'       => $s,
            'tertiary'        => $t,
            'surface'         => $surface,
            'inkLight'        => $surface,
            'inkDark'         => self::shade($primary, 0.55),
            'accentsOnDark'   => [self::tint($t, 0.15), self::tint($s, 0.35), self::tint($p, 0.6)],
            'accentsOnLight'  => [$primary, self::shade($s, 0.42), self::shade($t, 0.62)],
        ];
    }

    /**
     * Couches des 12 designs (kVerseDesigns), de la plus basse à la plus haute, et ornement
     * (line | quote | none). Ajouter un design ici ET dans l'app, puis dans config/verse_designs.php.
     */
    public static function recipes(array $t): array
    {
        $paper = fn (array $c, float $f) => self::tint($c, $f); // thème clair
        $P = $t['primary'];
        $S = $t['secondary'];
        $T = $t['tertiary'];
        // Alignements Flutter : topRight, bottomLeft, topCenter, bottomCenter.
        [$TR, $BL, $TC, $BC] = [[1, -1], [-1, 1], [0, -1], [0, 1]];
        $lin = fn (array $colors, ?array $stops = null, array $begin = [-1, -1], array $end = [1, 1]) =>
            ['type' => 'linear', 'colors' => $colors, 'stops' => $stops, 'begin' => $begin, 'end' => $end];
        $halo = fn (array $c, float $r, array $color, float $o) => ['type' => 'halo', 'center' => $c, 'radius' => $r, 'color' => $color, 'opacity' => $o];
        $blob = fn (array $c, float $r, array $color, float $o, int $seed) => ['type' => 'blob', 'center' => $c, 'radius' => $r, 'color' => $color, 'opacity' => $o, 'seed' => $seed, 'lobes' => 6, 'wobble' => 0.22];
        $pattern = fn (string $k, array $color, float $o, float $sp) => ['type' => 'pattern', 'kind' => $k, 'color' => $color, 'opacity' => $o, 'spacing' => $sp];
        $grain = fn (float $o) => ['type' => 'grain', 'opacity' => $o];
        $warm = self::deep($t['schemeSecondary'], 0.4);
        $slate = self::lerp($t['surface'], $P, 0.35);

        return [
            'aube' => ['ornament' => 'line', 'layers' => [
                $lin([$paper($T, 0.78), $paper($S, 0.86), $paper($P, 0.94)], null, $TR, $BL),
                $halo([0.9, -0.95], 0.75, $paper($T, 0.35), 0.55),
                $blob([-1.1, 1.0], 0.42, $paper($S, 0.6), 0.22, 3),
                $grain(0.04),
            ]],
            'nuit' => ['ornament' => 'none', 'layers' => [
                $lin([self::shade($P, 0.45), self::shade($P, 0.78)], null, $TC, $BC),
                $halo([-0.9, 1.0], 0.8, self::tint($P, 0.25), 0.25),
                $pattern('stars', self::tint($T, 0.5), 0.55, 18),
                $grain(0.05),
            ]],
            'ocean' => ['ornament' => 'quote', 'layers' => [
                $lin([$P, self::shade($P, 0.5)]),
                $halo([-1.0, -1.0], 0.7, self::tint($P, 0.3), 0.35),
                $blob([1.0, 1.05], 0.5, self::shade($P, 0.7), 0.35, 5),
                $blob([-0.7, 1.15], 0.35, self::tint($P, 0.2), 0.12, 9),
                $grain(0.05),
            ]],
            'lumiere' => ['ornament' => 'line', 'layers' => [
                $lin([$paper($P, 0.97), $paper($P, 0.9)], null, $TC, $BC),
                $halo([-0.95, -0.95], 0.85, $paper($T, 0.45), 0.5),
                $grain(0.035),
            ]],
            'braise' => ['ornament' => 'quote', 'layers' => [
                $lin([self::shade($warm, 0.2), self::shade($warm, 0.55), self::shade($P, 0.55)], [0, 0.55, 1]),
                $halo([0.95, 0.95], 0.75, $T, 0.28),
                $grain(0.05),
            ]],
            'minimal' => ['ornament' => 'line', 'layers' => [
                $lin([$paper($P, 0.975)]),
                $grain(0.03),
            ]],
            'vitrail' => ['ornament' => 'line', 'layers' => [
                $lin([self::shade($P, 0.15), self::shade($P, 0.6)], null, $TC, $BC),
                $pattern('diamonds', self::tint($P, 0.45), 0.1, 26),
                $halo([0, -1.05], 0.6, $T, 0.18),
            ]],
            'crepuscule' => ['ornament' => 'none', 'layers' => [
                $lin([self::shade($P, 0.55), self::shade(self::lerp($P, $S, 0.45), 0.35), self::shade($S, 0.3)], [0, 0.6, 1], $TC, $BC),
                $halo([0, 1.2], 0.65, $T, 0.3),
                $grain(0.05),
            ]],
            'matin' => ['ornament' => 'quote', 'layers' => [
                $lin([$paper($P, 0.88), $paper($P, 0.96)]),
                $blob([1.0, -1.0], 0.45, $paper($P, 0.72), 0.45, 2),
                $blob([-1.05, 0.9], 0.3, $paper($T, 0.6), 0.35, 4),
                $grain(0.035),
            ]],
            'or' => ['ornament' => 'line', 'layers' => [
                $lin([$paper($T, 0.62), $paper($T, 0.86)], null, $BL, $TR),
                $pattern('arcs', self::shade($T, 0.35), 0.1, 20),
                $halo([-0.9, -1.0], 0.7, $paper($T, 0.92), 0.6),
                $grain(0.035),
            ]],
            'encre' => ['ornament' => 'quote', 'layers' => [
                $lin([self::shade($P, 0.78), self::shade($P, 0.9)]),
                $halo([1.0, -1.0], 0.7, self::tint($P, 0.2), 0.22),
                $grain(0.07),
            ]],
            'ardoise' => ['ornament' => 'line', 'layers' => [
                $lin([self::shade($slate, 0.6), self::shade($slate, 0.72)], null, $TC, $BC),
                $pattern('lines', self::tint($P, 0.5), 0.07, 28),
                $grain(0.04),
            ]],
        ];
    }

    /** Couches enregistrées (couleurs en #rrggbb) → couleurs flottantes. */
    public static function decodeLayers(array $layers): array
    {
        return array_map(function (array $l) {
            if (isset($l['colors'])) {
                $l['colors'] = array_map([self::class, 'hex'], $l['colors']);
            }
            if (isset($l['color'])) {
                $l['color'] = self::hex($l['color']);
            }

            return $l;
        }, $layers);
    }

    /** Couches → couleurs en #rrggbb (fichier généré). */
    public static function encodeLayers(array $layers): array
    {
        return array_map(function (array $l) {
            if (isset($l['colors'])) {
                $l['colors'] = array_map([self::class, 'toHex'], $l['colors']);
            }
            if (isset($l['color'])) {
                $l['color'] = self::toHex($l['color']);
            }

            return $l;
        }, $layers);
    }

    /** Petit générateur pseudo-aléatoire déterministe (formes et étoiles identiques web / image). */
    public static function rng(int $seed): \Closure
    {
        $state = $seed * 2654435761 % 4294967296 ?: 1;

        return function () use (&$state): float {
            $state = (1103515245 * $state + 12345) % 2147483648;

            return $state / 2147483648;
        };
    }

    /** Contour d'une forme organique : points [x, y] (polygone fin, courbe de Catmull-Rom). */
    public static function blobPolygon(array $layer, float $w, float $h, int $steps = 8): array
    {
        [$cx, $cy] = self::at($layer['center'], $w, $h);
        $r = $layer['radius'] * max($w, $h);
        $rnd = self::rng($layer['seed']);
        $n = $layer['lobes'] * 2;
        $pts = [];
        for ($i = 0; $i < $n; $i++) {
            $ang = $i / $n * M_PI * 2;
            $k = 1 + ($rnd() * 2 - 1) * $layer['wobble'];
            $pts[] = [$cx + cos($ang) * $r * $k, $cy + sin($ang) * $r * $k];
        }
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            [$p0, $p1, $p2, $p3] = [$pts[($i - 1 + $n) % $n], $pts[$i], $pts[($i + 1) % $n], $pts[($i + 2) % $n]];
            for ($s = 0; $s < $steps; $s++) {
                $u = $s / $steps;
                $u2 = $u * $u;
                $u3 = $u2 * $u;
                $out[] = [
                    0.5 * (2 * $p1[0] + (-$p0[0] + $p2[0]) * $u + (2 * $p0[0] - 5 * $p1[0] + 4 * $p2[0] - $p3[0]) * $u2 + (-$p0[0] + 3 * $p1[0] - 3 * $p2[0] + $p3[0]) * $u3),
                    0.5 * (2 * $p1[1] + (-$p0[1] + $p2[1]) * $u + (2 * $p0[1] - 5 * $p1[1] + 4 * $p2[1] - $p3[1]) * $u2 + (-$p0[1] + 3 * $p1[1] - 3 * $p2[1] + $p3[1]) * $u3),
                ];
            }
        }

        return $out;
    }
}
