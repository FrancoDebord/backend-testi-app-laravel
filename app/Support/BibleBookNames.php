<?php

namespace App\Support;

/**
 * Noms et abréviations des 66 livres de la Bible (ordre canonique protestant,
 * Genèse = 1 … Apocalypse = 66), utilisés par les commandes d'import quand
 * le fichier source ne les fournit pas.
 */
final class BibleBookNames
{
    public const FR = [
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

    public const EN = [
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

    public const ABBREV_FR = [
        1=>'Gn',2=>'Ex',3=>'Lv',4=>'Nb',5=>'Dt',6=>'Jos',7=>'Jg',8=>'Rt',9=>'1S',10=>'2S',
        11=>'1R',12=>'2R',13=>'1Ch',14=>'2Ch',15=>'Esd',16=>'Né',17=>'Est',18=>'Job',19=>'Ps',20=>'Pr',
        21=>'Ec',22=>'Ct',23=>'És',24=>'Jr',25=>'Lm',26=>'Éz',27=>'Dn',28=>'Os',29=>'Jl',30=>'Am',
        31=>'Ab',32=>'Jon',33=>'Mi',34=>'Na',35=>'Hab',36=>'So',37=>'Ag',38=>'Za',39=>'Ml',
        40=>'Mt',41=>'Mc',42=>'Lc',43=>'Jn',44=>'Ac',45=>'Rm',46=>'1Co',47=>'2Co',48=>'Ga',49=>'Ép',
        50=>'Ph',51=>'Col',52=>'1Th',53=>'2Th',54=>'1Tm',55=>'2Tm',56=>'Tt',57=>'Phm',58=>'Hé',59=>'Jc',
        60=>'1P',61=>'2P',62=>'1Jn',63=>'2Jn',64=>'3Jn',65=>'Jd',66=>'Ap',
    ];

    public const ABBREV_EN = [
        1=>'Gen',2=>'Exod',3=>'Lev',4=>'Num',5=>'Deut',6=>'Josh',7=>'Judg',8=>'Ruth',9=>'1Sam',10=>'2Sam',
        11=>'1Kgs',12=>'2Kgs',13=>'1Chr',14=>'2Chr',15=>'Ezra',16=>'Neh',17=>'Esth',18=>'Job',19=>'Ps',20=>'Prov',
        21=>'Eccl',22=>'Song',23=>'Isa',24=>'Jer',25=>'Lam',26=>'Ezek',27=>'Dan',28=>'Hos',29=>'Joel',30=>'Amos',
        31=>'Obad',32=>'Jonah',33=>'Mic',34=>'Nah',35=>'Hab',36=>'Zeph',37=>'Hag',38=>'Zech',39=>'Mal',
        40=>'Matt',41=>'Mark',42=>'Luke',43=>'John',44=>'Acts',45=>'Rom',46=>'1Cor',47=>'2Cor',48=>'Gal',49=>'Eph',
        50=>'Phil',51=>'Col',52=>'1Thess',53=>'2Thess',54=>'1Tim',55=>'2Tim',56=>'Titus',57=>'Phlm',58=>'Heb',59=>'Jas',
        60=>'1Pet',61=>'2Pet',62=>'1John',63=>'2John',64=>'3John',65=>'Jude',66=>'Rev',
    ];

    /** Langues dont les noms sont intégrés. */
    public static function supports(string $language): bool
    {
        return in_array($language, ['fr', 'en'], true);
    }

    /** @return array<int, array{name: string, abbreviation: string}> */
    public static function for(string $language): array
    {
        [$names, $abbrevs] = $language === 'en'
            ? [self::EN, self::ABBREV_EN]
            : [self::FR, self::ABBREV_FR];

        $books = [];
        foreach ($names as $number => $name) {
            $books[$number] = ['name' => $name, 'abbreviation' => $abbrevs[$number]];
        }

        return $books;
    }
}
