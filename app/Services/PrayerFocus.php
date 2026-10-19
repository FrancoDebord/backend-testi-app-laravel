<?php

namespace App\Services;

use App\Exceptions\PrayerFocusRequired;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Temps de prière : « Je prie » n'est enregistré qu'après config('prayer.focus_seconds') secondes
 * (5 min par défaut) passées dans l'écran de prière. Le début est noté côté serveur (cache, 6 h) à l'appel
 * …/pray/start. Un nouvel appel pendant l'heure qui suit GARDE le premier début : un appel en double ou tardif
 * (double appui, écran recréé, requête lente) ne remet plus le décompte à zéro — sinon « encore 4 min 54 s »
 * s'affichait alors que l'écran avait fini. Une marge de 10 s couvre la latence réseau.
 * Un début ne vaut que pour une prière : il est consommé quand la prière est enregistrée.
 * Voir docs/fonctionnalites/temps-de-priere.md
 */
class PrayerFocus
{
    /**
     * Sujets concernés : requête de prière (« Je prie » dédié et réactions), témoignage (réactions du groupe
     * « je prie »), verset du jour (réaction `pray`), parole prophétique (« J'ai prié »), événement et
     * session de prière (réactions du groupe « je prie », ContentInteractions).
     */
    public const KINDS = ['prayer_request', 'testimony', 'daily_verse', 'prophecy', 'event', 'prayer_session'];

    /** Marge accordée à l'application (latence, horloges). */
    public const TOLERANCE_SECONDS = 10;

    private const TTL_HOURS = 6;

    /** Durée pendant laquelle un début reste valable pour un nouvel appel de …/pray/start (sinon il recommence). */
    public const RESTART_AFTER_SECONDS = 3600;

    public static function requiredSeconds(): int
    {
        return max(0, (int) config('prayer.focus_seconds', 300));
    }

    public static function enabled(): bool
    {
        return self::requiredSeconds() > 0;
    }

    /**
     * Commence le temps de prière ; un début de moins d'une heure, pas encore consommé, est gardé.
     *
     * @return array{startedAt: string, requiredSeconds: int, confirmableAt: string}
     */
    public function start(User $user, string $kind, string $id): array
    {
        $key = $this->key($user, $kind, $id);
        $now = Carbon::now();
        $previous = Cache::get($key);
        if (is_numeric($previous) && (int) $previous <= $now->getTimestamp()
            && $now->getTimestamp() - (int) $previous < self::RESTART_AFTER_SECONDS) {
            $startedAt = Carbon::createFromTimestamp((int) $previous);
        } else {
            $startedAt = $now;
            Cache::put($key, $startedAt->getTimestamp(), now()->addHours(self::TTL_HOURS));
        }

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
            // Diagnostic : un refus juste après la fin du décompte de l'écran signale un début reçu en retard.
            Log::info('Temps de prière : prière refusée, temps non écoulé.', [
                'user_id' => $user->id, 'kind' => $kind, 'id' => $id, 'elapsed' => $elapsed, 'required' => $required,
            ]);
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
