<?php

namespace App\Console\Commands;

use App\Models\DailyVerse;
use App\Models\ThemeSemaine;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Importe un calendrier de versets du jour groupés par semaine thématique (storage/dailyverse/*.json) dans
 * le verset du jour existant (daily_verses) : chaque date reçoit son verset, son thème (themes_semaine) et
 * sa version. Le verset déjà programmé à une date est remplacé (même ligne : rien n'est dupliqué). Relancer
 * la commande met à jour.
 *
 * Format : { "annee"?: 2027, "version_biblique": "…", "semaines": [ { "annee"?: 2026, "semaine", "debut", "fin",
 *   "thematique", "verset_cle", "periode"|null, "versets": [ { "date", "jour", "reference", "texte" } ] } ] }
 * Année de chaque semaine : son champ "annee", sinon celui du fichier (un fichier peut couvrir plusieurs
 * années : versets_du_jour_2026_2027.json).
 *
 * Usage :
 *   php artisan versets:import                                              (dailyverse/versets_du_jour_2027.json)
 *   php artisan versets:import dailyverse/versets_du_jour_2026_2027.json
 *   php artisan versets:import dailyverse/autre.json --fresh   (supprime d'abord les versets de la période du fichier)
 *
 * Voir docs/fonctionnalites/verset-du-jour.md
 */
class ImportVersetsDuJour extends Command
{
    protected $signature = 'versets:import
        {fichier=dailyverse/versets_du_jour_2027.json : Fichier JSON, relatif au dossier storage/ (ou chemin absolu)}
        {--fresh : Supprime d\'abord les versets programmés sur la période du fichier et ses thèmes}';

    protected $description = 'Importe les versets du jour d\'un calendrier, par semaine thématique';

    /** Version enregistrée si le fichier ne la donne pas. */
    public const DEFAULT_VERSION = 'LSG 1910';

    public function handle(): int
    {
        $data = $this->read((string) $this->argument('fichier'));
        if ($data === null) {
            return Command::FAILURE;
        }

        $errors = $this->validate($data);
        if ($errors) {
            $this->error('Fichier invalide :');
            foreach (array_slice($errors, 0, 15) as $error) {
                $this->line("  - {$error}");
            }
            if (count($errors) > 15) {
                $this->line('  … et ' . (count($errors) - 15) . ' autre(s) erreur(s).');
            }
            return Command::FAILURE;
        }

        $version = self::version($data['version_biblique'] ?? null);
        $dates   = collect($data['semaines'])->flatMap(fn ($w) => array_column($w['versets'], 'date'))->sort()->values();
        [$first, $last] = [$dates->first(), $dates->last()];
        $total   = $dates->count();

        if ($this->option('fresh')) {
            // Période du fichier seulement : jamais les jours passés d'une année déjà entamée.
            $existing = DailyVerse::whereDate('scheduled_date', '>=', $first)->whereDate('scheduled_date', '<=', $last)->count();
            // Supprimer un verset supprime aussi ses réactions et commentaires.
            if ($existing && !$this->confirm("--fresh : {$existing} verset(s) programmé(s) du {$first} au {$last} seront supprimés, avec leurs réactions et commentaires. Continuer ?", true)) {
                return Command::FAILURE;
            }
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        DB::transaction(function () use ($data, $version, $bar, $first, $last) {
            if ($this->option('fresh')) {
                DailyVerse::whereDate('scheduled_date', '>=', $first)->whereDate('scheduled_date', '<=', $last)->get()->each->delete();
                foreach ($data['semaines'] as $week) {
                    ThemeSemaine::where('annee', self::yearOf($data, $week))->where('numero_semaine', (int) $week['semaine'])->delete();
                }
            }

            foreach ($data['semaines'] as $week) {
                $theme = ThemeSemaine::updateOrCreate(
                    ['annee' => self::yearOf($data, $week), 'numero_semaine' => (int) $week['semaine']],
                    [
                        'thematique' => trim($week['thematique']),
                        'verset_cle' => trim($week['verset_cle']),
                        'periode'    => filled($week['periode'] ?? null) ? trim($week['periode']) : null,
                        'date_debut' => $week['debut'],
                        'date_fin'   => $week['fin'],
                    ],
                );

                foreach ($week['versets'] as $row) {
                    // Une ligne par date (scheduled_date unique) : le verset déjà programmé est remplacé.
                    $verse = DailyVerse::whereDate('scheduled_date', $row['date'])->first() ?? new DailyVerse();
                    $verse->fill([
                        'scheduled_date'   => $row['date'],
                        'reference'        => trim($row['reference']),
                        'verse_text'       => trim($row['texte']),
                        'theme'            => Str::limit(trim($week['thematique']), 150, ''),
                        'week_number'      => (int) $week['semaine'],
                        'theme_semaine_id' => $theme->id,
                        'version'          => $version,
                        'is_active'        => true,
                    ])->save();
                    $bar->advance();
                }
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info(sprintf('%d semaine(s) et %d verset(s) importés, du %s au %s.', count($data['semaines']), $total, $first, $last));

        return Command::SUCCESS;
    }

    /** Année d'une semaine : la sienne, sinon celle du fichier. */
    private static function yearOf(array $data, array $week): mixed
    {
        return $week['annee'] ?? $data['annee'] ?? null;
    }

    /** « Louis Segond 1910 (domaine public) » → « LSG 1910 ». */
    public static function version(?string $label): string
    {
        if (blank($label)) return self::DEFAULT_VERSION;
        if (preg_match('/segond\s*(\d{4})/i', $label, $m)) return "LSG {$m[1]}";

        return Str::limit(trim($label), 50, '');
    }

    private function read(string $file): ?array
    {
        // Chemin relatif au dossier storage/ (le fichier est livré dans storage/dailyverse/), ou absolu.
        $absolute = preg_match('#^([A-Za-z]:[\\\\/]|/)#', $file) === 1;
        $disk = Storage::build(['driver' => 'local', 'root' => $absolute ? dirname($file) : storage_path()]);
        $path = $absolute ? basename($file) : ltrim($file, '/');

        if (!$disk->exists($path)) {
            $this->error("Fichier introuvable : " . ($absolute ? $file : storage_path($path)));
            return null;
        }

        $data = json_decode(ltrim((string) $disk->get($path), "\xEF\xBB\xBF"), true);
        if (!is_array($data)) {
            $this->error('JSON illisible : ' . json_last_error_msg());
            return null;
        }

        return $data;
    }

    /** @return list<string> erreurs (vide si le fichier est valide) */
    private function validate(array $data): array
    {
        if (!is_array($data['semaines'] ?? null) || !$data['semaines']) {
            return ['tableau « semaines » absent ou vide.'];
        }

        $errors = [];
        $dates = [];
        $numbers = [];
        foreach ($data['semaines'] as $i => $week) {
            $year = self::yearOf($data, $week);
            $label = 'semaine ' . ($week['semaine'] ?? '#' . ($i + 1)) . (is_int($year) ? " de {$year}" : '');
            if (!is_int($year) || $year < 2000 || $year > 2100) {
                $errors[] = "{$label} : année absente ou invalide (clé « annee » de la semaine ou du fichier).";
            }
            foreach (['semaine', 'debut', 'fin', 'thematique', 'verset_cle', 'versets'] as $key) {
                if (!array_key_exists($key, $week) || $week[$key] === null || $week[$key] === '') {
                    $errors[] = "{$label} : clé « {$key} » manquante.";
                }
            }
            if (!is_int($week['semaine'] ?? null) || $week['semaine'] < 1 || $week['semaine'] > 53) {
                $errors[] = "{$label} : numéro de semaine invalide (1 à 53).";
            } elseif (isset($numbers["{$year}-{$week['semaine']}"])) {
                $errors[] = "{$label} : numéro de semaine en double.";
            } else {
                $numbers["{$year}-{$week['semaine']}"] = true;
            }
            $start = self::date($week['debut'] ?? null);
            $end = self::date($week['fin'] ?? null);
            if (!$start || !$end || $end->lt($start)) {
                $errors[] = "{$label} : dates « debut » / « fin » invalides.";
            }
            if (!is_array($week['versets'] ?? null)) {
                continue;
            }

            foreach ($week['versets'] as $j => $row) {
                $where = "{$label}, verset " . ($row['date'] ?? '#' . ($j + 1));
                foreach (['date', 'jour', 'reference', 'texte'] as $key) {
                    if (!is_string($row[$key] ?? null) || trim($row[$key]) === '') {
                        $errors[] = "{$where} : clé « {$key} » manquante.";
                    }
                }
                $date = self::date($row['date'] ?? null);
                if (!$date) {
                    $errors[] = "{$where} : date invalide (AAAA-MM-JJ attendu).";
                    continue;
                }
                if (is_int($year) && $date->year !== $year) {
                    $errors[] = "{$where} : date hors de l'année {$year}.";
                }
                if ($start && $end && !$date->betweenIncluded($start, $end)) {
                    $errors[] = "{$where} : date hors de la semaine ({$week['debut']} → {$week['fin']}).";
                }
                if (isset($dates[$row['date']])) {
                    $errors[] = "{$where} : date en double (déjà dans {$dates[$row['date']]}).";
                }
                $dates[$row['date']] ??= $label;
                if (mb_strlen((string) ($row['reference'] ?? '')) > 100) {
                    $errors[] = "{$where} : référence trop longue (100 caractères au plus).";
                }
            }
        }

        return $errors;
    }

    private static function date(mixed $value): ?Carbon
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return null;
        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
