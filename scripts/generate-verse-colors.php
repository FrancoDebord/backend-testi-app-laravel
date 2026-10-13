<?php

/**
 * Génère les couleurs des designs du verset du jour à partir de la palette de l'app Flutter.
 *
 *   php scripts/generate-verse-colors.php
 *   php scripts/generate-verse-colors.php --primary=#184797 --secondary=#F18717 --tertiary=#FCC11D \
 *       --surface=#FFFFFF --on-surface=#263238
 *
 * Écrit :
 *   config/verse_design_colors.php     couches, encre, accent, voile, contraste (image de partage, tests)
 *   resources/css/verse-designs.css    une classe .verse-design--{id} par design (site)
 *
 * Les couches reprennent kVerseDesigns (docs/flutter/verse_design.dart) via App\Support\VerseColors.
 * Après une régénération : recompiler les assets et vider storage/app/public/verse-images.
 * Voir docs/fonctionnalites/designs-du-verset.md
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Support\VerseColors as C;

$root = dirname(__DIR__);
$opts = getopt('', ['primary::', 'secondary::', 'tertiary::', 'surface::', 'on-surface::']);
$scheme = [
    'primary'   => $opts['primary'] ?? '#184797',
    'secondary' => $opts['secondary'] ?? '#F18717',
    'tertiary'  => $opts['tertiary'] ?? '#FCC11D',
    'surface'   => $opts['surface'] ?? '#FFFFFF',
    'onSurface' => $opts['on-surface'] ?? '#263238',
];
foreach ($scheme as $k => $v) {
    if (!preg_match('/^#?[0-9a-fA-F]{6}$/', $v)) {
        fwrite(STDERR, "Couleur invalide pour {$k} : {$v}\n");
        exit(1);
    }
    $scheme[$k] = '#' . strtoupper(ltrim($v, '#'));
}

$catalogue = require $root . '/config/verse_designs.php';
$tones = C::tones($scheme);
$recipes = C::recipes($tones);

// Formats sur lesquels le contraste est garanti (px logiques) : carte, plein écran (téléphone,
// ordinateur), image de partage (1080 × 1350 à l'échelle 3).
$sizes = [[360, 220], [390, 700], [1100, 640], [360, 450]];

$designs = [];
$problems = [];
foreach ($catalogue['designs'] as $entry) {
    $id = $entry['id'];
    if (!isset($recipes[$id])) {
        $problems[] = "{$id} : aucune recette dans App\\Support\\VerseColors::recipes()";
        continue;
    }
    $r = C::resolve($recipes[$id]['layers'], $tones, $sizes);
    $tone = $r['lightInk'] ? 'dark' : 'light';
    if ($tone !== $entry['tone_light_theme']) {
        $problems[] = "{$id} : rendu calculé « {$tone} », catalogue « {$entry['tone_light_theme']} »";
    }
    if ($r['inkContrast'] < C::MIN_CONTRAST) {
        $problems[] = sprintf('%s : contraste du texte %.2f < 4,5', $id, $r['inkContrast']);
    }
    $designs[$id] = [
        'tone'            => $tone,
        'ornament'        => $recipes[$id]['ornament'],
        'ink'             => C::toHex($r['ink']),
        'accent'          => C::toHex($r['accent']),
        'scrim'           => $r['scrim'] ? ['color' => C::toHex($r['scrim']['color']), 'opacity' => $r['scrim']['opacity']] : null,
        'ink_contrast'    => round($r['inkContrast'], 2),
        'accent_contrast' => round($r['accentContrast'], 2),
        'layers'          => C::encodeLayers($recipes[$id]['layers']),
    ];
}

// ─── config/verse_design_colors.php ─────────────────────────────────────────

$header = "<?php\n\n// Fichier GÉNÉRÉ par scripts/generate-verse-colors.php — ne pas modifier à la main.\n"
    . "// Couleurs des designs du verset du jour (docs/fonctionnalites/designs-du-verset.md).\n\n";
$data = [
    'palette' => $scheme,
    'tones'   => [
        'primary'          => C::toHex($tones['primary']),
        'ink_light'        => C::toHex($tones['inkLight']),
        'ink_dark'         => C::toHex($tones['inkDark']),
        'accents_on_dark'  => array_map([C::class, 'toHex'], $tones['accentsOnDark']),
        'accents_on_light' => array_map([C::class, 'toHex'], $tones['accentsOnLight']),
    ],
    'designs' => $designs,
];
file_put_contents($root . '/config/verse_design_colors.php', $header . 'return ' . exportArray($data) . ";\n");

// ─── resources/css/verse-designs.css ────────────────────────────────────────

$css = "/*\n * Fichier GÉNÉRÉ par scripts/generate-verse-colors.php — ne pas modifier à la main.\n"
    . " * Fonds des designs du verset du jour (docs/fonctionnalites/designs-du-verset.md).\n"
    . " * Structure : .verse-design.verse-design--{id} > .verse-design__bg (dégradé ; ::before halos et\n"
    . " * formes floutés ; ::after motif, grain et voile). --verse-long : plus grand côté (resources/js/verse-style.js).\n */\n";
foreach ($designs as $id => $d) {
    $layers = C::decodeLayers($d['layers']);
    $base = [];
    $soft = [];
    $top = [];
    foreach ($layers as $l) {
        switch ($l['type']) {
            case 'linear':
                $base[] = cssLinear($l);
                break;
            case 'halo':
                $soft[] = cssHalo($l);
                break;
            case 'blob':
                $soft[] = cssBlob($l);
                break;
            case 'pattern':
                $top[] = cssPattern($l);
                break;
            case 'grain':
                $top[] = cssGrain($l['opacity']);
                break;
        }
    }
    if ($d['scrim']) {
        $c = C::hex($d['scrim']['color']);
        $o = $d['scrim']['opacity'];
        array_unshift($top, sprintf('linear-gradient(to bottom, %s, %s 3.5%%, %s 96.5%%, %s)', rgba($c, $o * 0.6), rgba($c, $o), rgba($c, $o), rgba($c, $o * 0.6)));
    }
    // Les couches CSS s'empilent de haut en bas : la dernière couche Flutter vient en premier.
    $soft = array_reverse($soft);
    $topCss = $d['scrim'] ? array_merge([$top[0]], array_reverse(array_slice($top, 1))) : array_reverse($top);

    $ink = $d['ink'];
    $accent = $d['accent'];
    $css .= "\n/* {$id} : {$d['tone']}, contraste texte {$d['ink_contrast']}:1, référence {$d['accent_contrast']}:1 */\n";
    $css .= ".verse-design--{$id} { --verse-ink: {$ink}; --verse-accent: {$accent}; --verse-decoration: " . rgba(C::hex($accent), 0.9) . '; --verse-chip: ' . rgba(C::hex($ink), 0.12) . '; --verse-chip-hover: ' . rgba(C::hex($ink), 0.2) . '; color-scheme: ' . $d['tone'] . "; }\n";
    $css .= ".verse-design--{$id} .verse-design__bg { background: " . implode(', ', array_reverse($base)) . "; }\n";
    if ($soft) {
        $css .= ".verse-design--{$id} .verse-design__bg::before { background: " . implode(', ', $soft) . "; }\n";
    }
    if ($topCss) {
        $css .= ".verse-design--{$id} .verse-design__bg::after { background: " . implode(', ', $topCss) . "; }\n";
    }
    if ($d['ornament'] !== 'none') {
        $css .= ".verse-design--{$id} .verse-design__{$d['ornament']} { display: block; }\n";
    }
}
file_put_contents($root . '/resources/css/verse-designs.css', $css);

// ─── Compte rendu ───────────────────────────────────────────────────────────

echo "Palette : " . json_encode($scheme) . "\n";
echo 'Bleu profond ' . $data['tones']['primary'] . ' · encre claire ' . $data['tones']['ink_light'] . ' · encre foncée ' . $data['tones']['ink_dark'] . "\n";
echo 'Accents fond sombre ' . implode(' ', $data['tones']['accents_on_dark']) . ' · fond clair ' . implode(' ', $data['tones']['accents_on_light']) . "\n\n";
foreach ($designs as $id => $d) {
    printf("%-11s %-5s encre %s (%5.2f)  accent %s (%5.2f)  voile %s\n", $id, $d['tone'], $d['ink'], $d['ink_contrast'], $d['accent'], $d['accent_contrast'],
        $d['scrim'] ? $d['scrim']['color'] . ' ' . $d['scrim']['opacity'] : '—');
}
if ($problems) {
    echo "\nÀ vérifier :\n - " . implode("\n - ", $problems) . "\n";
    exit(2);
}
echo "\nÉcrit : config/verse_design_colors.php, resources/css/verse-designs.css\n";

// ─── Outils CSS ─────────────────────────────────────────────────────────────

function rgba(array $c, float $a): string
{
    [$r, $g, $b] = C::rgb255($c);
    $a = round(max(0, min(1, $a)), 3);

    return $a >= 1 ? sprintf('rgb(%d %d %d)', $r, $g, $b) : sprintf('rgb(%d %d %d / %s)', $r, $g, $b, $a);
}

function pct(float $v): string
{
    return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . '%';
}

function cssLinear(array $l): string
{
    if (count($l['colors']) === 1) {
        return rgba($l['colors'][0], 1);
    }
    $dir = ['0,-1|0,1' => 'to bottom', '-1,-1|1,1' => 'to bottom right', '1,-1|-1,1' => 'to bottom left', '-1,1|1,-1' => 'to top right'];
    $key = implode(',', $l['begin']) . '|' . implode(',', $l['end']);
    $stops = C::stops($l);
    $parts = [];
    foreach ($l['colors'] as $i => $c) {
        $parts[] = rgba($c, 1) . ' ' . pct($stops[$i] * 100);
    }

    return 'linear-gradient(' . ($dir[$key] ?? 'to bottom right') . ', ' . implode(', ', $parts) . ')';
}

function cssAt(array $align): string
{
    return pct(($align[0] + 1) / 2 * 100) . ' ' . pct(($align[1] + 1) / 2 * 100);
}

function cssHalo(array $l): string
{
    $c = $l['color'];
    $o = $l['opacity'];

    return sprintf('radial-gradient(circle calc(%s * var(--verse-long)) at %s, %s 0%%, %s 45%%, %s 100%%)',
        $l['radius'], cssAt($l['center']), rgba($c, $o), rgba($c, $o * 0.45), rgba($c, 0));
}

/** Forme organique : disque au bord adouci (le flou de ::before fait le reste). */
function cssBlob(array $l): string
{
    $c = $l['color'];
    $o = $l['opacity'];

    return sprintf('radial-gradient(circle calc(%s * var(--verse-long)) at %s, %s 0%%, %s 82%%, %s 100%%)',
        $l['radius'], cssAt($l['center']), rgba($c, $o), rgba($c, $o), rgba($c, 0));
}

function cssPattern(array $l): string
{
    $c = $l['color'];
    $o = $l['opacity'];
    $sp = $l['spacing'];
    switch ($l['kind']) {
        case 'dots':
            return sprintf('radial-gradient(circle 1.6px, %s 1.1px, transparent 1.6px) 0 0 / %dpx %dpx', rgba($c, $o), $sp, $sp);
        case 'stars':
            return svgUri(starsSvg($c, $o, $sp)) . ' 0 0 / 180px 180px';
        case 'diamonds':
            $p = round($sp * 1.6 / M_SQRT2, 2);
            $line = sprintf('transparent 0 %spx, %s %spx %spx', $p - 1, rgba($c, $o), $p - 1, $p);

            return "repeating-linear-gradient(45deg, {$line}), repeating-linear-gradient(-45deg, {$line})";
        case 'arcs':
            $p = round($sp * 1.4, 2);

            return sprintf('repeating-radial-gradient(circle at 100%% 100%%, transparent 0 %spx, %s %spx %spx)', $p - 1, rgba($c, $o), $p - 1, $p);
        case 'lines':
            return sprintf('repeating-linear-gradient(to bottom, transparent 0 %spx, %s %spx %spx)', $sp - 1, rgba($c, $o), $sp - 1, $sp);
    }

    return 'none';
}

function starsSvg(array $c, float $o, float $sp): string
{
    $rnd = C::rng(7);
    $hex = C::toHex($c);
    $count = (int) round(180 * 180 / ($sp * $sp * 4));
    $dots = '';
    for ($i = 0; $i < $count; $i++) {
        $dots .= sprintf("<circle cx='%.1f' cy='%.1f' r='%.2f' fill-opacity='%.2f'/>", $rnd() * 180, $rnd() * 180, 0.5 + $rnd() * 1.3, $o * (0.4 + $rnd() * 0.6));
    }

    return "<svg xmlns='http://www.w3.org/2000/svg' width='180' height='180'><g fill='{$hex}'>{$dots}</g></svg>";
}

function cssGrain(float $o): string
{
    $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='160' height='160'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='2' stitchTiles='stitch'/>"
        . "<feColorMatrix values='0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 1.4 -0.2'/></filter><rect width='100%' height='100%' filter='url(#n)' opacity='" . round($o * 2, 3) . "'/></svg>";

    return svgUri($svg) . ' 0 0 / 160px 160px';
}

function svgUri(string $svg): string
{
    return 'url("data:image/svg+xml,' . str_replace(['%', '#', '<', '>', '"'], ['%25', '%23', '%3C', '%3E', "'"], $svg) . '")';
}

function exportArray(mixed $v, int $depth = 0): string
{
    $pad = str_repeat('    ', $depth + 1);
    if (is_array($v)) {
        if ($v === []) {
            return '[]';
        }
        $list = array_is_list($v);
        $flat = $list && !array_filter($v, 'is_array');
        $items = [];
        foreach ($v as $k => $item) {
            $items[] = ($list ? '' : var_export($k, true) . ' => ') . exportArray($item, $depth + 1);
        }

        return $flat ? '[' . implode(', ', $items) . ']' : "[\n" . $pad . implode(",\n" . $pad, $items) . ",\n" . str_repeat('    ', $depth) . ']';
    }
    if (is_float($v)) {
        return rtrim(rtrim(sprintf('%.4F', $v), '0'), '.') ?: '0';
    }

    return var_export($v, true);
}
