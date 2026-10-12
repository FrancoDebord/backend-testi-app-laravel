<?php

namespace App\Services;

use App\Models\ContentLocation;
use App\Models\Event;
use App\Models\User;
use App\Models\UserLocation;
use App\Support\Countries;
use App\Support\Place;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Localisation grossière des personnes et des contenus, pour classer les fils par zone.
 *
 * Lieu d'une personne, par ordre de préférence :
 *   1. position GPS de l'appareil, avec consentement explicite (`use_gps`), arrondie à ≈ 1 km ;
 *   2. ville (« Ma ville », user_locations.home_city) et pays du profil (users.country) ;
 *   3. pays déduit : indicatif du téléphone (users.phone_country), puis langue (Accept-Language « fr-BJ ») ;
 *   4. aucun (classement sans zone).
 * Géocodage inverse côté serveur (Nominatim, mis en cache 30 jours par coordonnées arrondies, 1 requête/s).
 * Voir docs/fonctionnalites/localisation.md
 */
class Locations
{
    public const CACHE_SECONDS = 600;

    // ─── Lieu d'une personne ────────────────────────────────────────────────

    public function stored(User $user): ?UserLocation
    {
        return UserLocation::find($user->id);
    }

    /** Lieu effectif d'une personne (ou d'un visiteur, d'après sa langue). */
    public function placeFor(?User $user, ?Request $request = null): Place
    {
        $request ??= app()->bound('request') ? request() : null;

        if ($user) {
            $place = Cache::remember(self::placeCacheKey($user->id), self::CACHE_SECONDS,
                fn () => $this->authorPlaces([$user->id])[$user->id] ?? null);
            if ($place instanceof Place && !$place->isEmpty()) return $place;
        }

        $cc = self::countryFromLanguage($request?->header('Accept-Language'))
            ?? (config('recommendations.guest_default_country') ?: null);

        return $cc ? Place::make(countryCode: $cc, source: 'language') : new Place();
    }

    public static function placeCacheKey(string $userId): string
    {
        return "loc:place:{$userId}";
    }

    /**
     * Lieux de plusieurs personnes (auteurs), sans la langue du navigateur.
     * @param  list<string>  $userIds
     * @return array<string, Place>
     */
    public function authorPlaces(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter($userIds)));
        if (!$userIds) return [];

        $rows  = UserLocation::whereIn('user_id', $userIds)->get()->keyBy('user_id');
        $users = DB::table('users')->whereIn('id', $userIds)->get(['id', 'country', 'phone_country', 'organization_city'])->keyBy('id');
        $out = [];
        foreach ($userIds as $id) {
            $row = $rows->get($id);
            $u   = $users->get($id);
            $countryCode = Countries::code($u?->country) ?? (strlen((string) $u?->country) === 2 ? strtolower($u->country) : null);
            if ($row && $row->use_gps && $row->latitude !== null && $row->longitude !== null) {
                // Géocodage indisponible : le pays du profil complète les coordonnées.
                $out[$id] = Place::fromRow((object) [
                    ...$row->only(['latitude', 'longitude', 'locality', 'city', 'region', 'continent']),
                    'country_code' => $row->country_code ?? $countryCode ?? $u?->phone_country, 'source' => 'gps',
                ]);
                continue;
            }
            $city = $row?->home_city ?: ($u?->organization_city ?: null);
            if ($countryCode || $city) {
                $out[$id] = Place::make(city: $city, countryCode: $countryCode, source: 'profile');
                continue;
            }
            if ($u?->phone_country) {
                $out[$id] = Place::make(countryCode: $u->phone_country, source: 'phone');
            }
        }

        return $out;
    }

    /** Pays d'un en-tête Accept-Language (« fr-BJ,fr;q=0.9 » → « bj »), ou null. */
    public static function countryFromLanguage(?string $header): ?string
    {
        foreach (explode(',', (string) $header) as $part) {
            $tag = trim(explode(';', $part)[0]);
            if (preg_match('/^[a-z]{2,3}[-_]([a-z]{2})$/i', $tag, $m)) {
                $cc = strtolower($m[1]);
                if (\App\Support\Continents::of($cc)) return $cc;
            }
        }

        return null;
    }

    // ─── Réglage « Utiliser ma position » ──────────────────────────────────

    /**
     * Enregistre la position de l'appareil (consentement donné) : arrondie à 2 décimales, puis géocodée.
     * `$hints` : noms connus de l'appareil (locality, city, region, country_code), utilisés si le
     * géocodage du serveur est indisponible.
     */
    public function updateFromCoordinates(User $user, float $latitude, float $longitude, array $hints = []): UserLocation
    {
        $lat = Place::round($latitude);
        $lng = Place::round($longitude);
        $names = $this->reverseGeocode($lat, $lng) ?? array_intersect_key($hints, array_flip(['locality', 'city', 'region', 'country_code']));
        $place = Place::make($lat, $lng, $names['locality'] ?? null, $names['city'] ?? null,
            $names['region'] ?? null, $names['country_code'] ?? null, null, 'gps');

        $row = UserLocation::updateOrCreate(['user_id' => $user->id], [
            ...$place->toColumns(), 'use_gps' => true, 'location_updated_at' => now(),
        ]);
        $this->forget($user);

        return $row;
    }

    /** Active ou désactive le réglage ; désactiver efface immédiatement la position GPS. */
    public function setUseGps(User $user, bool $enabled): ?UserLocation
    {
        if ($enabled) {
            $row = UserLocation::firstOrCreate(['user_id' => $user->id]);
            $row->update(['use_gps' => true]);
        } else {
            $row = $this->eraseGps($user);
        }
        $this->forget($user);

        return $row;
    }

    /** « Ma ville » (sans GPS). */
    public function setHomeCity(User $user, ?string $city): UserLocation
    {
        $city = trim((string) $city);
        $row = UserLocation::updateOrCreate(['user_id' => $user->id], ['home_city' => $city === '' ? null : mb_substr($city, 0, 120)]);
        $this->forget($user);

        return $row;
    }

    /** « Supprimer ma position » : tout est effacé immédiatement (y compris la copie GPS sur ses contenus). */
    public function erase(User $user): void
    {
        UserLocation::where('user_id', $user->id)->delete();
        ContentLocation::where('user_id', $user->id)->where('source', 'gps')->delete();
        $this->forget($user);
    }

    private function eraseGps(User $user): ?UserLocation
    {
        ContentLocation::where('user_id', $user->id)->where('source', 'gps')->delete();
        $row = UserLocation::find($user->id);
        $row?->update([
            'use_gps' => false, 'latitude' => null, 'longitude' => null, 'locality' => null, 'city' => null,
            'region' => null, 'country_code' => null, 'continent' => null, 'source' => null, 'location_updated_at' => null,
        ]);

        return $row;
    }

    /** Vide les caches de la personne (lieu, fils classés). */
    public function forget(User $user): void
    {
        Cache::forget(self::placeCacheKey($user->id));
        Cache::forget(Recommendations::personalCacheKey($user->id));
    }

    /** Réglage tel que renvoyé à la personne elle-même (GET /users/me/location). */
    public function payload(User $user): array
    {
        $row   = $this->stored($user);
        $place = $this->placeFor($user);

        return [
            'useGps'      => (bool) $row?->use_gps,
            'source'      => $place->isEmpty() ? null : $place->source,
            'label'       => $place->label(),
            'precision'   => $place->precision(),
            'locality'    => $place->locality,
            'city'        => $place->city,
            'region'      => $place->region,
            'countryCode' => $place->countryCode,
            'country'     => Countries::nameOf($place->countryCode),
            'continent'   => $place->continent,
            'latitude'    => $place->latitude,
            'longitude'   => $place->longitude,
            'homeCity'    => $row?->home_city,
            'updatedAt'   => $row?->location_updated_at?->toIso8601String(),
        ];
    }

    // ─── Géocodage inverse ─────────────────────────────────────────────────

    /** Noms (locality, city, region, country_code) de coordonnées arrondies, ou null. */
    public function reverseGeocode(float $lat, float $lng): ?array
    {
        $config = config('recommendations.geocoder');
        if (!($config['enabled'] ?? false)) return null;

        $key = sprintf('geo:rev:%.2f:%.2f', $lat, $lng);
        if (is_array($cached = Cache::get($key))) return $cached;

        // Politique d'usage de Nominatim : une requête par seconde au plus pour tout le serveur.
        if (RateLimiter::tooManyAttempts('geocoder', 1)) return null;
        RateLimiter::hit('geocoder', 1);

        try {
            $res = Http::withHeaders(['User-Agent' => $config['user_agent']])->timeout(5)
                ->get($config['url'], [
                    'format' => 'jsonv2', 'lat' => $lat, 'lon' => $lng, 'zoom' => 14,
                    'addressdetails' => 1, 'accept-language' => 'fr',
                ]);
            if (!$res->successful()) return null;
            $a = (array) ($res->json('address') ?? []);
            $names = [
                'locality'     => $a['suburb'] ?? $a['neighbourhood'] ?? $a['quarter'] ?? $a['village'] ?? $a['hamlet'] ?? null,
                'city'         => $a['city'] ?? $a['town'] ?? $a['municipality'] ?? $a['village'] ?? $a['county'] ?? null,
                'region'       => $a['state'] ?? $a['region'] ?? $a['state_district'] ?? $a['county'] ?? null,
                'country_code' => isset($a['country_code']) ? strtolower($a['country_code']) : null,
            ];
            if ($names['locality'] === $names['city']) $names['locality'] = null;
            Cache::put($key, $names, now()->addDays((int) ($config['cache_days'] ?? 30)));

            return $names;
        } catch (\Throwable $e) {
            Log::info('Géocodage inverse indisponible : ' . $e->getMessage());
            return null;
        }
    }

    // ─── Lieu des contenus ─────────────────────────────────────────────────

    /** Copie grossière du lieu actuel de l'auteur sur un contenu nouvellement créé (si connu). */
    public function snapshot(string $type, string $subjectId, ?string $authorId, ?Place $place = null): void
    {
        $place ??= $authorId ? ($this->authorPlaces([$authorId])[$authorId] ?? null) : null;
        if (!$place || $place->isEmpty()) return;

        ContentLocation::updateOrCreate(
            ['subject_type' => $type, 'subject_id' => $subjectId],
            [...$place->toColumns(), 'user_id' => $authorId],
        );
    }

    /** Lieu d'un événement (ses propres colonnes). */
    public static function eventPlace(?Event $event): ?Place
    {
        if (!$event) return null;
        $country = trim((string) $event->country);
        $cc = Countries::code($country) ?? (strlen($country) === 2 ? strtolower($country) : null);
        $place = Place::make(
            $event->latitude !== null ? (float) $event->latitude : null,
            $event->longitude !== null ? (float) $event->longitude : null,
            null, $event->city, null, $cc, null, 'event',
        );

        return $place->isEmpty() ? null : $place;
    }

    /**
     * Lieux de contenus : la copie enregistrée, sinon le lieu actuel de l'auteur.
     * @param  iterable<object{id: string, user_id?: string}>  $items  (id et identifiant de l'auteur)
     * @return array<string, Place>
     */
    public function contentPlaces(string $type, iterable $items, string $authorKey = 'user_id'): array
    {
        $authors = [];
        foreach ($items as $item) $authors[(string) $item->id] = $item->{$authorKey} ?? null;
        if (!$authors) return [];

        $out = [];
        ContentLocation::where('subject_type', $type)->whereIn('subject_id', array_keys($authors))->get()
            ->each(function ($row) use (&$out) { $out[$row->subject_id] = Place::fromRow($row); });

        $missing = array_diff_key($authors, $out);
        if ($missing) {
            $places = $this->authorPlaces(array_values(array_filter($missing)));
            foreach ($missing as $id => $author) {
                if ($author && isset($places[$author])) $out[$id] = $places[$author];
            }
        }

        return $out;
    }
}
