<?php

namespace App\Console\Commands;

use App\Models\BibleVerse;
use App\Models\DailyVerse;
use App\Support\BibleReference;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Importe une liste de versets du jour classés par thème (storage/dailyverse/versets_du_jour.json).
 *
 * Format attendu :
 * {
 *   "versets_du_jour": [
 *     { "id": 1, "theme": "…", "livre": "Exode", "chapitre": 20, "versets": "16",
 *       "reference": "Exode 20:16", "texte": "…" },   // "texte" facultatif
 *     ...
 *   ]
 * }
 *
 * Texte de chaque verset, dans cet ordre : champ "texte" du fichier, sinon --bible-file
 * (fichier « un objet par verset » de storage/bible/), sinon la table bible_verses (--translation).
 * Un verset déjà présent (même référence) est mis à jour ; ses réactions et sa date sont gardées.
 *
 * Usage :
 *   php artisan verse:import storage/dailyverse/versets_du_jour.json --dry-run
 *   php artisan verse:import storage/dailyverse/versets_du_jour.json --bible-file=storage/bible/french.json --save-texts
 *   php artisan verse:import storage/dailyverse/versets_du_jour.json --start=2026-10-23
 */
class ImportDailyVerses extends Command
{
    protected $signature = 'verse:import
        {file             : Fichier JSON des versets du jour}
        {--bible-file=    : Fichier Bible « un objet par verset » d\'où tirer les textes manquants}
        {--translation=LSG : Traduction de bible_verses utilisée si le texte manque}
        {--save-texts     : Écrit les textes trouvés dans le fichier (champ "texte") pour l\'importer ailleurs sans Bible}
        {--start=         : Programme un verset par jour à partir de cette date (YYYY-MM-DD), dans l\'ordre du fichier}
        {--dry-run        : Contrôle sans rien écrire en base}';

    protected $description = 'Importe les versets du jour d\'un fichier JSON, avec leur thème';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (!is_file($path)) {
            $this->error("Fichier introuvable : {$path}");
            return Command::FAILURE;
        }

        $data    = json_decode(ltrim(file_get_contents($path), "\xEF\xBB\xBF"), true);
        $entries = $data['versets_du_jour'] ?? null;
        if (!is_array($entries) || !$entries) {
            $this->error('Format invalide : tableau « versets_du_jour » attendu.');
            return Command::FAILURE;
        }

        $start = null;
        if ($this->option('start')) {
            try {
                $start = Carbon::createFromFormat('Y-m-d', $this->option('start'))->startOfDay();
            } catch (\Throwable) {
                $this->error('Date --start invalide (YYYY-MM-DD attendu).');
                return Command::FAILURE;
            }
        }

        $bibleFile = $this->option('bible-file') ? $this->loadBibleFile($this->option('bible-file')) : null;
        if ($this->option('bible-file') && $bibleFile === null) {
            return Command::FAILURE;
        }
        $translation = strtoupper((string) $this->option('translation'));

        // ── Contrôle et résolution des textes ─────────────────────────────────
        $errors = $rows = $seen = [];
        $fromFile = $fromBible = $fromDb = 0;

        foreach ($entries as $i => $entry) {
            $label = '#' . ($entry['id'] ?? $i + 1) . ' ' . ($entry['reference'] ?? '?');
            $reference = trim((string) ($entry['reference'] ?? ''));
            $theme     = trim((string) ($entry['theme'] ?? ''));
            $ref       = BibleReference::parse($reference);

            if ($reference === '' || !$ref) {
                $errors[] = "{$label} : référence non reconnue.";
                continue;
            }
            if (mb_strlen($reference) > 100 || mb_strlen($theme) > 150) {
                $errors[] = "{$label} : référence (100) ou thème (150 caractères) trop long.";
                continue;
            }
            if (isset($seen[$reference])) {
                $errors[] = "{$label} : référence déjà présente plus haut dans le fichier.";
                continue;
            }
            $seen[$reference] = true;

            $text = trim((string) ($entry['texte'] ?? $entry['text'] ?? ''));
            if ($text !== '') {
                $fromFile++;
            } elseif ($bibleFile !== null && ($text = $this->textFrom($bibleFile, $ref)) !== '') {
                $fromBible++;
            } elseif (($text = $this->textFromDatabase($translation, $ref)) !== '') {
                $fromDb++;
            }

            if ($text === '') {
                $errors[] = "{$label} : texte introuvable (fichier, --bible-file, bible_verses {$translation}).";
                continue;
            }

            $rows[] = ['index' => $i, 'reference' => $reference, 'theme' => $theme ?: null, 'text' => $text];
        }

        // ── Rapport ───────────────────────────────────────────────────────────
        $existing = DailyVerse::whereIn('reference', array_column($rows, 'reference'))->pluck('id', 'reference');
        $themes   = array_count_values(array_filter(array_column($rows, 'theme')));

        $this->table(['Contrôle', 'Résultat'], [
            ['Versets dans le fichier', count($entries)],
            ['Versets valides',         count($rows)],
            ['Thèmes',                  count($themes)],
            ['Textes : fichier / --bible-file / base', "{$fromFile} / {$fromBible} / {$fromDb}" . ($fromDb ? " ({$translation})" : '')],
            ['Déjà en base (mis à jour)', $existing->count()],
            ['Nouveaux',                count($rows) - $existing->count()],
            ['Programmation',           $start ? 'un par jour à partir du ' . $start->format('d/m/Y') : 'aucune (rotation des versets actifs)'],
        ]);

        foreach (array_slice($errors, 0, 20) as $error) $this->error("  ✗ {$error}");
        if (count($errors) > 20) $this->error('  … ' . (count($errors) - 20) . ' autre(s) erreur(s).');
        if ($errors) {
            $this->error('Import annulé : corrigez le fichier ou les options.');
            return Command::FAILURE;
        }

        if ($this->option('save-texts') && !$this->option('dry-run')) {
            foreach ($rows as $row) {
                $data['versets_du_jour'][$row['index']]['texte'] = $row['text'];
            }
            file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
            $this->info("Textes enregistrés dans {$path} (champ « texte »).");
        }

        if ($this->option('dry-run')) {
            $this->info('Contrôle réussi (--dry-run, rien n\'a été écrit).');
            return Command::SUCCESS;
        }

        // ── Écriture ──────────────────────────────────────────────────────────
        $created = $updated = $scheduled = 0;

        DB::transaction(function () use ($rows, $start, &$created, &$updated, &$scheduled) {
            $taken = $start
                ? DailyVerse::whereDate('scheduled_date', '>=', $start->toDateString())->pluck('scheduled_date')
                    ->map(fn ($d) => Carbon::parse($d)->toDateString())->flip()->all()
                : [];
            $day = $start?->copy();

            foreach ($rows as $row) {
                $verse = DailyVerse::firstOrNew(['reference' => $row['reference']]);
                $verse->exists ? $updated++ : $created++;
                $verse->fill(['verse_text' => $row['text'], 'theme' => $row['theme'], 'is_active' => true]);

                if ($day && !$verse->scheduled_date) {
                    while (isset($taken[$day->toDateString()])) {
                        $day->addDay();
                    }
                    $verse->scheduled_date = $day->toDateString();
                    $taken[$day->toDateString()] = true;
                    $day->addDay();
                    $scheduled++;
                }

                $verse->save();
            }
        });

        $this->info("Versets du jour : {$created} créés, {$updated} mis à jour" . ($start ? ", {$scheduled} programmés" : '') . '.');

        return Command::SUCCESS;
    }

    /** @return array<string, string>|null "livre:chapitre:verset" => texte */
    private function loadBibleFile(string $path): ?array
    {
        if (!is_file($path)) {
            $this->error("Fichier Bible introuvable : {$path}");
            return null;
        }
        $json = json_decode(ltrim(file_get_contents($path), "\xEF\xBB\xBF"), true);
        if (!is_array($json) || !isset($json[0]['book'], $json[0]['chapter'], $json[0]['verse'])) {
            $this->error('Fichier Bible invalide : format « un objet par verset » attendu (book, chapter, verse, text).');
            return null;
        }

        $map = [];
        foreach ($json as $row) {
            $map["{$row['book']}:{$row['chapter']}:{$row['verse']}"] = trim((string) ($row['text'] ?? ''));
        }

        return $map;
    }

    /** Texte d'une plage depuis le fichier Bible ; '' si un verset manque. */
    private function textFrom(array $bible, array $ref): string
    {
        $parts = [];
        for ($v = $ref['verseStart']; $v <= $ref['verseEnd']; $v++) {
            $text = $bible["{$ref['book']}:{$ref['chapter']}:{$v}"] ?? '';
            if ($text === '') {
                return '';
            }
            $parts[] = $text;
        }

        return implode(' ', $parts);
    }

    private function textFromDatabase(string $translation, array $ref): string
    {
        $verses = BibleVerse::where(['translation' => $translation, 'book' => $ref['book'], 'chapter' => $ref['chapter']])
            ->whereBetween('verse', [$ref['verseStart'], $ref['verseEnd']])
            ->orderBy('verse')->pluck('text');

        return $verses->count() === $ref['verseEnd'] - $ref['verseStart'] + 1 ? trim($verses->implode(' ')) : '';
    }
}
