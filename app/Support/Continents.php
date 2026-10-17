<?php

namespace App\Support;

/**
 * Continent d'un pays (code ISO 3166-1 alpha-2) : AF, EU, AS, NA (Amérique du Nord et centrale,
 * Caraïbes), SA, OC, AN. Sert au classement des fils par zone (docs/fonctionnalites/localisation.md).
 */
final class Continents
{
    /** Codes des continents ; noms affichés dans lang/{fr,en}/countries.php (« continents »). */
    public const CODES = ['AF', 'EU', 'AS', 'NA', 'SA', 'OC', 'AN'];

    private const LISTS = [
        'AF' => 'dz ao bj bw bf bi cm cv cf td km cg cd ci dj eg gq er et ga gm gh gn gw ke ls lr ly mg mw ml mr mu yt ma mz na ne ng re rw sh st sn sc sl so za ss sd sz tz tg tn ug eh zm zw',
        'EU' => 'al ad at by be ba bg hr cy cz dk ee fo fi fr de gi gr gg hu is ie im it je xk lv li lt lu mt md mc me nl mk no pl pt ro ru sm rs sk si es sj se ch ua gb va ax',
        'AS' => 'af am az bh bd bt bn kh cn ge hk in id ir iq il jo kz kw kg la lb mo my mv mn mm np kp om pk ps ph qa sa sg kr lk sy tw tj th tl tr tm ae uz vn ye',
        'NA' => 'ag ai aw bs bb bz bm bq vg ca ky cr cu cw dm do sv gl gd gp gt ht hn jm mq mx ms ni pa pr bl kn lc mf pm vc sx tt tc us vi',
        'SA' => 'ar bo br cl co ec fk gf gy py pe sr uy ve',
        'OC' => 'as au ck fj pf gu ki mh fm nr nc nz nu nf mp pw pg pn ws sb tk to tv vu wf',
        'AN' => 'aq',
    ];

    /** @var array<string, string>|null */
    private static ?array $map = null;

    /** Code du continent (« AF ») d'un code pays (« bj »), ou null. */
    public static function of(?string $countryCode): ?string
    {
        if (self::$map === null) {
            self::$map = [];
            foreach (self::LISTS as $continent => $codes) {
                foreach (explode(' ', $codes) as $code) self::$map[$code] = $continent;
            }
        }

        return self::$map[strtolower(trim((string) $countryCode))] ?? null;
    }

    public static function name(?string $continent): ?string
    {
        $continent = strtoupper((string) $continent);

        return in_array($continent, self::CODES, true) ? __("countries.continents.{$continent}") : null;
    }
}
