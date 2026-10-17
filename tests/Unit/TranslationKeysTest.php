<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * lang/fr et lang/en doivent avoir exactement les mêmes fichiers, les mêmes clés et les mêmes
 * paramètres (:name). Échoue en nommant chaque clé manquante. Voir docs/fonctionnalites/langues.md
 */
class TranslationKeysTest extends TestCase
{
    private const LOCALES = ['fr', 'en'];

    private static function langPath(string $locale = ''): string
    {
        return dirname(__DIR__, 2) . '/lang' . ($locale !== '' ? '/' . $locale : '');
    }

    /** @return list<string> noms des fichiers PHP d'une langue (sous-dossiers compris) */
    private static function files(string $locale): array
    {
        $root = self::langPath($locale);
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            }
        }
        sort($files);

        return $files;
    }

    /** @return array<string, string> clé pointée => texte */
    private static function flatten(array $lines, string $prefix = ''): array
    {
        $out = [];
        foreach ($lines as $key => $value) {
            $full = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value) && $value !== []) {
                $out += self::flatten($value, $full);
            } else {
                $out[$full] = is_array($value) ? '' : (string) $value;
            }
        }

        return $out;
    }

    /** @return list<string> paramètres « :nom » d'un texte, triés et dédoublonnés */
    private static function placeholders(string $text): array
    {
        preg_match_all('/(?<![\w:]):([a-z_][a-z0-9_]*)/i', $text, $m);
        $names = array_values(array_unique(array_map('strtolower', $m[1])));
        sort($names);

        return $names;
    }

    public function test_both_locales_have_the_same_files(): void
    {
        $fr = self::files('fr');
        $en = self::files('en');

        $this->assertNotEmpty($fr, 'lang/fr est vide.');
        $this->assertSame([], array_values(array_diff($fr, $en)), 'Fichiers présents dans lang/fr mais absents de lang/en.');
        $this->assertSame([], array_values(array_diff($en, $fr)), 'Fichiers présents dans lang/en mais absents de lang/fr.');
    }

    public function test_json_translation_files_have_the_same_keys(): void
    {
        $json = [];
        foreach (self::LOCALES as $locale) {
            $path = self::langPath() . "/{$locale}.json";
            $json[$locale] = is_file($path) ? array_keys(json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR)) : [];
        }
        $this->assertSame([], array_values(array_diff($json['fr'], $json['en'])), 'Clés de fr.json absentes de en.json.');
        $this->assertSame([], array_values(array_diff($json['en'], $json['fr'])), 'Clés de en.json absentes de fr.json.');
    }

    public static function files_provider(): array
    {
        $files = array_unique(array_merge(self::files('fr'), self::files('en')));

        return array_combine($files, array_map(fn ($f) => [$f], $files));
    }

    #[DataProvider('files_provider')]
    public function test_every_key_exists_in_both_locales_with_the_same_parameters(string $file): void
    {
        $lines = [];
        foreach (self::LOCALES as $locale) {
            $path = self::langPath($locale) . '/' . $file;
            $this->assertFileExists($path, "lang/{$locale}/{$file} manque.");
            $data = require $path;
            $this->assertIsArray($data, "lang/{$locale}/{$file} doit renvoyer un tableau.");
            $lines[$locale] = self::flatten($data);
        }

        $missingEn = array_keys(array_diff_key($lines['fr'], $lines['en']));
        $missingFr = array_keys(array_diff_key($lines['en'], $lines['fr']));
        $this->assertSame([], $missingEn, "Clés de lang/fr/{$file} absentes de lang/en/{$file}.");
        $this->assertSame([], $missingFr, "Clés de lang/en/{$file} absentes de lang/fr/{$file}.");

        $mismatch = [];
        foreach ($lines['fr'] as $key => $text) {
            if (self::placeholders($text) !== self::placeholders($lines['en'][$key])) {
                $mismatch[] = $key;
            }
        }
        $this->assertSame([], $mismatch, "Paramètres (:nom) différents entre fr et en dans {$file}.");

        $empty = array_keys(array_filter($lines['en'] + $lines['fr'], fn ($t, $k) => trim($t) === '' && !str_ends_with($k, 'attributes'), ARRAY_FILTER_USE_BOTH));
        $this->assertSame([], $empty, "Textes vides dans {$file}.");
    }
}
