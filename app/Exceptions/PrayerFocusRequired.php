<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Prière enregistrée trop tôt : le temps de prière n'a pas été commencé, ou n'est pas encore écoulé.
 * Réponse JSON 422 (API et site en fetch) ; retour à la page avec un message pour un formulaire classique.
 * Voir docs/fonctionnalites/temps-de-priere.md
 */
class PrayerFocusRequired extends RuntimeException
{
    public function __construct(public readonly int $remainingSeconds, public readonly bool $started)
    {
        parent::__construct('Temps de prière non écoulé.');
    }

    /** Message détaillé, avec le temps restant (« Encore 3 min 20 s… »). */
    public function detail(): string
    {
        if (!$this->started) {
            return __('prayer_focus.not_started');
        }

        $minutes = intdiv($this->remainingSeconds, 60);
        $seconds = $this->remainingSeconds % 60;
        $time = $minutes > 0
            ? __('prayer_focus.time_min_sec', ['min' => $minutes, 'sec' => str_pad((string) $seconds, 2, '0', STR_PAD_LEFT)])
            : __('prayer_focus.time_sec', ['sec' => $seconds]);

        return __('prayer_focus.too_early', ['time' => $time]);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success'           => false,
                'message'           => __('prayer_focus.required'),
                'errors'            => ['prayer_focus' => [$this->detail()]],
                'remaining_seconds' => $this->remainingSeconds,
            ], 422);
        }

        return back()->with('error', $this->detail());
    }
}
