<?php

namespace App\Services;

use App\Models\Testimony;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Compteur de vues des témoignages : une vue par personne et par témoignage
 * sur une fenêtre de WINDOW_HOURS heures. La personne est reconnue par son
 * compte, sinon par sa session (ou, à défaut, son adresse IP).
 */
class ViewCounter
{
    public const WINDOW_HOURS = 6;

    /** Enregistre la vue si elle n'a pas déjà été comptée. Renvoie vrai si le compteur a augmenté. */
    public function record(Testimony $testimony, Request $request): bool
    {
        // Carnet privé : seul l'auteur le lit, ses lectures ne sont pas des vues.
        if ($testimony->isInJournal()) {
            return false;
        }

        $viewer = $request->user()?->id
            ?? 'guest:' . ($request->hasSession() ? $request->session()->getId() : $request->ip());

        // Cache::add est atomique : deux requêtes simultanées ne comptent qu'une vue.
        $key = 'testimony-view:' . $testimony->id . ':' . sha1($viewer);
        if (!Cache::add($key, true, now()->addHours(self::WINDOW_HOURS))) {
            return false;
        }

        $testimony->increment('views_count');
        // Historique de la personne connectée : centres d'intérêt et « déjà vu » (docs/fonctionnalites/recommandations.md)
        Recommendations::recordView($request->user(), $testimony);

        return true;
    }
}
