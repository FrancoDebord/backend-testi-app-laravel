<?php

namespace App\Services;

use App\Exceptions\PrayerFocusRequired;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Temps de prière : « Je prie » n'est enregistré qu'après config('prayer.focus_seconds') secondes
 * (5 min par défaut) passées dans l'écran de prière. Le début est noté côté serveur (cache, 6 h) à l'appel
 * …/pray/start ; un nouvel appel recommence le décompte. Une marge de 10 s couvre la latence réseau.
 * Un début ne vaut que pour une prière : il est consommé quand la prière est enregistrée.
 * Voir docs/fonctionnalites/temps-de-priere.md
 */
class PrayerFocus
{
    public const KINDS = ['prayer_request', 'testimony'];

    /** Marge accordée à l'application (latence, horloges). */
    public const TOLERANCE_SECONDS = 10;

    private const TTL_HOURS = 6;

    public static function requiredSeconds(): int
    {
        return max(0, (int) config('prayer.focus_seconds', 300));
    }

    public static function enabled(): bool
    {
        return self::requiredSeconds() > 0;
    }

    /**
     * Commence (ou recommence) le temps de prière.
     *
     * @return array{startedAt: string, requiredSeconds: int, confirmableAt: string}
     */
    public function start(User $user, string $kind, string $id): array
    {
        $startedAt = Carbon::now();
        Cache::put($this->key($user, $kind, $id), $startedAt->getTimestamp(), now()->addHours(self::TTL_HOURS));

        $required = self::requiredSeconds();

        return [
            'startedAt'       => $startedAt->toIso8601String(),
            'requiredSeconds' => $required,
            'confirmableAt'   => $startedAt->copy()->addSeconds($required)->toIso8601String(),
        ];
    }

    /**
     * Exige un temps de prière écoulé avant d'enregistrer une prière, puis le consomme.
     *
     * @throws PrayerFocusRequired 422 : pas commencé, ou temps restant
     */
    public function ensureCompleted(User $user, string $kind, string $id): void
    {
        if (!self::enabled()) {
            return;
        }

        $key = $this->key($user, $kind, $id);
        $startedAt = Cache::get($key);
        $required = self::requiredSeconds();

        if (!is_numeric($startedAt)) {
            throw new PrayerFocusRequired($required, started: false);
        }

        $elapsed = Carbon::now()->getTimestamp() - (int) $startedAt;
        $minimum = max(0, $required - self::TOLERANCE_SECONDS);
        if ($elapsed < $minimum) {
            throw new PrayerFocusRequired(max(1, $required - $elapsed), started: true);
        }

        Cache::forget($key);
    }

    private function key(User $user, string $kind, string $id): string
    {
        if (!in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException("Type de temps de prière inconnu : {$kind}");
        }

        return "prayer_focus:{$kind}:{$id}:{$user->id}";
    }
}
