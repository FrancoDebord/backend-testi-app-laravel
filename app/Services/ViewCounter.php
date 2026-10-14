<?php

namespace App\Services;

use App\Models\Testimony;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Compteur de vues des témoignages : une vue par personne et par témoignage sur
 * config('testimonies.view_dedup_minutes') minutes (30 par défaut). La personne est reconnue par son
 * compte, sinon son appareil (X-Device-Id / device_id), sa session, ou son IP et son navigateur
 * (TestimonyStats::viewerKey). Chaque vue comptée alimente les statistiques par jour.
 * Utilisé par le site (pages du témoignage) et l'API (GET /testimonies/{id}, POST /testimonies/{id}/view).
 * Voir docs/fonctionnalites/gestion-des-temoignages.md
 */
class ViewCounter
{
    /**
     * Enregistre la vue si elle n'a pas déjà été comptée. Renvoie vrai si le compteur a augmenté.
     * $history = false : l'appelant enregistre lui-même l'historique de lecture (Recommendations).
     */
    public function record(Testimony $testimony, Request $request, bool $history = true): bool
    {
        // Carnet privé : seul l'auteur le lit, ses lectures ne sont pas des vues. Non publié : relecture.
        if ($testimony->isInJournal() || $testimony->status !== \App\Enums\TestimonyStatus::Approved) {
            return false;
        }

        $viewer = TestimonyStats::viewerKey($request);

        // Cache::add est atomique : deux requêtes simultanées ne comptent qu'une vue.
        $key = 'testimony-view:' . $testimony->id . ':' . $viewer;
        if (!Cache::add($key, true, now()->addMinutes((int) config('testimonies.view_dedup_minutes', 30)))) {
            return false;
        }

        $testimony->increment('views_count');
        app(TestimonyStats::class)->recordView($testimony, $viewer);
        // Historique de la personne connectée : centres d'intérêt et « déjà vu » (docs/fonctionnalites/recommandations.md)
        if ($history) {
            Recommendations::recordView($request->user() ?? $request->user('sanctum'), $testimony);
        }

        return true;
    }
}
