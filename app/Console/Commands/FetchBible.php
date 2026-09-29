<?php

namespace App\Console\Commands;

use App\Models\BibleBook;
use App\Models\BibleVerse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Télécharge et importe une traduction de la Bible depuis API.Bible ou BibleBrain.
 *
 * Usage :
 *   php artisan bible:fetch LSG
 *   php artisan bible:fetch KJV --fresh
 *   php artisan bible:fetch YCB --bible-id=<id>
 *   php artisan bible:fetch FON --fileset=<id>
 *
 * Découvrir les IDs disponibles :
 *   php artisan bible:sources --source=apibible  --lang=fr
 *   php artisan bible:sources --source=biblebrain --lang=fon
 */
class FetchBible extends Command
{
    protected $signature = 'bible:fetch
        {translation  : Code de la traduction (LSG, KJV, WEB, FON, GUN…)}
        {--fresh      : Supprime les données existantes avant import}
        {--bible-id=  : Surcharge le bible_id pour API.Bible}
        {--fileset=   : Surcharge le fileset_id pour BibleBrain}
        {--delay=150  : Délai entre requêtes API en millisecondes (défaut : 150)}';

    protected $description = 'Importe une traduction de la Bible depuis API.Bible ou BibleBrain';

    // Mapping OSIS book_id → numéro canonique 1-66
    private const BOOK_NUMBERS = [
        'GEN'=>1,  'EXO'=>2,  'LEV'=>3,  'NUM'=>4,  'DEU'=>5,
        'JOS'=>6,  'JDG'=>7,  'RUT'=>8,  '1SA'=>9,  '2SA'=>10,
        '1KI'=>11, '2KI'=>12, '1CH'=>13, '2CH'=>14, 'EZR'=>15,
        'NEH'=>16, 'EST'=>17, 'JOB'=>18, 'PSA'=>19, 'PRO'=>20,
        'ECC'=>21, 'SNG'=>22, 'ISA'=>23, 'JER'=>24, 'LAM'=>25,
        'EZK'=>26, 'DAN'=>27, 'HOS'=>28, 'JOL'=>29, 'AMO'=>30,
        'OBA'=>31, 'JON'=>32, 'MIC'=>33, 'NAM'=>34, 'HAB'=>35,
        'ZEP'=>36, 'HAG'=>37, 'ZEC'=>38, 'MAL'=>39,
        'MAT'=>40, 'MRK'=>41, 'LUK'=>42, 'JHN'=>43, 'ACT'=>44,
        'ROM'=>45, '1CO'=>46, '2CO'=>47, 'GAL'=>48, 'EPH'=>49,
        'PHP'=>50, 'COL'=>51, '1TH'=>52, '2TH'=>53, '1TI'=>54,
        '2TI'=>55, 'TIT'=>56, 'PHM'=>57, 'HEB'=>58, 'JAS'=>59,
        '1PE'=>60, '2PE'=>61, '1JN'=>62, '2JN'=>63, '3JN'=>64,
        'JUD'=>65, 'REV'=>66,
    ];

    // ──────────────────────────────────────────────────────────────────────────

    public function handle(): int
    {
        $code     = strtoupper($this->argument('translation'));
        $registry = config("bible.translations.{$code}");

        if (!$registry) {
            $this->error("Traduction '{$code}' inconnue. Ajoutez-la dans config/bible.php.");
            return Command::FAILURE;
        }

        return match ($registry['source']) {
            'apibible'   => $this->fetchFromApiBible($code, $registry),
            'biblebrain' => $this->fetchFromBibleBrain($code, $registry),
            default      => $this->error("Source '{$registry['source']}' non supportée.") ?: Command::FAILURE,
        };
    }

    // ──────────────────────────────────────────────────────────────────────────
    // API.Bible
    // ──────────────────────────────────────────────────────────────────────────

    private function fetchFromApiBible(string $code, array $meta): int
    {
        $apiKey  = config('bible.apibible.key');
        $baseUrl = config('bible.apibible.base_url');

        if (!$apiKey) {
            $this->error("BIBLE_API_KEY manquant dans .env");
            return Command::FAILURE;
        }

        $bibleId = $this->option('bible-id') ?: ($meta['bible_id'] ?? null);

        if (!$bibleId) {
            $this->error("Aucun bible_id configuré pour {$code}.");
            $this->line("  → Cherchez l'ID : php artisan bible:sources --source=apibible --lang={$meta['language']}");
            $this->line("  → Puis ajoutez BIBLE_ID_{$code}=<id> dans votre .env");
            return Command::FAILURE;
        }

        $this->info("📖 API.Bible — {$meta['name']} ({$bibleId})");

        $response = Http::withHeaders(['api-key' => $apiKey])
                        ->timeout(30)
                        ->get("{$baseUrl}/bibles/{$bibleId}/books", ['include-chapters' => 'true']);

        if (!$response->ok()) {
            $this->error("API.Bible {$response->status()} : {$response->body()}");
            return Command::FAILURE;
        }

        $books = $response->json('data', []);

        if (empty($books)) {
            $this->error("Aucun livre retourné — vérifiez le bible_id.");
            return Command::FAILURE;
        }

        $this->purgeIfFresh($code);

        $totalChapters = (int) array_sum(array_map(fn($b) => count($b['chapters'] ?? []), $books));
        $bar           = $this->output->createProgressBar($totalChapters);
        $bar->start();

        $totalVerses = 0;
        $delay       = max(0, (int) $this->option('delay'));

        foreach ($books as $index => $bookData) {
            $bookNumber = $index + 1;
            $chapters   = $bookData['chapters'] ?? [];

            BibleBook::updateOrCreate(
                ['translation' => $code, 'number' => $bookNumber],
                [
                    'name'           => $bookData['name'] ?? $bookData['nameLong'] ?? "Livre {$bookNumber}",
                    'abbreviation'   => $bookData['abbreviation'] ?? strtoupper(substr($bookData['name'] ?? '', 0, 3)),
                    'testament'      => $bookNumber <= 39 ? 'OT' : 'NT',
                    'chapters_count' => count($chapters),
                ]
            );

            foreach ($chapters as $chapterData) {
                $chapterId     = $chapterData['id'];
                $chapterNumber = (int) $chapterData['number'];

                usleep($delay * 1000);

                $resp = Http::withHeaders(['api-key' => $apiKey])
                            ->timeout(30)
                            ->get("{$baseUrl}/bibles/{$bibleId}/chapters/{$chapterId}", [
                                'content-type'          => 'text',
                                'include-verse-numbers' => 'true',
                                'include-titles'        => 'false',
                                'include-notes'         => 'false',
                            ]);

                if (!$resp->ok()) {
                    $this->newLine();
                    $this->warn("  Chapitre {$chapterId} ignoré ({$resp->status()})");
                    $bar->advance();
                    continue;
                }

                $verses = $this->parseApiBibleText($resp->json('data.content', ''));
                $totalVerses += $this->insertVerses($code, $bookNumber, $chapterNumber, $verses);
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ {$code} importée — {$totalVerses} versets.");

        return Command::SUCCESS;
    }

    /**
     * Extrait les versets numérotés depuis le texte API.Bible.
     * Format attendu : "[1] Texte du verset. [2] Autre verset…"
     */
    private function parseApiBibleText(string $content): array
    {
        $text = strip_tags($content);
        $text = preg_replace('/[¶\x{00B6}\n\r\t]+/u', ' ', $text);
        $text = preg_replace('/\s{2,}/', ' ', trim($text));

        $verses = [];
        preg_match_all('/\[(\d{1,3})\]\s*(.*?)(?=\s*\[\d{1,3}\]|$)/u', $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $num  = (int) $m[1];
            $body = trim($m[2]);
            if ($num > 0 && $body !== '') {
                $verses[$num] = $body;
            }
        }

        return $verses;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // BibleBrain (Faith Comes By Hearing)
    // ──────────────────────────────────────────────────────────────────────────

    private function fetchFromBibleBrain(string $code, array $meta): int
    {
        $apiKey  = config('bible.biblebrain.key');
        $baseUrl = config('bible.biblebrain.base_url');

        if (!$apiKey) {
            $this->error("BIBLEBRAIN_API_KEY manquant dans .env");
            return Command::FAILURE;
        }

        $filesetId = $this->option('fileset') ?: ($meta['fileset_id'] ?? null);

        if (!$filesetId) {
            $this->error("Aucun fileset_id configuré pour {$code}.");
            $this->line("  → Cherchez l'ID : php artisan bible:sources --source=biblebrain --lang={$meta['language']}");
            $this->line("  → Puis ajoutez BIBLEBRAIN_FILESET_{$code}=<id> dans votre .env");
            return Command::FAILURE;
        }

        $this->info("📖 BibleBrain — {$meta['name']} ({$filesetId})");

        $response = Http::timeout(30)
                        ->get("{$baseUrl}/bibles/filesets/{$filesetId}/books", [
                            'v'   => 4,
                            'key' => $apiKey,
                        ]);

        if (!$response->ok()) {
            $this->error("BibleBrain {$response->status()} : {$response->body()}");
            return Command::FAILURE;
        }

        $books = $response->json('data', []);

        if (empty($books)) {
            $this->error("Aucun livre retourné — vérifiez le fileset_id.");
            return Command::FAILURE;
        }

        $this->purgeIfFresh($code);

        $totalChapters = (int) array_sum(array_map(fn($b) => (int)($b['chapters'] ?? 0), $books));
        $bar           = $this->output->createProgressBar($totalChapters);
        $bar->start();

        $totalVerses = 0;
        $delay       = max(0, (int) $this->option('delay'));

        foreach ($books as $bookData) {
            $bookId     = strtoupper($bookData['book_id'] ?? '');
            $bookNumber = self::BOOK_NUMBERS[$bookId] ?? null;

            if (!$bookNumber) {
                $this->newLine();
                $this->warn("  Livre inconnu ignoré : {$bookId}");
                continue;
            }

            $chaptersCount = (int) ($bookData['chapters'] ?? 0);
            $bookName      = $bookData['name'] ?? $bookData['name_short'] ?? "Livre {$bookNumber}";

            BibleBook::updateOrCreate(
                ['translation' => $code, 'number' => $bookNumber],
                [
                    'name'           => $bookName,
                    'abbreviation'   => $bookData['name_short'] ?? strtoupper(substr($bookName, 0, 3)),
                    'testament'      => $bookNumber <= 39 ? 'OT' : 'NT',
                    'chapters_count' => $chaptersCount,
                ]
            );

            for ($chap = 1; $chap <= $chaptersCount; $chap++) {
                usleep($delay * 1000);

                $resp = Http::timeout(30)
                            ->get("{$baseUrl}/bibles/chapter", [
                                'v'           => 4,
                                'key'         => $apiKey,
                                'fileset_id'  => $filesetId,
                                'book_id'     => $bookId,
                                'chapter_num' => $chap,
                            ]);

                if (!$resp->ok()) {
                    $this->newLine();
                    $this->warn("  {$bookId} chap.{$chap} ignoré ({$resp->status()})");
                    $bar->advance();
                    continue;
                }

                $rows   = $resp->json('data', []);
                $verses = [];

                foreach ($rows as $row) {
                    $verseNum  = (int) ($row['verse_start'] ?? 0);
                    $verseText = trim($row['verse_text'] ?? '');
                    if ($verseNum > 0 && $verseText !== '') {
                        $verses[$verseNum] = $verseText;
                    }
                }

                $totalVerses += $this->insertVerses($code, $bookNumber, $chap, $verses);
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ {$code} importée — {$totalVerses} versets.");

        return Command::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function purgeIfFresh(string $code): void
    {
        if ($this->option('fresh')) {
            $this->warn("  Suppression des données existantes pour {$code}…");
            BibleBook::where('translation', $code)->delete();
            BibleVerse::where('translation', $code)->delete();
        }
    }

    /** Insère les versets par lots et retourne le nombre inséré. */
    private function insertVerses(string $code, int $book, int $chapter, array $verses): int
    {
        if (empty($verses)) {
            return 0;
        }

        $batch = [];
        foreach ($verses as $verse => $text) {
            $batch[] = [
                'translation' => $code,
                'book'        => $book,
                'chapter'     => $chapter,
                'verse'       => $verse,
                'text'        => $text,
            ];
        }

        BibleVerse::upsert($batch, ['translation', 'book', 'chapter', 'verse'], ['text']);

        return count($batch);
    }
}
