<?php

namespace App\Console\Commands;

use App\Models\BibleBook;
use App\Models\BibleVerse;
use App\Support\BibleBookNames;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Importe une traduction biblique depuis un fichier JSON « un objet par verset ».
 *
 * Format attendu (fichiers de storage/bible/) :
 * [
 *   {
 *     "language": "French",       // facultatif, sert à choisir les noms des livres
 *     "version": "BDS",            // facultatif, informatif
 *     "testament": "Old",          // facultatif : Old/New, OT/NT, sinon déduit du livre
 *     "book": 1,                   // 1 (Genèse) … 66 (Apocalypse)
 *     "chapter": 1,
 *     "verse": 1,
 *     "text": "Au commencement…"
 *   },
 *   ...
 * ]
 *
 * Avant toute écriture, le fichier est contrôlé contre le schéma réel des tables
 * bible_books / bible_verses (longueur du code, plages numériques, taille du texte).
 *
 * Usage :
 *   php artisan bible:import-verses BDS storage/bible/french_bds.json --dry-run
 *   php artisan bible:import-verses BDS storage/bible/french_bds.json
 *   php artisan bible:import-verses YOR14 storage/bible/yoruba_2014.json --names-from=YOR
 *   php artisan bible:import-verses NIV storage/bible/english_niv.json --fresh
 */
class ImportBibleVerses extends Command
{
    protected $signature = 'bible:import-verses
        {translation   : Code de la traduction (BDS, NIV, S21…)}
        {file          : Chemin vers le fichier JSON (un objet par verset)}
        {--fresh       : Supprime les livres et versets existants de ce code avant import}
        {--dry-run     : Contrôle le fichier et la base sans rien écrire}
        {--lang=       : Langue des noms de livres (fr, en) ; déduite du champ "language" sinon}
        {--names-from= : Copie les noms et abréviations des livres d\'une traduction déjà en base}';

    protected $description = 'Importe une traduction de la Bible depuis un fichier JSON « un objet par verset »';

    // Valeurs du champ "language" des fichiers → code ISO
    private const LANGUAGES = [
        'french' => 'fr', 'français' => 'fr', 'francais' => 'fr',
        'english' => 'en', 'anglais' => 'en',
        'yoruba' => 'yo', 'ewe' => 'ee', 'fon' => 'fon', 'fongbe' => 'fon',
    ];

    private const BATCH_SIZE = 1000;

    public function handle(): int
    {
        $code     = strtoupper(trim($this->argument('translation')));
        $filePath = $this->argument('file');
        $dryRun   = (bool) $this->option('dry-run');

        if (!file_exists($filePath)) {
            $this->error("Fichier introuvable : {$filePath}");
            return Command::FAILURE;
        }

        $limits = $this->columnLimits();

        if ($code === '' || ($limits['code'] !== null && mb_strlen($code) > $limits['code'])) {
            $this->error("Code « {$code} » invalide : {$limits['code']} caractères au plus (colonne translation).");
            return Command::FAILURE;
        }

        $this->info('Lecture du fichier...');
        $json = json_decode(ltrim(file_get_contents($filePath), "\xEF\xBB\xBF"), true);

        if (!is_array($json) || !array_is_list($json)) {
            $this->error('Format JSON invalide : ' . (json_last_error() ? json_last_error_msg() : 'un tableau de versets est attendu.'));
            $this->line("Conseil : pour le format « livres → chapitres », utilisez bible:import.");
            return Command::FAILURE;
        }

        if (isset($json[0]['chapters'])) {
            $this->error('Ce fichier est au format « livres → chapitres » : utilisez bible:import.');
            return Command::FAILURE;
        }

        // ── Contrôle du contenu ───────────────────────────────────────────────
        $errors = $warnings = [];
        $verses = [];      // "livre:chapitre:verset" => ligne
        $books  = [];      // numéro => ['testament' => …, 'chapters' => max]
        $languages = $versions = [];
        $duplicates = $skipped = 0;

        foreach ($json as $i => $row) {
            $line = $i + 1;

            if (!is_array($row)) {
                $errors[] = "Entrée n° {$line} : objet attendu.";
                continue;
            }

            $book    = $this->toInt($row['book'] ?? null);
            $chapter = $this->toInt($row['chapter'] ?? null);
            $verse   = $this->toInt($row['verse'] ?? null);
            $text    = is_string($row['text'] ?? null) ? trim($row['text']) : null;

            if ($book === null || $chapter === null || $verse === null || $text === null) {
                $errors[] = "Entrée n° {$line} : book, chapter, verse (entiers) et text (texte) sont obligatoires.";
                continue;
            }
            if ($book < 1 || $book > 66) {
                $errors[] = "Entrée n° {$line} : livre {$book} hors de 1–66.";
                continue;
            }
            if ($chapter < 1 || $verse < 1
                || ($limits['chapter'] !== null && $chapter > $limits['chapter'])
                || ($limits['verse'] !== null && $verse > $limits['verse'])) {
                $errors[] = "Entrée n° {$line} : chapitre {$chapter} ou verset {$verse} hors limites de la base.";
                continue;
            }
            if ($text === '') {
                $skipped++;
                continue;
            }
            if ($limits['text'] !== null && strlen($text) > $limits['text']) {
                $errors[] = "Entrée n° {$line} ({$book} {$chapter}:{$verse}) : texte de " . strlen($text) . " octets, la colonne en accepte {$limits['text']}.";
                continue;
            }

            $key = "{$book}:{$chapter}:{$verse}";
            if (isset($verses[$key])) {
                $duplicates++;
            }
            $verses[$key] = [
                'translation' => $code,
                'book'        => $book,
                'chapter'     => $chapter,
                'verse'       => $verse,
                'text'        => $text,
            ];

            $books[$book]['chapters']  = max($books[$book]['chapters'] ?? 0, $chapter);
            $books[$book]['testament'] ??= $this->testament($row['testament'] ?? null, $book);

            if (!empty($row['language'])) $languages[$row['language']] = true;
            if (!empty($row['version']))  $versions[$row['version']]   = true;
        }
        unset($json);

        if ($duplicates) $warnings[] = "{$duplicates} verset(s) en double dans le fichier : la dernière occurrence est gardée.";
        if ($skipped)    $warnings[] = "{$skipped} verset(s) au texte vide ignoré(s).";
        if (count($languages) > 1) $warnings[] = 'Plusieurs langues dans le fichier : ' . implode(', ', array_keys($languages)) . '.';
        if (count($versions) > 1)  $warnings[] = 'Plusieurs versions dans le fichier : ' . implode(', ', array_keys($versions)) . '.';

        $missing = array_diff(range(1, 66), array_keys($books));
        if ($missing) {
            $warnings[] = count($missing) . ' livre(s) absent(s) du fichier (' . implode(', ', $missing) . ') : ils ne seront pas créés.';
        }

        // ── Noms des livres ───────────────────────────────────────────────────
        $language = $this->option('lang')
            ?: (self::LANGUAGES[mb_strtolower((string) array_key_first($languages))] ?? null)
            ?: config("bible.translations.{$code}.language");

        $bookNames = $this->bookNames($language, $errors, $warnings);

        foreach ($bookNames as $number => $meta) {
            if ($limits['name'] !== null && mb_strlen($meta['name']) > $limits['name']) {
                $errors[] = "Nom du livre {$number} trop long pour la colonne name ({$limits['name']} caractères).";
            }
            if ($limits['abbreviation'] !== null && mb_strlen($meta['abbreviation']) > $limits['abbreviation']) {
                $errors[] = "Abréviation du livre {$number} trop longue ({$limits['abbreviation']} caractères).";
            }
        }

        // ── État actuel de la base ────────────────────────────────────────────
        $existingBooks  = BibleBook::where('translation', $code)->count();
        $existingVerses = BibleVerse::where('translation', $code)->count();

        if ($existingVerses || $existingBooks) {
            $warnings[] = $this->option('fresh')
                ? "{$code} existe déjà ({$existingBooks} livres, {$existingVerses} versets) : ces données seront remplacées (--fresh)."
                : "{$code} existe déjà ({$existingBooks} livres, {$existingVerses} versets) : les versets du fichier seront mis à jour, les autres resteront. Ajoutez --fresh pour remplacer la traduction.";
        }

        if (!config("bible.translations.{$code}")) {
            $warnings[] = "{$code} n'est pas déclarée dans config/bible.php : l'API affichera le code comme nom et la langue « unknown ».";
        }

        // ── Rapport ───────────────────────────────────────────────────────────
        $this->table(['Contrôle', 'Résultat'], [
            ['Fichier',            basename($filePath)],
            ['Langue / version',   (implode(', ', array_keys($languages)) ?: '—') . ' / ' . (implode(', ', array_keys($versions)) ?: '—')],
            ['Livres',             count($books)],
            ['Versets à importer', count($verses)],
            ['Noms des livres',    match (true) {
                (bool) $this->option('names-from')     => 'copiés de ' . strtoupper($this->option('names-from')),
                BibleBookNames::supports((string) $language) => $language,
                default                                => 'fr (aucune liste pour « ' . ($language ?: '?') . ' »)',
            }],
            ['Code',               "{$code} (max. {$limits['code']} car.)"],
            ['Texte le plus long', (count($verses) ? max(array_map(fn ($v) => strlen($v['text']), $verses)) : 0) . ' octets (max. ' . ($limits['text'] ?? '—') . ')'],
        ]);

        foreach ($warnings as $warning) $this->warn("  ! {$warning}");
        foreach (array_slice($errors, 0, 20) as $error) $this->error("  ✗ {$error}");
        if (count($errors) > 20) $this->error('  … ' . (count($errors) - 20) . ' autre(s) erreur(s).');

        if ($errors) {
            $this->error('Import annulé : corrigez le fichier ou les options.');
            return Command::FAILURE;
        }

        if (!$verses) {
            $this->error('Aucun verset à importer.');
            return Command::FAILURE;
        }

        if ($dryRun) {
            $this->info("Contrôle réussi : {$code} peut être importée (--dry-run, rien n'a été écrit).");
            return Command::SUCCESS;
        }

        // ── Écriture ──────────────────────────────────────────────────────────
        $bar = $this->output->createProgressBar(count($verses));
        $bar->start();

        DB::transaction(function () use ($code, $books, $bookNames, $verses, $bar) {
            if ($this->option('fresh')) {
                BibleVerse::where('translation', $code)->delete();
                BibleBook::where('translation', $code)->delete();
            }

            $bookRows = [];
            foreach ($books as $number => $book) {
                $bookRows[] = [
                    'translation'    => $code,
                    'number'         => $number,
                    'name'           => $bookNames[$number]['name'],
                    'abbreviation'   => $bookNames[$number]['abbreviation'],
                    'testament'      => $book['testament'],
                    'chapters_count' => $book['chapters'],
                ];
            }
            BibleBook::upsert($bookRows, ['translation', 'number'], ['name', 'abbreviation', 'testament', 'chapters_count']);

            foreach (array_chunk($verses, self::BATCH_SIZE) as $chunk) {
                BibleVerse::upsert($chunk, ['translation', 'book', 'chapter', 'verse'], ['text']);
                $bar->advance(count($chunk));
            }
        });

        $bar->finish();
        $this->newLine();

        // Relecture : le contenu en base correspond-il au fichier ?
        $storedBooks  = BibleBook::where('translation', $code)->count();
        $storedVerses = BibleVerse::where('translation', $code)->count();
        $this->info("{$code} importée : {$storedBooks} livres, {$storedVerses} versets en base (" . count($verses) . ' dans le fichier).');

        if ($storedVerses < count($verses)) {
            $this->error('Moins de versets en base que dans le fichier : vérifiez la base.');
            return Command::FAILURE;
        }

        if (!config("bible.translations.{$code}")) {
            $this->line('Entrée à ajouter dans config/bible.php (tableau translations) :');
            $this->line("    '{$code}' => ['name' => '…', 'language' => '" . ($language ?: 'fr') . "', 'lang_name' => '…', 'source' => 'file'],");
        }

        return Command::SUCCESS;
    }

    /**
     * Noms et abréviations des 66 livres : copiés d'une traduction en base
     * (--names-from), sinon listes intégrées (français par défaut).
     *
     * @return array<int, array{name: string, abbreviation: string}>
     */
    private function bookNames(?string $language, array &$errors, array &$warnings): array
    {
        if ($source = $this->option('names-from')) {
            $source = strtoupper($source);
            $rows = BibleBook::where('translation', $source)->get(['number', 'name', 'abbreviation'])->keyBy('number');

            if ($rows->isEmpty()) {
                $errors[] = "--names-from : la traduction {$source} n'est pas en base.";
                return BibleBookNames::for('fr');
            }

            $books = BibleBookNames::for($language ?? 'fr');
            foreach ($rows as $number => $row) {
                $books[$number] = ['name' => $row->name, 'abbreviation' => $row->abbreviation];
            }
            if ($rows->count() < 66) {
                $warnings[] = "--names-from : {$source} n'a que {$rows->count()} livres, les autres prennent les noms intégrés.";
            }

            return $books;
        }

        if ($language && !BibleBookNames::supports($language)) {
            $warnings[] = "Pas de noms de livres intégrés pour la langue « {$language} » : noms français utilisés. Utilisez --names-from=<CODE> pour reprendre ceux d'une traduction en base.";
        }

        return BibleBookNames::for(BibleBookNames::supports((string) $language) ? $language : 'fr');
    }

    /** Old/New, OT/NT, AT/NT… → OT ou NT (valeurs attendues par le lecteur). */
    private function testament(mixed $value, int $book): string
    {
        $value = mb_strtolower(trim((string) $value));

        return match (true) {
            in_array($value, ['old', 'ot', 'at', 'ancien', 'ancien testament', 'old testament'], true) => 'OT',
            in_array($value, ['new', 'nt', 'nouveau', 'nouveau testament', 'new testament'], true)    => 'NT',
            default => $book <= 39 ? 'OT' : 'NT',
        };
    }

    private function toInt(mixed $value): ?int
    {
        if (is_int($value)) return $value;
        if (is_string($value) && ctype_digit(trim($value))) return (int) trim($value);
        return null;
    }

    /**
     * Limites lues dans le schéma réel (et non supposées d'après les migrations) :
     * le serveur peut avoir des colonnes élargies à la main.
     */
    private function columnLimits(): array
    {
        $books  = collect(Schema::getColumns('bible_books'))->keyBy('name');
        $verses = collect(Schema::getColumns('bible_verses'))->keyBy('name');

        $codeLimits = array_filter([
            $this->lengthOf($books['translation']['type'] ?? ''),
            $this->lengthOf($verses['translation']['type'] ?? ''),
        ]);

        return [
            'code'         => $codeLimits ? min($codeLimits) : null,
            'name'         => $this->lengthOf($books['name']['type'] ?? ''),
            'abbreviation' => $this->lengthOf($books['abbreviation']['type'] ?? ''),
            'chapter'      => $this->integerMax($verses['chapter']['type'] ?? ''),
            'verse'        => $this->integerMax($verses['verse']['type'] ?? ''),
            'text'         => $this->textBytes($verses['text']['type'] ?? ''),
        ];
    }

    private function lengthOf(string $type): ?int
    {
        return preg_match('/char\((\d+)\)/i', $type, $m) ? (int) $m[1] : null;
    }

    private function integerMax(string $type): ?int
    {
        $unsigned = str_contains(strtolower($type), 'unsigned');

        return match (true) {
            str_starts_with($type, 'tinyint')   => $unsigned ? 255 : 127,
            str_starts_with($type, 'smallint')  => $unsigned ? 65535 : 32767,
            str_starts_with($type, 'mediumint') => $unsigned ? 16777215 : 8388607,
            str_starts_with($type, 'int')       => $unsigned ? 4294967295 : 2147483647,
            default                             => null,
        };
    }

    private function textBytes(string $type): ?int
    {
        return match (true) {
            str_starts_with($type, 'tinytext')   => 255,
            str_starts_with($type, 'mediumtext') => 16777215,
            str_starts_with($type, 'longtext')   => null,
            str_starts_with($type, 'text')       => 65535,
            default                              => $this->lengthOf($type) !== null ? $this->lengthOf($type) * 4 : null,
        };
    }
}
