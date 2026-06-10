<?php

namespace App\Console\Commands;

use App\Models\BibleBook;
use App\Models\BibleVerse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importe une traduction biblique depuis un fichier JSON.
 *
 * Format attendu (thiagobodruk/bible ou compatible) :
 * [
 *   {
 *     "abbrev": "gn",
 *     "name": "Genèse",          // optionnel si --names non fourni
 *     "testament": "OT",          // optionnel, déduit automatiquement
 *     "chapters": [
 *       ["Verset 1 du chap 1", "Verset 2 du chap 1"],
 *       ["Verset 1 du chap 2"]
 *     ]
 *   },
 *   ...
 * ]
 *
 * Usage :
 *   php artisan bible:import LSG storage/bible/lsg.json
 *   php artisan bible:import KJV storage/bible/kjv.json
 *   php artisan bible:import NEG storage/bible/neg.json --fresh
 */
class ImportBible extends Command
{
    protected $signature = 'bible:import
        {translation : Code de la traduction (LSG, KJV, NEG…)}
        {file        : Chemin vers le fichier JSON}
        {--fresh     : Supprime les données existantes avant import}';

    protected $description = 'Importe une traduction de la Bible depuis un fichier JSON';

    // Noms des 66 livres en français (fallback si absent du JSON)
    private const BOOK_NAMES_FR = [
        1=>'Genèse',2=>'Exode',3=>'Lévitique',4=>'Nombres',5=>'Deutéronome',
        6=>'Josué',7=>'Juges',8=>'Ruth',9=>'1 Samuel',10=>'2 Samuel',
        11=>'1 Rois',12=>'2 Rois',13=>'1 Chroniques',14=>'2 Chroniques',15=>'Esdras',
        16=>'Néhémie',17=>'Esther',18=>'Job',19=>'Psaumes',20=>'Proverbes',
        21=>'Ecclésiaste',22=>'Cantique des Cantiques',23=>'Ésaïe',24=>'Jérémie',25=>'Lamentations',
        26=>'Ézéchiel',27=>'Daniel',28=>'Osée',29=>'Joël',30=>'Amos',
        31=>'Abdias',32=>'Jonas',33=>'Michée',34=>'Nahoum',35=>'Habacuc',
        36=>'Sophonie',37=>'Aggée',38=>'Zacharie',39=>'Malachie',
        40=>'Matthieu',41=>'Marc',42=>'Luc',43=>'Jean',44=>'Actes',
        45=>'Romains',46=>'1 Corinthiens',47=>'2 Corinthiens',48=>'Galates',49=>'Éphésiens',
        50=>'Philippiens',51=>'Colossiens',52=>'1 Thessaloniciens',53=>'2 Thessaloniciens',54=>'1 Timothée',
        55=>'2 Timothée',56=>'Tite',57=>'Philémon',58=>'Hébreux',59=>'Jacques',
        60=>'1 Pierre',61=>'2 Pierre',62=>'1 Jean',63=>'2 Jean',64=>'3 Jean',
        65=>'Jude',66=>'Apocalypse',
    ];

    // Noms en anglais (pour KJV)
    private const BOOK_NAMES_EN = [
        1=>'Genesis',2=>'Exodus',3=>'Leviticus',4=>'Numbers',5=>'Deuteronomy',
        6=>'Joshua',7=>'Judges',8=>'Ruth',9=>'1 Samuel',10=>'2 Samuel',
        11=>'1 Kings',12=>'2 Kings',13=>'1 Chronicles',14=>'2 Chronicles',15=>'Ezra',
        16=>'Nehemiah',17=>'Esther',18=>'Job',19=>'Psalms',20=>'Proverbs',
        21=>'Ecclesiastes',22=>'Song of Solomon',23=>'Isaiah',24=>'Jeremiah',25=>'Lamentations',
        26=>'Ezekiel',27=>'Daniel',28=>'Hosea',29=>'Joel',30=>'Amos',
        31=>'Obadiah',32=>'Jonah',33=>'Micah',34=>'Nahum',35=>'Habakkuk',
        36=>'Zephaniah',37=>'Haggai',38=>'Zechariah',39=>'Malachi',
        40=>'Matthew',41=>'Mark',42=>'Luke',43=>'John',44=>'Acts',
        45=>'Romans',46=>'1 Corinthians',47=>'2 Corinthians',48=>'Galatians',49=>'Ephesians',
        50=>'Philippians',51=>'Colossians',52=>'1 Thessalonians',53=>'2 Thessalonians',54=>'1 Timothy',
        55=>'2 Timothy',56=>'Titus',57=>'Philemon',58=>'Hebrews',59=>'James',
        60=>'1 Peter',61=>'2 Peter',62=>'1 John',63=>'2 John',64=>'3 John',
        65=>'Jude',66=>'Revelation',
    ];

    private const ABBREV_FR = [
        'gn'=>'Gn','ex'=>'Ex','lv'=>'Lv','nb'=>'Nb','dt'=>'Dt',
        'jos'=>'Jos','jg'=>'Jg','rt'=>'Rt','1s'=>'1S','2s'=>'2S',
        '1r'=>'1R','2r'=>'2R','1ch'=>'1Ch','2ch'=>'2Ch','esd'=>'Esd',
        'ne'=>'Né','est'=>'Est','job'=>'Job','ps'=>'Ps','pr'=>'Pr',
        'ec'=>'Ec','ct'=>'Ct','es'=>'És','jr'=>'Jr','lm'=>'Lm',
        'ez'=>'Éz','dn'=>'Dn','os'=>'Os','jl'=>'Jl','am'=>'Am',
        'ab'=>'Ab','jon'=>'Jon','mi'=>'Mi','na'=>'Na','hab'=>'Hab',
        'so'=>'So','ag'=>'Ag','za'=>'Za','ml'=>'Ml',
        'mt'=>'Mt','mc'=>'Mc','lc'=>'Lc','jn'=>'Jn','ac'=>'Ac',
        'rm'=>'Rm','1co'=>'1Co','2co'=>'2Co','ga'=>'Ga','ep'=>'Ép',
        'ph'=>'Ph','col'=>'Col','1th'=>'1Th','2th'=>'2Th','1tm'=>'1Tm',
        '2tm'=>'2Tm','tt'=>'Tt','phm'=>'Phm','he'=>'Hé','jc'=>'Jc',
        '1p'=>'1P','2p'=>'2P','1jn'=>'1Jn','2jn'=>'2Jn','3jn'=>'3Jn',
        'jd'=>'Jd','ap'=>'Ap',
    ];

    public function handle(): int
    {
        $translation = strtoupper($this->argument('translation'));
        $filePath    = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("Fichier introuvable : {$filePath}");
            return Command::FAILURE;
        }

        $this->info("Lecture du fichier...");

        $content = file_get_contents($filePath);

        // Supprimer le BOM UTF-8 si présent
        $content = ltrim($content, "\xEF\xBB\xBF");

        $json = json_decode($content, true);
        unset($content); // libérer la mémoire

        if (!is_array($json)) {
            $error = json_last_error_msg();
            $this->error("Format JSON invalide : {$error}");
            $this->line("Conseil : vérifiez l'encodage du fichier (doit être UTF-8).");
            return Command::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->warn("Suppression des données existantes pour {$translation}...");
            BibleBook::where('translation', $translation)->delete();
            BibleVerse::where('translation', $translation)->delete();
        }

        $isEnglish  = in_array($translation, ['KJV', 'ESV', 'NIV', 'NKJV']);
        $namesMap   = $isEnglish ? self::BOOK_NAMES_EN : self::BOOK_NAMES_FR;

        $totalVerses = 0;
        $bar = $this->output->createProgressBar(count($json));
        $bar->start();

        DB::transaction(function () use ($json, $translation, $namesMap, &$totalVerses, $bar) {
            foreach ($json as $index => $bookData) {
                $bookNumber = $index + 1;
                $chapters   = $bookData['chapters'] ?? [];

                $name      = $bookData['name']      ?? $namesMap[$bookNumber] ?? "Livre {$bookNumber}";
                $abbrevKey = strtolower($bookData['abbrev'] ?? '');
                $abbrev    = $bookData['abbreviation']
                          ?? self::ABBREV_FR[$abbrevKey]
                          ?? strtoupper(substr($name, 0, 3));
                $testament = $bookData['testament'] ?? ($bookNumber <= 39 ? 'OT' : 'NT');

                BibleBook::updateOrCreate(
                    ['translation' => $translation, 'number' => $bookNumber],
                    [
                        'name'           => $name,
                        'abbreviation'   => $abbrev,
                        'testament'      => $testament,
                        'chapters_count' => count($chapters),
                    ]
                );

                $verseBatch = [];
                foreach ($chapters as $chapterIndex => $verseList) {
                    $chapterNumber = $chapterIndex + 1;
                    foreach ($verseList as $verseIndex => $text) {
                        $verseNumber = $verseIndex + 1;
                        $verseBatch[] = [
                            'translation' => $translation,
                            'book'        => $bookNumber,
                            'chapter'     => $chapterNumber,
                            'verse'       => $verseNumber,
                            'text'        => trim($text),
                        ];

                        // Insertion par lots de 500
                        if (count($verseBatch) >= 500) {
                            BibleVerse::upsert($verseBatch, ['translation', 'book', 'chapter', 'verse'], ['text']);
                            $totalVerses += count($verseBatch);
                            $verseBatch = [];
                        }
                    }
                }

                if (!empty($verseBatch)) {
                    BibleVerse::upsert($verseBatch, ['translation', 'book', 'chapter', 'verse'], ['text']);
                    $totalVerses += count($verseBatch);
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("✅ {$translation} importée : {$totalVerses} versets insérés.");

        return Command::SUCCESS;
    }
}
