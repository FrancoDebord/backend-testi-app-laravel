<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Pays proposés dans les formulaires du site (inscription, profil), en français et en toutes lettres,
 * avec leur code ISO 3166-1 (drapeau : public/flags/{code}.svg).
 * Les pays communs avec l'application mobile gardent exactement son orthographe
 * (« Congo (RDC) », « Côte d'Ivoire », « Swaziland »…) pour que les données restent homogènes.
 */
final class Countries
{
    private const CODES = [
        // Afrique (orthographe de l'application mobile)
        'Afrique du Sud' => 'za', 'Algérie' => 'dz', 'Angola' => 'ao', 'Bénin' => 'bj', 'Botswana' => 'bw',
        'Burkina Faso' => 'bf', 'Burundi' => 'bi', 'Cameroun' => 'cm', 'Cap-Vert' => 'cv', 'Comores' => 'km',
        'Congo (Brazzaville)' => 'cg', 'Congo (RDC)' => 'cd', "Côte d'Ivoire" => 'ci', 'Djibouti' => 'dj',
        'Égypte' => 'eg', 'Érythrée' => 'er', 'Éthiopie' => 'et', 'Gabon' => 'ga', 'Gambie' => 'gm', 'Ghana' => 'gh',
        'Guinée' => 'gn', 'Guinée-Bissau' => 'gw', 'Guinée équatoriale' => 'gq', 'Kenya' => 'ke', 'Lesotho' => 'ls',
        'Libéria' => 'lr', 'Libye' => 'ly', 'Madagascar' => 'mg', 'Malawi' => 'mw', 'Mali' => 'ml', 'Maroc' => 'ma',
        'Maurice' => 'mu', 'Mauritanie' => 'mr', 'Mozambique' => 'mz', 'Namibie' => 'na', 'Niger' => 'ne',
        'Nigeria' => 'ng', 'Ouganda' => 'ug', 'République centrafricaine' => 'cf', 'Rwanda' => 'rw',
        'São Tomé-et-Príncipe' => 'st', 'Sénégal' => 'sn', 'Seychelles' => 'sc', 'Sierra Leone' => 'sl',
        'Somalie' => 'so', 'Soudan' => 'sd', 'Soudan du Sud' => 'ss', 'Swaziland' => 'sz', 'Tanzanie' => 'tz',
        'Tchad' => 'td', 'Togo' => 'tg', 'Tunisie' => 'tn', 'Zambie' => 'zm', 'Zimbabwe' => 'zw',
        // Europe
        'Albanie' => 'al', 'Allemagne' => 'de', 'Andorre' => 'ad', 'Autriche' => 'at', 'Belgique' => 'be',
        'Biélorussie' => 'by', 'Bosnie-Herzégovine' => 'ba', 'Bulgarie' => 'bg', 'Chypre' => 'cy', 'Croatie' => 'hr',
        'Danemark' => 'dk', 'Espagne' => 'es', 'Estonie' => 'ee', 'Finlande' => 'fi', 'France' => 'fr', 'Grèce' => 'gr',
        'Hongrie' => 'hu', 'Irlande' => 'ie', 'Islande' => 'is', 'Italie' => 'it', 'Kosovo' => 'xk', 'Lettonie' => 'lv',
        'Liechtenstein' => 'li', 'Lituanie' => 'lt', 'Luxembourg' => 'lu', 'Macédoine du Nord' => 'mk', 'Malte' => 'mt',
        'Moldavie' => 'md', 'Monaco' => 'mc', 'Monténégro' => 'me', 'Norvège' => 'no', 'Pays-Bas' => 'nl',
        'Pologne' => 'pl', 'Portugal' => 'pt', 'République tchèque' => 'cz', 'Roumanie' => 'ro', 'Royaume-Uni' => 'gb',
        'Russie' => 'ru', 'Saint-Marin' => 'sm', 'Serbie' => 'rs', 'Slovaquie' => 'sk', 'Slovénie' => 'si',
        'Suède' => 'se', 'Suisse' => 'ch', 'Ukraine' => 'ua', 'Vatican' => 'va',
        // Amériques
        'Antigua-et-Barbuda' => 'ag', 'Argentine' => 'ar', 'Bahamas' => 'bs', 'Barbade' => 'bb', 'Belize' => 'bz',
        'Bolivie' => 'bo', 'Brésil' => 'br', 'Canada' => 'ca', 'Chili' => 'cl', 'Colombie' => 'co', 'Costa Rica' => 'cr',
        'Cuba' => 'cu', 'Dominique' => 'dm', 'Équateur' => 'ec', 'États-Unis' => 'us', 'Grenade' => 'gd',
        'Guatemala' => 'gt', 'Guyana' => 'gy', 'Haïti' => 'ht', 'Honduras' => 'hn', 'Jamaïque' => 'jm', 'Mexique' => 'mx',
        'Nicaragua' => 'ni', 'Panama' => 'pa', 'Paraguay' => 'py', 'Pérou' => 'pe', 'République dominicaine' => 'do',
        'Saint-Christophe-et-Niévès' => 'kn', 'Sainte-Lucie' => 'lc', 'Saint-Vincent-et-les-Grenadines' => 'vc',
        'Salvador' => 'sv', 'Suriname' => 'sr', 'Trinité-et-Tobago' => 'tt', 'Uruguay' => 'uy', 'Venezuela' => 've',
        // Asie et Moyen-Orient
        'Afghanistan' => 'af', 'Arabie saoudite' => 'sa', 'Arménie' => 'am', 'Azerbaïdjan' => 'az', 'Bahreïn' => 'bh',
        'Bangladesh' => 'bd', 'Bhoutan' => 'bt', 'Birmanie' => 'mm', 'Brunei' => 'bn', 'Cambodge' => 'kh',
        'Chine' => 'cn', 'Corée du Nord' => 'kp', 'Corée du Sud' => 'kr', 'Émirats arabes unis' => 'ae',
        'Géorgie' => 'ge', 'Inde' => 'in', 'Indonésie' => 'id', 'Irak' => 'iq', 'Iran' => 'ir', 'Israël' => 'il',
        'Japon' => 'jp', 'Jordanie' => 'jo', 'Kazakhstan' => 'kz', 'Kirghizistan' => 'kg', 'Koweït' => 'kw',
        'Laos' => 'la', 'Liban' => 'lb', 'Malaisie' => 'my', 'Maldives' => 'mv', 'Mongolie' => 'mn', 'Népal' => 'np',
        'Oman' => 'om', 'Ouzbékistan' => 'uz', 'Pakistan' => 'pk', 'Palestine' => 'ps', 'Philippines' => 'ph',
        'Qatar' => 'qa', 'Singapour' => 'sg', 'Sri Lanka' => 'lk', 'Syrie' => 'sy', 'Tadjikistan' => 'tj',
        'Taïwan' => 'tw', 'Thaïlande' => 'th', 'Timor oriental' => 'tl', 'Turkménistan' => 'tm', 'Turquie' => 'tr',
        'Viêt Nam' => 'vn', 'Yémen' => 'ye',
        // Océanie
        'Australie' => 'au', 'Fidji' => 'fj', 'Îles Marshall' => 'mh', 'Îles Salomon' => 'sb', 'Kiribati' => 'ki',
        'Micronésie' => 'fm', 'Nauru' => 'nr', 'Nouvelle-Zélande' => 'nz', 'Palaos' => 'pw',
        'Papouasie-Nouvelle-Guinée' => 'pg', 'Samoa' => 'ws', 'Tonga' => 'to', 'Tuvalu' => 'tv', 'Vanuatu' => 'vu',
        // Territoires d'outre-mer (diaspora)
        'Guadeloupe' => 'gp', 'Guyane' => 'gf', 'La Réunion' => 're', 'Martinique' => 'mq', 'Mayotte' => 'yt',
        'Nouvelle-Calédonie' => 'nc', 'Polynésie française' => 'pf',
    ];

    /** Indicatif téléphonique international (sans « + ») de chaque code ISO. */
    private const DIAL = [
        'za' => '27', 'dz' => '213', 'ao' => '244', 'bj' => '229', 'bw' => '267', 'bf' => '226', 'bi' => '257',
        'cm' => '237', 'cv' => '238', 'km' => '269', 'cg' => '242', 'cd' => '243', 'ci' => '225', 'dj' => '253',
        'eg' => '20', 'er' => '291', 'et' => '251', 'ga' => '241', 'gm' => '220', 'gh' => '233', 'gn' => '224',
        'gw' => '245', 'gq' => '240', 'ke' => '254', 'ls' => '266', 'lr' => '231', 'ly' => '218', 'mg' => '261',
        'mw' => '265', 'ml' => '223', 'ma' => '212', 'mu' => '230', 'mr' => '222', 'mz' => '258', 'na' => '264',
        'ne' => '227', 'ng' => '234', 'ug' => '256', 'cf' => '236', 'rw' => '250', 'st' => '239', 'sn' => '221',
        'sc' => '248', 'sl' => '232', 'so' => '252', 'sd' => '249', 'ss' => '211', 'sz' => '268', 'tz' => '255',
        'td' => '235', 'tg' => '228', 'tn' => '216', 'zm' => '260', 'zw' => '263',
        'al' => '355', 'de' => '49', 'ad' => '376', 'at' => '43', 'be' => '32', 'by' => '375', 'ba' => '387',
        'bg' => '359', 'cy' => '357', 'hr' => '385', 'dk' => '45', 'es' => '34', 'ee' => '372', 'fi' => '358',
        'fr' => '33', 'gr' => '30', 'hu' => '36', 'ie' => '353', 'is' => '354', 'it' => '39', 'xk' => '383',
        'lv' => '371', 'li' => '423', 'lt' => '370', 'lu' => '352', 'mk' => '389', 'mt' => '356', 'md' => '373',
        'mc' => '377', 'me' => '382', 'no' => '47', 'nl' => '31', 'pl' => '48', 'pt' => '351', 'cz' => '420',
        'ro' => '40', 'gb' => '44', 'ru' => '7', 'sm' => '378', 'rs' => '381', 'sk' => '421', 'si' => '386',
        'se' => '46', 'ch' => '41', 'ua' => '380', 'va' => '39',
        'ag' => '1', 'ar' => '54', 'bs' => '1', 'bb' => '1', 'bz' => '501', 'bo' => '591', 'br' => '55', 'ca' => '1',
        'cl' => '56', 'co' => '57', 'cr' => '506', 'cu' => '53', 'dm' => '1', 'ec' => '593', 'us' => '1', 'gd' => '1',
        'gt' => '502', 'gy' => '592', 'ht' => '509', 'hn' => '504', 'jm' => '1', 'mx' => '52', 'ni' => '505',
        'pa' => '507', 'py' => '595', 'pe' => '51', 'do' => '1', 'kn' => '1', 'lc' => '1', 'vc' => '1', 'sv' => '503',
        'sr' => '597', 'tt' => '1', 'uy' => '598', 've' => '58',
        'af' => '93', 'sa' => '966', 'am' => '374', 'az' => '994', 'bh' => '973', 'bd' => '880', 'bt' => '975',
        'mm' => '95', 'bn' => '673', 'kh' => '855', 'cn' => '86', 'kp' => '850', 'kr' => '82', 'ae' => '971',
        'ge' => '995', 'in' => '91', 'id' => '62', 'iq' => '964', 'ir' => '98', 'il' => '972', 'jp' => '81',
        'jo' => '962', 'kz' => '7', 'kg' => '996', 'kw' => '965', 'la' => '856', 'lb' => '961', 'my' => '60',
        'mv' => '960', 'mn' => '976', 'np' => '977', 'om' => '968', 'uz' => '998', 'pk' => '92', 'ps' => '970',
        'ph' => '63', 'qa' => '974', 'sg' => '65', 'lk' => '94', 'sy' => '963', 'tj' => '992', 'tw' => '886',
        'th' => '66', 'tl' => '670', 'tm' => '993', 'tr' => '90', 'vn' => '84', 'ye' => '967',
        'au' => '61', 'fj' => '679', 'mh' => '692', 'sb' => '677', 'ki' => '686', 'fm' => '691', 'nr' => '674',
        'nz' => '64', 'pw' => '680', 'pg' => '675', 'ws' => '685', 'to' => '676', 'tv' => '688', 'vu' => '678',
        'gp' => '590', 'gf' => '594', 're' => '262', 'mq' => '596', 'yt' => '262', 'nc' => '687', 'pf' => '689',
    ];

    /** @var list<string>|null */
    private static ?array $sorted = null;

    /** @return list<string> ordre alphabétique français, sans tenir compte des accents */
    public static function all(): array
    {
        if (self::$sorted === null) {
            $names = array_keys(self::CODES);
            usort($names, fn ($a, $b) => strcmp(self::key($a), self::key($b)));
            self::$sorted = $names;
        }

        return self::$sorted;
    }

    /** Valeurs acceptées : la liste, plus l'éventuelle valeur déjà enregistrée (anciens comptes, application). */
    public static function allowed(?string $current = null): array
    {
        $current = trim((string) $current);

        return $current !== '' && !in_array($current, self::all(), true)
            ? [...self::all(), $current]
            : self::all();
    }

    /** Code ISO en minuscules (« ci »), ou null pour un nom inconnu. */
    public static function code(?string $name): ?string
    {
        return self::CODES[trim((string) $name)] ?? null;
    }

    /** Adresse de l'image du drapeau, ou null. */
    public static function flagUrl(?string $name): ?string
    {
        $code = self::code($name);

        return $code ? asset("flags/{$code}.svg") : null;
    }

    /** Indicatif (« 229 ») d'un code ISO, ou null. */
    public static function dialCode(?string $code): ?string
    {
        return self::DIAL[strtolower(trim((string) $code))] ?? null;
    }

    /** Nom du pays d'un code ISO, ou null. */
    public static function nameOf(?string $code): ?string
    {
        $name = array_search(strtolower(trim((string) $code)), self::CODES, true);

        return $name === false ? null : $name;
    }

    /** @return list<string> codes utilisés (pour vérifier que chaque drapeau existe) */
    public static function codes(): array
    {
        return array_values(self::CODES);
    }

    private static function key(string $name): string
    {
        return Str::lower(Str::ascii($name));
    }
}
