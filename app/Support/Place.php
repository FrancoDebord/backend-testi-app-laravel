<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Lieu grossier d'une personne ou d'un contenu (jamais plus précis qu'environ 1 km) :
 * coordonnées arrondies à 2 décimales, village/quartier, ville, région, pays, continent.
 * `levelTo()` donne le niveau de proximité (5 = même village/quartier … 0 = monde).
 * Voir docs/fonctionnalites/localisation.md
 */
final class Place
{
    public const LEVEL_NEAR      = 5;
    public const LEVEL_CITY      = 4;
    public const LEVEL_REGION    = 3;
    public const LEVEL_COUNTRY   = 2;
    public const LEVEL_CONTINENT = 1;
    public const LEVEL_WORLD     = 0;

    public function __construct(
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?string $locality = null,
        public readonly ?string $city = null,
        public readonly ?string $region = null,
        public readonly ?string $countryCode = null,
        public readonly ?string $continent = null,
        public readonly ?string $source = null,
    ) {}

    /** Construit un lieu en arrondissant et en complétant le continent. */
    public static function make(
        ?float $latitude = null, ?float $longitude = null, ?string $locality = null, ?string $city = null,
        ?string $region = null, ?string $countryCode = null, ?string $continent = null, ?string $source = null,
    ): self {
        $cc = $countryCode ? strtolower(trim($countryCode)) : null;
        $hasCoords = $latitude !== null && $longitude !== null;

        return new self(
            $hasCoords ? self::round($latitude) : null,
            $hasCoords ? self::round($longitude) : null,
            self::clean($locality), self::clean($city), self::clean($region),
            $cc ?: null,
            $continent ? strtoupper($continent) : Continents::of($cc),
            $source,
        );
    }

    /** Depuis une ligne user_locations / content_locations (objet ou modèle). */
    public static function fromRow(object $row): self
    {
        return self::make(
            isset($row->latitude) ? (float) $row->latitude : null,
            isset($row->longitude) ? (float) $row->longitude : null,
            $row->locality ?? null, $row->city ?? null, $row->region ?? null,
            $row->country_code ?? null, $row->continent ?? null, $row->source ?? null,
        );
    }

    /** Arrondi à 2 décimales (≈ 1,1 km) : jamais de position précise enregistrée. */
    public static function round(float $value): float
    {
        return round($value, 2);
    }

    public function isEmpty(): bool
    {
        return $this->countryCode === null && $this->city === null && !$this->hasCoordinates();
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** Colonnes à enregistrer (user_locations, content_locations). */
    public function toColumns(): array
    {
        return [
            'latitude' => $this->latitude, 'longitude' => $this->longitude,
            'locality' => $this->locality, 'city' => $this->city, 'region' => $this->region,
            'country_code' => $this->countryCode, 'continent' => $this->continent,
            'source' => $this->source,
        ];
    }

    /** Clé courte du lieu (caches par personne et par zone). */
    public function key(): string
    {
        if ($this->isEmpty()) return 'none';

        return substr(md5(implode('|', [
            $this->latitude, $this->longitude, self::norm($this->locality), self::norm($this->city),
            self::norm($this->region), $this->countryCode, $this->continent,
        ])), 0, 12);
    }

    /** Nom affiché de la zone la plus précise connue (« Cotonou », « Bénin »). */
    public function label(): ?string
    {
        return $this->city ?? $this->locality ?? $this->region
            ?? Countries::nameOf($this->countryCode) ?? Continents::name($this->continent);
    }

    /** Niveau de zone le plus précis connu : near | city | region | country | continent | null. */
    public function precision(): ?string
    {
        return match (true) {
            $this->locality !== null && $this->city !== null => 'locality',
            $this->city !== null => 'city',
            $this->region !== null => 'region',
            $this->countryCode !== null => 'country',
            $this->continent !== null => 'continent',
            default => null,
        };
    }

    /** Niveau de proximité avec un autre lieu (5 à 0) ; la distance réelle compte quand les deux ont des coordonnées. */
    public function levelTo(?self $other): int
    {
        if (!$other || $this->isEmpty() || $other->isEmpty()) return self::LEVEL_WORLD;

        $level = self::LEVEL_WORLD;
        $sameCountry = $this->countryCode !== null && $this->countryCode === $other->countryCode;
        $sameCity = $this->city !== null && self::norm($this->city) === self::norm($other->city)
            && ($sameCountry || $this->countryCode === null || $other->countryCode === null);

        if ($this->continent !== null && $this->continent === $other->continent) $level = self::LEVEL_CONTINENT;
        if ($sameCountry) $level = self::LEVEL_COUNTRY;
        if ($sameCountry && $this->region !== null && self::norm($this->region) === self::norm($other->region)) $level = self::LEVEL_REGION;
        if ($sameCity) $level = self::LEVEL_CITY;
        if ($sameCity && $this->locality !== null && self::norm($this->locality) === self::norm($other->locality)) $level = self::LEVEL_NEAR;

        if ($this->hasCoordinates() && $other->hasCoordinates()) {
            $km = $this->distanceKm($other);
            $limits = config('recommendations.distance_km');
            $byDistance = match (true) {
                $km < ($limits['near'] ?? 5)     => self::LEVEL_NEAR,
                $km < ($limits['city'] ?? 30)    => self::LEVEL_CITY,
                $km < ($limits['region'] ?? 150) => self::LEVEL_REGION,
                default => self::LEVEL_WORLD,
            };
            $level = max($level, $byDistance);
        }

        return $level;
    }

    /** Distance à vol d'oiseau (haversine), en km. */
    public function distanceKm(self $other): float
    {
        $rad = M_PI / 180;
        $dLat = ($other->latitude - $this->latitude) * $rad;
        $dLng = ($other->longitude - $this->longitude) * $rad;
        $a = sin($dLat / 2) ** 2 + cos($this->latitude * $rad) * cos($other->latitude * $rad) * sin($dLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Étiquette discrète d'une carte proche de la personne : « À Cotonou » (même ville ou plus près),
     * « Au Bénin » (même région ou même pays) ; null au-delà.
     */
    public function nearbyLabelFor(int $level): ?string
    {
        if ($level >= self::LEVEL_CITY && $this->city) return 'À ' . $this->city;
        if ($level >= self::LEVEL_COUNTRY && $this->countryCode) return Countries::inPhrase($this->countryCode);

        return null;
    }

    public static function norm(?string $value): string
    {
        return Str::lower(Str::ascii(trim((string) $value)));
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 120);
    }
}
