// lib/features/daily_verse/design/verse_design.dart
//
// « Designs » du verset du jour : un fond (couches peintes en code), une mise
// en page et les couleurs du texte, calculées pour garantir un contraste
// minimal de 4,5:1 (WCAG AA).
//
// Toutes les couleurs viennent du ColorScheme de l'application (primary,
// secondary, tertiary, surface…) ou de leurs teintes plus claires / plus
// foncées ([VerseTones]). Chaque design gère sa version claire ou sombre selon
// la luminosité du thème.
//
// Ajouter un design = ajouter une entrée dans [kVerseDesigns].

import 'dart:math' as math;
import 'dart:ui' as ui;

import 'package:flutter/material.dart';

// ── Contraste (WCAG 2.x) ─────────────────────────────────────────────────────

/// Rapport de contraste WCAG entre deux couleurs opaques (1 à 21).
double contrastRatio(Color a, Color b) {
  final la = a.computeLuminance();
  final lb = b.computeLuminance();
  final hi = math.max(la, lb);
  final lo = math.min(la, lb);
  return (hi + 0.05) / (lo + 0.05);
}

/// [top] posée sur [bottom] avec l'opacité [opacity] (composition « over »).
Color blendOver(Color bottom, Color top, double opacity) {
  final a = opacity.clamp(0.0, 1.0);
  return Color.from(
    alpha: 1,
    red: bottom.r + (top.r - bottom.r) * a,
    green: bottom.g + (top.g - bottom.g) * a,
    blue: bottom.b + (top.b - bottom.b) * a,
  );
}

/// Contraste minimal visé (texte du verset, référence, libellés).
const double kMinVerseContrast = 4.5;

// ── Palette dérivée du thème ─────────────────────────────────────────────────

/// Couleurs de travail des designs, dérivées du [ColorScheme] :
/// rôles du thème et teintes plus claires / plus foncées de la marque.
class VerseTones {
  VerseTones._(this.scheme);

  factory VerseTones.of(ColorScheme scheme) => VerseTones._(scheme);

  final ColorScheme scheme;

  bool get isDark => scheme.brightness == Brightness.dark;

  static const _white = Color(0xFFFFFFFF);
  static const _black = Color(0xFF000000);

  /// Version « profonde » d'une couleur de marque (luminosité ≤ [max]), quel
  /// que soit le thème : le bleu Krea reste un bleu nuit en mode sombre.
  static Color deep(Color c, [double max = 0.36]) {
    final hsl = HSLColor.fromColor(c);
    return hsl.lightness <= max ? c : hsl.withLightness(max).toColor();
  }

  /// Plus foncé (vers le noir) de [f] (0 à 1).
  static Color shade(Color c, double f) => Color.lerp(c, _black, f)!;

  /// Plus clair (vers le blanc) de [f] (0 à 1).
  static Color tint(Color c, double f) => Color.lerp(c, _white, f)!;

  /// Fond « papier » : clair en thème clair, sombre en thème sombre.
  Color paper(Color c, double f) => isDark ? shade(deep(c), f) : tint(c, f);

  Color get primary => deep(scheme.primary, 0.34);
  Color get secondary => isDark ? deep(scheme.secondary, 0.5) : scheme.secondary;
  Color get tertiary => isDark ? deep(scheme.tertiary, 0.55) : scheme.tertiary;
  Color get surface => scheme.surface;

  /// Encre claire (texte sur fond sombre).
  Color get inkLight => isDark ? scheme.onSurface : scheme.surface;

  /// Encre foncée (texte sur fond clair) : bleu nuit de la marque.
  Color get inkDark => shade(primary, 0.55);

  /// Accent lumineux (référence sur fond sombre) : jaune / orange clairs.
  List<Color> get accentsOnDark => [
        tint(scheme.tertiary, 0.15),
        tint(scheme.secondary, 0.35),
        tint(scheme.primary, 0.6),
      ];

  /// Accent profond (référence sur fond clair) : bleu, orange brûlé.
  List<Color> get accentsOnLight => [
        primary,
        shade(scheme.secondary, 0.42),
        shade(scheme.tertiary, 0.62),
      ];
}

// ── Couches du fond ──────────────────────────────────────────────────────────

/// Une couche du fond. [paint] la dessine ; [sample] donne les couleurs
/// possibles en un point (plusieurs quand la couche est une texture), pour le
/// calcul du contraste.
sealed class VerseLayer {
  const VerseLayer();

  void paint(Canvas canvas, Size size, ui.Image? grain);

  Iterable<Color> sample(Color under, Offset p, Size size);
}

/// Dégradé linéaire couvrant tout le fond (opaque).
class LinearFill extends VerseLayer {
  const LinearFill(this.colors,
      {this.stops, this.begin = Alignment.topLeft, this.end = Alignment.bottomRight});

  final List<Color> colors;
  final List<double>? stops;
  final Alignment begin;
  final Alignment end;

  List<double> get _stops =>
      stops ?? [for (var i = 0; i < colors.length; i++) i / (colors.length - 1)];

  @override
  void paint(Canvas canvas, Size size, ui.Image? grain) {
    final rect = Offset.zero & size;
    if (colors.length == 1) {
      canvas.drawRect(rect, Paint()..color = colors.first);
      return;
    }
    canvas.drawRect(
      rect,
      Paint()
        ..shader = ui.Gradient.linear(
            begin.withinRect(rect), end.withinRect(rect), colors, _stops),
    );
  }

  @override
  Iterable<Color> sample(Color under, Offset p, Size size) {
    if (colors.length == 1) return [colors.first];
    final rect = Offset.zero & size;
    final a = begin.withinRect(rect);
    final b = end.withinRect(rect);
    final ab = b - a;
    final len2 = ab.dx * ab.dx + ab.dy * ab.dy;
    final t = len2 == 0
        ? 0.0
        : (((p - a).dx * ab.dx + (p - a).dy * ab.dy) / len2).clamp(0.0, 1.0);
    final s = _stops;
    for (var i = 0; i < s.length - 1; i++) {
      if (t <= s[i + 1]) {
        final span = s[i + 1] - s[i];
        final local = span == 0 ? 0.0 : (t - s[i]) / span;
        return [Color.lerp(colors[i], colors[i + 1], local)!];
      }
    }
    return [colors.last];
  }
}

/// Halo lumineux flou (lumière diffuse) : dégradé radial vers la transparence.
/// [radius] : fraction du plus grand côté.
class Halo extends VerseLayer {
  const Halo(this.center, this.radius, this.color, this.opacity);

  final Alignment center;
  final double radius;
  final Color color;
  final double opacity;

  static const _stops = [0.0, 0.45, 1.0];
  static const _alphas = [1.0, 0.45, 0.0];

  double _r(Size s) => radius * s.longestSide;

  @override
  void paint(Canvas canvas, Size size, ui.Image? grain) {
    final rect = Offset.zero & size;
    final c = center.withinRect(rect);
    canvas.drawRect(
      rect,
      Paint()
        ..shader = ui.Gradient.radial(
          c,
          _r(size),
          [for (final a in _alphas) color.withValues(alpha: opacity * a)],
          _stops,
        ),
    );
  }

  @override
  Iterable<Color> sample(Color under, Offset p, Size size) {
    final d = (p - center.withinRect(Offset.zero & size)).distance / _r(size);
    if (d >= 1) return [under];
    final a = d <= _stops[1]
        ? 1 - (1 - _alphas[1]) * (d / _stops[1])
        : _alphas[1] * (1 - (d - _stops[1]) / (1 - _stops[1]));
    return [blendOver(under, color, opacity * a)];
  }
}

/// Forme organique (contour ondulé lissé), déterministe ([seed]).
/// [radius] : fraction du plus grand côté.
class Blob extends VerseLayer {
  const Blob(this.center, this.radius, this.color, this.opacity,
      {this.seed = 1, this.lobes = 6, this.wobble = 0.22});

  final Alignment center;
  final double radius;
  final Color color;
  final double opacity;
  final int seed;
  final int lobes;
  final double wobble;

  Path path(Size size) {
    final c = center.withinRect(Offset.zero & size);
    final r = radius * size.longestSide;
    final rnd = math.Random(seed);
    final n = lobes * 2;
    final pts = <Offset>[
      for (var i = 0; i < n; i++)
        () {
          final ang = i / n * math.pi * 2;
          final k = 1 + (rnd.nextDouble() * 2 - 1) * wobble;
          return c + Offset(math.cos(ang), math.sin(ang)) * (r * k);
        }(),
    ];
    // Catmull-Rom → courbes de Bézier cubiques (contour lisse et fermé).
    final path = Path()..moveTo(pts[0].dx, pts[0].dy);
    for (var i = 0; i < n; i++) {
      final p0 = pts[(i - 1 + n) % n];
      final p1 = pts[i];
      final p2 = pts[(i + 1) % n];
      final p3 = pts[(i + 2) % n];
      final c1 = p1 + (p2 - p0) / 6;
      final c2 = p2 - (p3 - p1) / 6;
      path.cubicTo(c1.dx, c1.dy, c2.dx, c2.dy, p2.dx, p2.dy);
    }
    return path..close();
  }

  @override
  void paint(Canvas canvas, Size size, ui.Image? grain) {
    canvas.drawPath(path(size), Paint()..color = color.withValues(alpha: opacity));
  }

  @override
  Iterable<Color> sample(Color under, Offset p, Size size) =>
      path(size).contains(p) ? [blendOver(under, color, opacity)] : [under];
}

enum VersePatternKind { dots, stars, diamonds, arcs, lines }

/// Motif géométrique discret.
class PatternLayer extends VerseLayer {
  const PatternLayer(this.kind, this.color, this.opacity, {this.spacing = 22});

  final VersePatternKind kind;
  final Color color;
  final double opacity;

  /// Écart du motif, en pixels logiques.
  final double spacing;

  @override
  void paint(Canvas canvas, Size size, ui.Image? grain) {
    final paint = Paint()
      ..color = color.withValues(alpha: opacity)
      ..strokeWidth = 1
      ..style = PaintingStyle.stroke;
    final fill = Paint()..color = color.withValues(alpha: opacity);
    switch (kind) {
      case VersePatternKind.dots:
        for (var y = spacing / 2; y < size.height; y += spacing) {
          for (var x = spacing / 2; x < size.width; x += spacing) {
            canvas.drawCircle(Offset(x, y), 1.1, fill);
          }
        }
      case VersePatternKind.stars:
        final rnd = math.Random(7);
        final count = (size.width * size.height / (spacing * spacing * 4)).round();
        for (var i = 0; i < count; i++) {
          final o = Offset(rnd.nextDouble() * size.width, rnd.nextDouble() * size.height);
          final r = 0.5 + rnd.nextDouble() * 1.3;
          canvas.drawCircle(
              o, r, Paint()..color = color.withValues(alpha: opacity * (0.4 + rnd.nextDouble() * 0.6)));
        }
      case VersePatternKind.diamonds:
        final path = Path();
        for (var x = -size.height; x < size.width + size.height; x += spacing * 1.6) {
          path
            ..moveTo(x, 0)
            ..lineTo(x + size.height, size.height)
            ..moveTo(x + size.height, 0)
            ..lineTo(x, size.height);
        }
        canvas.drawPath(path, paint);
      case VersePatternKind.arcs:
        final origin = Offset(size.width, size.height);
        for (var r = spacing * 2; r < size.longestSide * 1.5; r += spacing * 1.4) {
          canvas.drawCircle(origin, r, paint);
        }
      case VersePatternKind.lines:
        for (var y = spacing; y < size.height; y += spacing) {
          canvas.drawLine(Offset(0, y), Offset(size.width, y), paint);
        }
    }
  }

  /// Sans motif, sur un trait, ou au croisement de deux traits (losanges,
  /// arcs) : l'opacité s'y additionne.
  @override
  Iterable<Color> sample(Color under, Offset p, Size size) {
    final once = blendOver(under, color, opacity);
    return [
      under,
      once,
      if (kind == VersePatternKind.diamonds || kind == VersePatternKind.arcs)
        blendOver(once, color, opacity),
    ];
  }
}

/// Grain fin (texture de bruit générée une fois, répétée).
class Grain extends VerseLayer {
  const Grain([this.opacity = 0.05]);

  final double opacity;

  @override
  void paint(Canvas canvas, Size size, ui.Image? grain) {
    if (grain == null) return;
    canvas.drawRect(
      Offset.zero & size,
      Paint()
        ..color = Color.fromRGBO(0, 0, 0, opacity)
        ..shader = ImageShader(grain, TileMode.repeated, TileMode.repeated,
            Matrix4.identity().storage),
    );
  }

  /// Le bruit va du noir au blanc : on retient les deux extrêmes.
  @override
  Iterable<Color> sample(Color under, Offset p, Size size) => [
        blendOver(under, const Color(0xFF000000), opacity),
        blendOver(under, const Color(0xFFFFFFFF), opacity),
      ];
}

/// Voile dégradé posé derrière le texte quand le fond seul n'assure pas le
/// contraste (opacité pleine sur la zone de texte, adoucie aux bords).
class Scrim extends VerseLayer {
  const Scrim(this.color, this.opacity);

  final Color color;
  final double opacity;

  @override
  void paint(Canvas canvas, Size size, ui.Image? grain) {
    final rect = Offset.zero & size;
    canvas.drawRect(
      rect,
      Paint()
        ..shader = ui.Gradient.linear(
          rect.topCenter,
          rect.bottomCenter,
          [
            color.withValues(alpha: opacity * 0.6),
            color.withValues(alpha: opacity),
            color.withValues(alpha: opacity),
            color.withValues(alpha: opacity * 0.6),
          ],
          const [0, kVerseTextZoneInset, 1 - kVerseTextZoneInset, 1],
        ),
    );
  }

  @override
  Iterable<Color> sample(Color under, Offset p, Size size) =>
      [blendOver(under, color, opacity)];
}

/// Marge (fraction de chaque côté) hors de la zone où du texte peut être posé.
const double kVerseTextZoneInset = 0.035;

// ── Mise en page ─────────────────────────────────────────────────────────────

enum VerseAccent {
  /// Rien.
  none,

  /// Court filet au-dessus du verset.
  line,

  /// Grand guillemet décoratif, très discret.
  quote,
}

// ── Design ───────────────────────────────────────────────────────────────────

enum VerseMood { deep, light, dawn, night, minimal, warm }

extension VerseMoodLabel on VerseMood {
  String get label => switch (this) {
        VerseMood.deep => 'Sombre profond',
        VerseMood.light => 'Clair et lumineux',
        VerseMood.dawn => 'Aube',
        VerseMood.night => 'Nuit',
        VerseMood.minimal => 'Sobre',
        VerseMood.warm => 'Chaleureux',
      };
}

/// Un design de verset : fond + mise en page. Les couleurs du texte sont
/// calculées par [resolve] (contraste garanti).
class VerseDesign {
  const VerseDesign({
    required this.id,
    required this.name,
    required this.mood,
    required this.layers,
    this.accent = VerseAccent.line,
  });

  /// Identifiant stable (préférences enregistrées).
  final String id;
  final String name;
  final VerseMood mood;

  /// Couches du fond, de la plus basse à la plus haute.
  final List<VerseLayer> Function(VerseTones t) layers;
  final VerseAccent accent;

  /// Couleurs du texte et voile pour ce thème et cette taille (mis en cache).
  ResolvedVerseDesign resolve(ColorScheme scheme, Size size) =>
      ResolvedVerseDesign._cached(this, scheme, size);

  @override
  bool operator ==(Object other) => other is VerseDesign && other.id == id;

  @override
  int get hashCode => id.hashCode;
}

/// Design prêt à peindre : couches (voile compris), encre, accent, contraste.
class ResolvedVerseDesign {
  ResolvedVerseDesign._({
    required this.design,
    required this.layers,
    required this.ink,
    required this.accent,
    required this.lightInk,
    required this.scrimOpacity,
    required this.inkContrast,
    required this.accentContrast,
    required this.decoration,
  });

  final VerseDesign design;

  /// Couches à peindre (dont le voile éventuel en dernier).
  final List<VerseLayer> layers;

  /// Couleur du texte du verset et des libellés.
  final Color ink;

  /// Couleur de la référence (accent du thème si contraste suffisant).
  final Color accent;

  /// Encre claire (fond sombre) : barre d'état claire, etc.
  final bool lightInk;

  /// Opacité du voile ajouté (0 : aucun).
  final double scrimOpacity;

  /// Contraste minimal calculé sur la zone de texte.
  final double inkContrast;
  final double accentContrast;

  /// Couleur des éléments décoratifs (filet, guillemet, vignettes).
  final Color decoration;

  static final Map<String, ResolvedVerseDesign> _cache = {};

  factory ResolvedVerseDesign._cached(VerseDesign d, ColorScheme scheme, Size size) {
    // Les halos dépendent des proportions : clé arrondie à 8 px.
    final key = '${d.id}|${scheme.hashCode}|${(size.width / 8).round()}x${(size.height / 8).round()}';
    final hit = _cache[key];
    if (hit != null) return hit;
    if (_cache.length > 96) _cache.clear();
    return _cache[key] = _compute(d, VerseTones.of(scheme), size);
  }

  /// Couleurs possibles sous la zone de texte (grille de points × variantes).
  static List<Color> backgroundSamples(List<VerseLayer> layers, Size size, {int grid = 13}) {
    final out = <Color>[];
    final inset = kVerseTextZoneInset;
    final zone = Rect.fromLTRB(size.width * inset, size.height * inset,
        size.width * (1 - inset), size.height * (1 - inset));
    final points = <Offset>[
      for (var iy = 0; iy < grid; iy++)
        for (var ix = 0; ix < grid; ix++)
          Offset(
            zone.left + zone.width * ix / (grid - 1),
            zone.top + zone.height * iy / (grid - 1),
          ),
      // Point le plus lumineux (ou sombre) de chaque halo, ramené dans la zone.
      for (final l in layers)
        if (l is Halo)
          () {
            final c = l.center.withinRect(Offset.zero & size);
            return Offset(c.dx.clamp(zone.left, zone.right), c.dy.clamp(zone.top, zone.bottom));
          }(),
    ];
    for (final p in points) {
      {
        var colors = <Color>[const Color(0xFF808080)];
        for (final l in layers) {
          final next = <Color>{};
          for (final c in colors) {
            next.addAll(l.sample(c, p, size));
          }
          colors = next.toList();
        }
        out.addAll(colors);
      }
    }
    return out;
  }

  static double _minContrast(Color ink, List<Color> bg) =>
      bg.fold(21.0, (m, c) => math.min(m, contrastRatio(ink, c)));

  static ResolvedVerseDesign _compute(VerseDesign d, VerseTones t, Size size) {
    final base = d.layers(t);
    final bg = backgroundSamples(base, size);

    final light = _minContrast(t.inkLight, bg);
    final dark = _minContrast(t.inkDark, bg);
    final lightInk = light >= dark;
    final ink = lightInk ? t.inkLight : t.inkDark;

    // Marge de sécurité : le grain et l'anticrénelage varient d'un pixel à l'autre.
    const target = kMinVerseContrast + 0.15;
    var layers = base;
    var samples = bg;
    var scrim = 0.0;
    if (math.max(light, dark) < target) {
      final scrimColor = lightInk ? VerseTones.shade(t.primary, 0.7) : t.surface;
      for (scrim = 0.04; scrim <= 0.9; scrim += 0.02) {
        final s = [for (final c in bg) blendOver(c, scrimColor, scrim)];
        if (_minContrast(ink, s) >= target) {
          samples = s;
          break;
        }
      }
      layers = [...base, Scrim(scrimColor, scrim)];
      samples = [for (final c in bg) blendOver(c, scrimColor, scrim)];
    }

    final inkContrast = _minContrast(ink, samples);
    var accent = ink;
    var accentContrast = inkContrast;
    for (final a in lightInk ? t.accentsOnDark : t.accentsOnLight) {
      final c = _minContrast(a, samples);
      if (c >= target) {
        accent = a;
        accentContrast = c;
        break;
      }
    }

    return ResolvedVerseDesign._(
      design: d,
      layers: layers,
      ink: ink,
      accent: accent,
      lightInk: lightInk,
      scrimOpacity: scrim,
      inkContrast: inkContrast,
      accentContrast: accentContrast,
      decoration: accent.withValues(alpha: 0.9),
    );
  }
}

// ── Les designs ──────────────────────────────────────────────────────────────

/// Liste des designs, dans l'ordre du sélecteur.
final List<VerseDesign> kVerseDesigns = [
  VerseDesign(
    id: 'aube',
    name: 'Aube',
    mood: VerseMood.dawn,
    accent: VerseAccent.line,
    layers: (t) => [
      LinearFill([t.paper(t.tertiary, 0.78), t.paper(t.secondary, 0.86), t.paper(t.primary, 0.94)],
          begin: Alignment.topRight, end: Alignment.bottomLeft),
      Halo(const Alignment(0.9, -0.95), 0.75, t.paper(t.tertiary, 0.35), 0.55),
      Blob(const Alignment(-1.1, 1.0), 0.42, t.paper(t.secondary, 0.6), 0.22, seed: 3),
      const Grain(0.04),
    ],
  ),
  VerseDesign(
    id: 'nuit',
    name: 'Nuit étoilée',
    mood: VerseMood.night,
    accent: VerseAccent.none,
    layers: (t) => [
      LinearFill([VerseTones.shade(t.primary, 0.45), VerseTones.shade(t.primary, 0.78)],
          begin: Alignment.topCenter, end: Alignment.bottomCenter),
      Halo(const Alignment(-0.9, 1.0), 0.8, VerseTones.tint(t.primary, 0.25), 0.25),
      PatternLayer(VersePatternKind.stars, VerseTones.tint(t.tertiary, 0.5), 0.55, spacing: 18),
      const Grain(0.05),
    ],
  ),
  VerseDesign(
    id: 'ocean',
    name: 'Océan profond',
    mood: VerseMood.deep,
    accent: VerseAccent.quote,
    layers: (t) => [
      LinearFill([t.primary, VerseTones.shade(t.primary, 0.5)]),
      Halo(const Alignment(-1.0, -1.0), 0.7, VerseTones.tint(t.primary, 0.3), 0.35),
      Blob(const Alignment(1.0, 1.05), 0.5, VerseTones.shade(t.primary, 0.7), 0.35, seed: 5),
      Blob(const Alignment(-0.7, 1.15), 0.35, VerseTones.tint(t.primary, 0.2), 0.12, seed: 9),
      const Grain(0.05),
    ],
  ),
  VerseDesign(
    id: 'lumiere',
    name: 'Lumière douce',
    mood: VerseMood.light,
    accent: VerseAccent.line,
    layers: (t) => [
      LinearFill([t.paper(t.primary, 0.97), t.paper(t.primary, 0.9)],
          begin: Alignment.topCenter, end: Alignment.bottomCenter),
      Halo(const Alignment(-0.95, -0.95), 0.85, t.paper(t.tertiary, 0.45), 0.5),
      const Grain(0.035),
    ],
  ),
  VerseDesign(
    id: 'braise',
    name: 'Braise',
    mood: VerseMood.warm,
    accent: VerseAccent.quote,
    layers: (t) => [
      LinearFill([
        VerseTones.shade(VerseTones.deep(t.scheme.secondary, 0.4), 0.2),
        VerseTones.shade(VerseTones.deep(t.scheme.secondary, 0.4), 0.55),
        VerseTones.shade(t.primary, 0.55),
      ], stops: const [0, 0.55, 1]),
      Halo(const Alignment(0.95, 0.95), 0.75, t.tertiary, 0.28),
      const Grain(0.05),
    ],
  ),
  VerseDesign(
    id: 'minimal',
    name: 'Minimal',
    mood: VerseMood.minimal,
    accent: VerseAccent.line,
    layers: (t) => [
      LinearFill([t.paper(t.primary, 0.975)]),
      const Grain(0.03),
    ],
  ),
  VerseDesign(
    id: 'vitrail',
    name: 'Vitrail',
    mood: VerseMood.deep,
    accent: VerseAccent.line,
    layers: (t) => [
      LinearFill([VerseTones.shade(t.primary, 0.15), VerseTones.shade(t.primary, 0.6)],
          begin: Alignment.topCenter, end: Alignment.bottomCenter),
      PatternLayer(VersePatternKind.diamonds, VerseTones.tint(t.primary, 0.45), 0.1, spacing: 26),
      Halo(const Alignment(0, -1.05), 0.6, t.tertiary, 0.18),
    ],
  ),
  VerseDesign(
    id: 'crepuscule',
    name: 'Crépuscule',
    mood: VerseMood.dawn,
    accent: VerseAccent.none,
    layers: (t) => [
      LinearFill([
        VerseTones.shade(t.primary, 0.55),
        VerseTones.shade(Color.lerp(t.primary, t.secondary, 0.45)!, 0.35),
        VerseTones.shade(t.secondary, 0.3),
      ], stops: const [0, 0.6, 1], begin: Alignment.topCenter, end: Alignment.bottomCenter),
      Halo(const Alignment(0, 1.2), 0.65, t.tertiary, 0.3),
      const Grain(0.05),
    ],
  ),
  VerseDesign(
    id: 'matin',
    name: 'Matin clair',
    mood: VerseMood.light,
    accent: VerseAccent.quote,
    layers: (t) => [
      LinearFill([t.paper(t.primary, 0.88), t.paper(t.primary, 0.96)]),
      Blob(const Alignment(1.0, -1.0), 0.45, t.paper(t.primary, 0.72), 0.45, seed: 2),
      Blob(const Alignment(-1.05, 0.9), 0.3, t.paper(t.tertiary, 0.6), 0.35, seed: 4),
      const Grain(0.035),
    ],
  ),
  VerseDesign(
    id: 'or',
    name: 'Or doux',
    mood: VerseMood.warm,
    accent: VerseAccent.line,
    layers: (t) => [
      LinearFill([t.paper(t.tertiary, 0.62), t.paper(t.tertiary, 0.86)],
          begin: Alignment.bottomLeft, end: Alignment.topRight),
      PatternLayer(VersePatternKind.arcs, VerseTones.shade(t.tertiary, 0.35), 0.1, spacing: 20),
      Halo(const Alignment(-0.9, -1.0), 0.7, t.paper(t.tertiary, 0.92), 0.6),
      const Grain(0.035),
    ],
  ),
  VerseDesign(
    id: 'encre',
    name: 'Encre',
    mood: VerseMood.deep,
    accent: VerseAccent.quote,
    layers: (t) => [
      LinearFill([VerseTones.shade(t.primary, 0.78), VerseTones.shade(t.primary, 0.9)]),
      Halo(const Alignment(1.0, -1.0), 0.7, VerseTones.tint(t.primary, 0.2), 0.22),
      const Grain(0.07),
    ],
  ),
  VerseDesign(
    id: 'ardoise',
    name: 'Ardoise',
    mood: VerseMood.minimal,
    accent: VerseAccent.line,
    layers: (t) => [
      LinearFill([
        VerseTones.shade(Color.lerp(t.surface, t.primary, 0.35)!, 0.6),
        VerseTones.shade(Color.lerp(t.surface, t.primary, 0.35)!, 0.72),
      ], begin: Alignment.topCenter, end: Alignment.bottomCenter),
      PatternLayer(VersePatternKind.lines, VerseTones.tint(t.primary, 0.5), 0.07, spacing: 28),
      const Grain(0.04),
    ],
  ),
];

/// Design par identifiant (null si inconnu, ex. design retiré).
VerseDesign? verseDesignById(String? id) =>
    id == null ? null : kVerseDesigns.where((d) => d.id == id).firstOrNull;

/// Design du jour : déterministe à partir de la date du calendrier (même design
/// pour tous le même jour, un autre le lendemain). La permutation (× 5) évite
/// deux ambiances voisines deux jours de suite.
VerseDesign dailyVerseDesign(DateTime day) {
  final days = DateTime.utc(day.year, day.month, day.day)
      .difference(DateTime.utc(2024, 1, 1))
      .inDays;
  final n = kVerseDesigns.length;
  final step = _coprimeStep(n);
  return kVerseDesigns[((days * step) % n + n) % n];
}

int _coprimeStep(int n) {
  int gcd(int a, int b) => b == 0 ? a : gcd(b, a % b);
  for (final s in [5, 7, 3, 1]) {
    if (gcd(s, n) == 1) return s;
  }
  return 1;
}
