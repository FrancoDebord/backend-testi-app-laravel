<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Références bibliques écrites à la main : « Jérémie 29 : 11 », « Jean 3:16-18 », « 1 Pierre 5.7 »,
 * « Ps 23,1 », « John 3:16 ». Noms français (avec ou sans accents), anglais et abréviations courantes.
 *
 * Une référence analysée : ['book' => 1-66, 'chapter' => int, 'verseStart' => int, 'verseEnd' => int].
 * Les plages qui débordent sur le chapitre suivant (« Jean 3:16-4:2 ») sont réduites au premier verset.
 * Voir docs/fonctionnalites/verset-du-jour.md
 */
class BibleReference
{
    public const NAMES_FR = [
        1 => 'Genèse', 2 => 'Exode', 3 => 'Lévitique', 4 => 'Nombres', 5 => 'Deutéronome',
        6 => 'Josué', 7 => 'Juges', 8 => 'Ruth', 9 => '1 Samuel', 10 => '2 Samuel',
        11 => '1 Rois', 12 => '2 Rois', 13 => '1 Chroniques', 14 => '2 Chroniques', 15 => 'Esdras',
        16 => 'Néhémie', 17 => 'Esther', 18 => 'Job', 19 => 'Psaumes', 20 => 'Proverbes',
        21 => 'Ecclésiaste', 22 => 'Cantique des Cantiques', 23 => 'Ésaïe', 24 => 'Jérémie', 25 => 'Lamentations',
        26 => 'Ézéchiel', 27 => 'Daniel', 28 => 'Osée', 29 => 'Joël', 30 => 'Amos',
        31 => 'Abdias', 32 => 'Jonas', 33 => 'Michée', 34 => 'Nahoum', 35 => 'Habacuc',
        36 => 'Sophonie', 37 => 'Aggée', 38 => 'Zacharie', 39 => 'Malachie',
        40 => 'Matthieu', 41 => 'Marc', 42 => 'Luc', 43 => 'Jean', 44 => 'Actes',
        45 => 'Romains', 46 => '1 Corinthiens', 47 => '2 Corinthiens', 48 => 'Galates', 49 => 'Éphésiens',
        50 => 'Philippiens', 51 => 'Colossiens', 52 => '1 Thessaloniciens', 53 => '2 Thessaloniciens', 54 => '1 Timothée',
        55 => '2 Timothée', 56 => 'Tite', 57 => 'Philémon', 58 => 'Hébreux', 59 => 'Jacques',
        60 => '1 Pierre', 61 => '2 Pierre', 62 => '1 Jean', 63 => '2 Jean', 64 => '3 Jean',
        65 => 'Jude', 66 => 'Apocalypse',
    ];

    private const NAMES_EN = [
        1 => 'Genesis', 2 => 'Exodus', 3 => 'Leviticus', 4 => 'Numbers', 5 => 'Deuteronomy',
        6 => 'Joshua', 7 => 'Judges', 8 => 'Ruth', 9 => '1 Samuel', 10 => '2 Samuel',
        11 => '1 Kings', 12 => '2 Kings', 13 => '1 Chronicles', 14 => '2 Chronicles', 15 => 'Ezra',
        16 => 'Nehemiah', 17 => 'Esther', 18 => 'Job', 19 => 'Psalms', 20 => 'Proverbs',
        21 => 'Ecclesiastes', 22 => 'Song of Solomon', 23 => 'Isaiah', 24 => 'Jeremiah', 25 => 'Lamentations',
        26 => 'Ezekiel', 27 => 'Daniel', 28 => 'Hosea', 29 => 'Joel', 30 => 'Amos',
        31 => 'Obadiah', 32 => 'Jonah', 33 => 'Micah', 34 => 'Nahum', 35 => 'Habakkuk',
        36 => 'Zephaniah', 37 => 'Haggai', 38 => 'Zechariah', 39 => 'Malachi',
        40 => 'Matthew', 41 => 'Mark', 42 => 'Luke', 43 => 'John', 44 => 'Acts',
        45 => 'Romans', 46 => '1 Corinthians', 47 => '2 Corinthians', 48 => 'Galatians', 49 => 'Ephesians',
        50 => 'Philippians', 51 => 'Colossians', 52 => '1 Thessalonians', 53 => '2 Thessalonians', 54 => '1 Timothy',
        55 => '2 Timothy', 56 => 'Titus', 57 => 'Philemon', 58 => 'Hebrews', 59 => 'James',
        60 => '1 Peter', 61 => '2 Peter', 62 => '1 John', 63 => '2 John', 64 => '3 John',
        65 => 'Jude', 66 => 'Revelation',
    ];

    /** Variantes et abréviations (normalisées à la volée). */
    private const ALIASES = [
        1 => ['Gn', 'Gen', 'Ge'], 2 => ['Ex', 'Exo'], 3 => ['Lv', 'Lev'], 4 => ['Nb', 'Nomb', 'Num'], 5 => ['Dt', 'Deut'],
        6 => ['Jos'], 7 => ['Jg', 'Jug'], 8 => ['Rt', 'Ru'], 9 => ['1 S', '1 Sam'], 10 => ['2 S', '2 Sam'],
        11 => ['1 R', '1 Ro'], 12 => ['2 R', '2 Ro'], 13 => ['1 Ch', '1 Chr'], 14 => ['2 Ch', '2 Chr'], 15 => ['Esd'],
        16 => ['Ne', 'Neh'], 17 => ['Esth'] /* pas « Est » : trop fréquent dans un texte libre */, 18 => ['Jb'], 19 => ['Ps', 'Psaume', 'Psalm', 'Psa'], 20 => ['Pr', 'Prov', 'Proverbe'],
        21 => ['Ec', 'Eccl', 'Qohelet'], 22 => ['Ct', 'Cantique', 'Song of Songs', 'Cant'], 23 => ['Es', 'Is', 'Isaïe', 'Esa'], 24 => ['Jr', 'Jer'], 25 => ['Lm', 'Lam'],
        26 => ['Ez', 'Ezk', 'Ezech'], 27 => ['Dn', 'Dan'], 28 => ['Os'], 29 => ['Jl'], 30 => ['Am'],
        31 => ['Ab', 'Abd'], 32 => ['Jon'], 33 => ['Mi', 'Mic'], 34 => ['Na', 'Nahum'], 35 => ['Ha', 'Hab', 'Habakuk'],
        36 => ['So', 'Soph'], 37 => ['Ag'], 38 => ['Za', 'Zach'], 39 => ['Ml', 'Mal'],
        40 => ['Mt', 'Matt'], 41 => ['Mc', 'Mk'], 42 => ['Lc', 'Lk'], 43 => ['Jn'], 44 => ['Ac', 'Actes des apôtres', 'Act'],
        45 => ['Rm', 'Rom'], 46 => ['1 Co', '1 Cor'], 47 => ['2 Co', '2 Cor'], 48 => ['Ga', 'Gal'], 49 => ['Ep', 'Eph'],
        50 => ['Ph', 'Phil'], 51 => ['Col'], 52 => ['1 Th', '1 Thes'], 53 => ['2 Th', '2 Thes'], 54 => ['1 Tm', '1 Tim'],
        55 => ['2 Tm', '2 Tim'], 56 => ['Tt', 'Tit'], 57 => ['Phm', 'Philem'], 58 => ['He', 'Hb', 'Heb'], 59 => ['Jc', 'Jas'],
        60 => ['1 P', '1 Pi', '1 Pet'], 61 => ['2 P', '2 Pi', '2 Pet'], 62 => ['1 Jn'], 63 => ['2 Jn'], 64 => ['3 Jn'],
        65 => ['Jd'], 66 => ['Ap', 'Apoc', 'Rev', 'Révélation'],
    ];

    /** « 29:11 », « 29 : 11 », « 29.11 », « 3,16 » ; plage « 16-18 », « 16–18 », « 16 à 18 ». */
    private const NUMBERS = '/(\d{1,3})\s*[:.,]\s*(\d{1,3})(?:\s*(?:[-–—]|à|to)\s*(\d{1,3}))?/u';

    private static ?array $lookup = null;

    /** Livres d'un seul chapitre : Abdias, Philémon, 2 Jean, 3 Jean, Jude. */
    private const SINGLE_CHAPTER = [31, 57, 63, 64, 65];

    /**
     * Première référence trouvée dans le texte, ou null. Une référence seule d'un livre d'un chapitre
     * peut omettre le chapitre : « Jude 24-25 », « 3 Jean 3 » (= chapitre 1).
     */
    public static function parse(?string $text): ?array
    {
        return self::findAll($text)[0] ?? self::parseSingleChapter($text);
    }

    private static function parseSingleChapter(?string $text): ?array
    {
        if (blank($text) || !preg_match('/^\s*(.+?)\s+(\d{1,3})(?:\s*(?:[-–—]|à|to)\s*(\d{1,3}))?\s*$/u', $text, $m)) {
            return null;
        }
        $book = self::bookNumber($m[1]);
        if (!in_array($book, self::SINGLE_CHAPTER, true) || (int) $m[2] < 1) {
            return null;
        }
        $start = (int) $m[2];
        $end   = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : $start;

        return ['book' => $book, 'chapter' => 1, 'verseStart' => $start, 'verseEnd' => max($start, $end)];
    }

    /** Toutes les références d'un texte libre (ordre du texte). */
    public static function findAll(?string $text): array
    {
        if (blank($text) || !preg_match_all(self::NUMBERS, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $found = [];
        foreach ($matches as $m) {
            $before = substr($text, 0, $m[0][1]);
            $words  = preg_split('/\s+/u', trim($before), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $book   = null;
            // Le nom du livre précède les nombres : jusqu'à 4 mots (« Cantique des Cantiques », « 1 Jean »).
            for ($n = min(4, count($words)); $n >= 1 && !$book; $n--) {
                $book = self::bookNumber(implode(' ', array_slice($words, -$n)));
            }
            if (!$book) {
                continue;
            }
            $chapter = (int) $m[1][0];
            $start   = (int) $m[2][0];
            $end     = isset($m[3]) && $m[3][1] >= 0 && $m[3][0] !== '' ? (int) $m[3][0] : $start;
            if ($chapter < 1 || $start < 1) {
                continue;
            }
            $found[] = ['book' => $book, 'chapter' => $chapter, 'verseStart' => $start, 'verseEnd' => max($start, $end)];
        }

        return $found;
    }

    /** Numéro du livre (1-66) d'après son nom ou une abréviation, sinon null. */
    public static function bookNumber(string $name): ?int
    {
        $key = self::normalize($name);

        return $key === '' ? null : (self::lookup()[$key] ?? null);
    }

    public static function bookName(int $book): string
    {
        return self::NAMES_FR[$book] ?? "Livre {$book}";
    }

    /** « Jérémie 29:11 », « Jean 3:16-18 ». */
    public static function label(array $ref): string
    {
        $verses = $ref['verseEnd'] > $ref['verseStart'] ? "{$ref['verseStart']}-{$ref['verseEnd']}" : (string) $ref['verseStart'];

        return self::bookName($ref['book']) . " {$ref['chapter']}:{$verses}";
    }

    /** Même livre, même chapitre et versets qui se recoupent. */
    public static function overlaps(array $a, array $b): bool
    {
        return $a['book'] === $b['book'] && $a['chapter'] === $b['chapter']
            && $a['verseStart'] <= $b['verseEnd'] && $b['verseStart'] <= $a['verseEnd'];
    }

    private static function normalize(string $name): string
    {
        // Ponctuation autour du nom : « (Jean », « Réf. : Jean ».
        $ascii = Str::lower(Str::ascii($name));

        return preg_replace('/[^a-z0-9]/', '', $ascii) ?? '';
    }

    private static function lookup(): array
    {
        if (self::$lookup !== null) {
            return self::$lookup;
        }
        $map = [];
        foreach ([self::NAMES_FR, self::NAMES_EN] as $names) {
            foreach ($names as $n => $name) {
                $map[self::normalize($name)] ??= $n;
            }
        }
        foreach (self::ALIASES as $n => $aliases) {
            foreach ($aliases as $alias) {
                $map[self::normalize($alias)] ??= $n;
            }
        }

        return self::$lookup = $map;
    }
}
