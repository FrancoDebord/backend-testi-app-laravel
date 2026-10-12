<?php

namespace App\Providers;

use App\Models\ContentLocation;
use App\Models\PrayerRequest;
use App\Models\PrayerSession;
use App\Models\Testimony;
use App\Models\User;
use App\Services\Locations;
use Illuminate\Support\ServiceProvider;

/**
 * Copie grossière du lieu de l'auteur sur chaque contenu créé (témoignage, requête et session de
 * prière ; session ou requête liée à un événement : lieu de l'événement), pour classer les fils
 * par zone. Les événements gardent leurs propres colonnes. Voir docs/fonctionnalites/localisation.md
 */
class LocationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $snapshot = function (string $type, string $authorKey) {
            return function ($model) use ($type, $authorKey) {
                try {
                    $locations = app(Locations::class);
                    $place = ($model->event_id ?? null) ? Locations::eventPlace($model->event) : null;
                    $locations->snapshot($type, $model->id, $model->{$authorKey}, $place);
                } catch (\Throwable $e) {
                    // Le classement par zone ne doit jamais empêcher une publication.
                    report($e);
                }
            };
        };

        Testimony::created($snapshot(ContentLocation::TESTIMONY, 'user_id'));
        PrayerRequest::created($snapshot(ContentLocation::PRAYER_REQUEST, 'user_id'));
        PrayerSession::created($snapshot(ContentLocation::PRAYER_SESSION, 'host_id'));

        // Compte supprimé (même en suppression douce) : sa position est effacée tout de suite.
        User::deleted(fn (User $user) => app(Locations::class)->erase($user));

        $forget = fn ($model) => ContentLocation::where('subject_id', $model->id)->delete();
        Testimony::forceDeleted($forget);
        PrayerRequest::forceDeleted($forget);
        PrayerSession::forceDeleted($forget);
    }
}
