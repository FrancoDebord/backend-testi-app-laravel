<?php

namespace App\Console\Commands;

use App\Support\Countries;
use Illuminate\Console\Command;

/**
 * Écrit la liste des pays (nom, code ISO, indicatif) pour l'application mobile, à partir de
 * App\Support\Countries : mêmes noms que le site. Voir docs/fonctionnalites/telephone.md
 *
 *   php artisan countries:export-dart ../testi_app/lib/core/data/countries.dart
 */
class ExportCountriesDart extends Command
{
    protected $signature = 'countries:export-dart {path : fichier Dart à écrire (lib/core/data/countries.dart de l\'application)}';

    protected $description = 'Génère la liste des pays et indicatifs de l\'application mobile depuis celle du site';

    public function handle(): int
    {
        $lines = [];
        foreach (Countries::all() as $name) {
            $code    = Countries::code($name);
            $lines[] = sprintf("  Country(%s, '%s', '%s'),", var_export($name, true), $code, Countries::dialCode($code));
        }
        $escaped = implode("\n", $lines);

        $dart = <<<DART
// Pays proposés à l'inscription et dans le profil, en français et en toutes lettres,
// avec leur code ISO et leur indicatif téléphonique.
//
// GÉNÉRÉ depuis le serveur (App\\Support\\Countries, TestiApp-Backend-Laravel) : mêmes noms
// que le site, pour des données homogènes. Ne pas modifier à la main ; régénérer en cas d'ajout.
// Backend : docs/fonctionnalites/telephone.md

/// Un pays : nom affiché (et enregistré), code ISO 3166-1 (« bj »), indicatif (« 229 »).
class Country {
  const Country(this.name, this.code, this.dial);

  final String name;
  final String code;
  final String dial;

  /// Drapeau (émoji formé des deux lettres du code ISO).
  String get flag => String.fromCharCodes(
      code.toUpperCase().codeUnits.map((c) => 0x1F1E6 + c - 0x41));

  /// « +229 »
  String get dialLabel => '+\$dial';
}

/// Ordre alphabétique français, sans tenir compte des accents (comme le site).
const List<Country> kCountries = [
$escaped
];

Country? countryByName(String? name) {
  if (name == null || name.trim().isEmpty) return null;
  for (final c in kCountries) {
    if (c.name == name.trim()) return c;
  }
  return null;
}

Country? countryByCode(String? code) {
  if (code == null || code.trim().isEmpty) return null;
  final lower = code.trim().toLowerCase();
  for (final c in kCountries) {
    if (c.code == lower) return c;
  }
  return null;
}

/// Minuscules sans accents, pour la recherche (« cote » trouve « Côte d'Ivoire »).
String normalizeForSearch(String s) {
  const from = 'àâäáãåçéèêëíìîïñóòôöõúùûüýÿœæ';
  const to   = 'aaaaaaceeeeiiiinooooouuuuyyoa';
  final lower = s.toLowerCase().trim();
  final out = StringBuffer();
  for (final ch in lower.split('')) {
    final i = from.indexOf(ch);
    out.write(i >= 0 ? to[i] : ch);
  }
  return out.toString();
}

/// Pays correspondant à la recherche (nom ou indicatif) : ceux qui commencent
/// par la saisie d'abord, puis ceux qui la contiennent.
List<Country> searchCountries(String query) {
  final q = normalizeForSearch(query).replaceFirst('+', '');
  if (q.isEmpty) return kCountries;
  final starts = <Country>[];
  final contains = <Country>[];
  for (final c in kCountries) {
    final n = normalizeForSearch(c.name);
    if (n.startsWith(q) || c.dial.startsWith(q)) {
      starts.add(c);
    } else if (n.contains(q) || c.dial.contains(q)) {
      contains.add(c);
    }
  }
  return [...starts, ...contains];
}

DART;

        file_put_contents($this->argument('path'), $dart);
        $this->info(count($lines) . ' pays écrits dans ' . $this->argument('path'));

        return self::SUCCESS;
    }
}
