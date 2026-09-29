<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Liste les traductions disponibles sur API.Bible ou BibleBrain.
 *
 * Usage :
 *   php artisan bible:sources --source=apibible  --lang=fr
 *   php artisan bible:sources --source=apibible  --lang=yo
 *   php artisan bible:sources --source=biblebrain --lang=fon
 *   php artisan bible:sources --source=biblebrain --lang=gun
 */
class ListBibleSources extends Command
{
    protected $signature = 'bible:sources
        {--source=apibible : Source à interroger : apibible | biblebrain}
        {--lang=           : Code langue ISO 639 (ex: fr, en, yo, fon, gun)}';

    protected $description = 'Liste les traductions disponibles sur API.Bible ou BibleBrain';

    public function handle(): int
    {
        return match ($this->option('source')) {
            'biblebrain' => $this->listBibleBrain(),
            default      => $this->listApiBible(),
        };
    }

    // ──────────────────────────────────────────────────────────────────────────
    // API.Bible
    // ──────────────────────────────────────────────────────────────────────────

    private function listApiBible(): int
    {
        $apiKey  = config('bible.apibible.key');
        $baseUrl = config('bible.apibible.base_url');

        if (!$apiKey) {
            $this->error("BIBLE_API_KEY manquant dans .env");
            return Command::FAILURE;
        }

        $params = [];
        if ($lang = $this->option('lang')) {
            $params['language'] = $lang;
        }

        $this->info("Interrogation de API.Bible" . ($lang ? " (langue : {$lang})" : "") . "…");

        $response = Http::withHeaders(['api-key' => $apiKey])
                        ->timeout(15)
                        ->get("{$baseUrl}/bibles", $params);

        if (!$response->ok()) {
            $this->error("API.Bible {$response->status()} : {$response->body()}");
            return Command::FAILURE;
        }

        $bibles = $response->json('data', []);

        if (empty($bibles)) {
            $this->warn("Aucune traduction trouvée pour cette langue.");
            return Command::SUCCESS;
        }

        $this->table(
            ['bible_id', 'Nom', 'Abréviation', 'Langue', 'Pays'],
            array_map(fn($b) => [
                $b['id']                                ?? '—',
                $b['name']          ?? $b['nameLocal'] ?? '—',
                $b['abbreviation']  ?? '—',
                $b['language']['name'] ?? $b['language']['nameLocal'] ?? '—',
                $b['countries'][0]['name'] ?? '—',
            ], $bibles)
        );

        $this->newLine();
        $this->line("Usage : php artisan bible:fetch <CODE> --bible-id=<bible_id>");
        $this->line("   ex : php artisan bible:fetch LSG --bible-id=bba9f40183526463-01");

        return Command::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // BibleBrain
    // ──────────────────────────────────────────────────────────────────────────

    private function listBibleBrain(): int
    {
        $apiKey  = config('bible.biblebrain.key');
        $baseUrl = config('bible.biblebrain.base_url');

        if (!$apiKey) {
            $this->error("BIBLEBRAIN_API_KEY manquant dans .env");
            return Command::FAILURE;
        }

        $params = ['v' => 4, 'key' => $apiKey, 'media' => 'text_plain'];

        if ($lang = $this->option('lang')) {
            $params['language_code'] = $lang;
        }

        $this->info("Interrogation de BibleBrain" . ($lang ? " (langue : {$lang})" : "") . "…");

        $response = Http::timeout(15)->get("{$baseUrl}/bibles", $params);

        if (!$response->ok()) {
            $this->error("BibleBrain {$response->status()} : {$response->body()}");
            return Command::FAILURE;
        }

        $bibles = collect($response->json('data', []))->take(60)->values();

        if ($bibles->isEmpty()) {
            $this->warn("Aucune traduction trouvée pour cette langue.");
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($bibles as $bible) {
            $filesets = collect($bible['filesets'] ?? [])
                ->filter(fn($fs) => str_ends_with($fs['id'] ?? '', 'ET') || ($fs['type'] ?? '') === 'text_plain')
                ->values();

            foreach ($filesets as $fs) {
                $rows[] = [
                    $fs['id']                   ?? '—',
                    $bible['vname'] ?? $bible['name'] ?? '—',
                    $bible['language']          ?? '—',
                    $bible['iso']               ?? '—',
                    $fs['size']                 ?? '—',
                ];
            }
        }

        if (empty($rows)) {
            $this->warn("Aucun fileset text_plain trouvé pour cette langue.");
            return Command::SUCCESS;
        }

        $this->table(['fileset_id', 'Nom', 'Langue', 'ISO', 'Taille'], $rows);

        $this->newLine();
        $this->line("Usage : php artisan bible:fetch <CODE> --fileset=<fileset_id>");
        $this->line("   ex : php artisan bible:fetch FON --fileset=FONTV2ET");

        return Command::SUCCESS;
    }
}
