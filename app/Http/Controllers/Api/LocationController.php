<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Locations;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Réglage « Utiliser ma position pour personnaliser mes fils » (privé : seulement la personne).
 *   GET    /users/me/location  → réglage et zone utilisée
 *   PUT    /users/me/location  { use_gps?, latitude?, longitude?, locality?, city?, region?, country_code?, home_city? }
 *   DELETE /users/me/location  → effacement immédiat
 * Voir docs/fonctionnalites/localisation.md
 */
class LocationController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly Locations $locations) {}

    public function show(Request $request): JsonResponse
    {
        return $this->success($this->locations->payload($request->user()));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'use_gps'      => ['nullable', 'boolean'],
            'latitude'     => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude'    => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'locality'     => ['nullable', 'string', 'max:120'],
            'city'         => ['nullable', 'string', 'max:120'],
            'region'       => ['nullable', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'home_city'    => ['nullable', 'string', 'max:120'],
        ]);
        $user = $request->user();

        if ($request->has('home_city')) {
            $this->locations->setHomeCity($user, $data['home_city'] ?? null);
        }
        if ($request->has('use_gps') && !$request->boolean('use_gps')) {
            $this->locations->setUseGps($user, false);
        } elseif (isset($data['latitude'], $data['longitude'])) {
            // Envoyer des coordonnées vaut consentement explicite (réglage activé dans l'application ou le site).
            $this->locations->updateFromCoordinates($user, (float) $data['latitude'], (float) $data['longitude'], $data);
        } elseif ($request->boolean('use_gps')) {
            $this->locations->setUseGps($user, true);
        }

        return $this->success($this->locations->payload($user), __('profile.location.api_updated'));
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->locations->erase($request->user());

        return $this->success($this->locations->payload($request->user()), __('profile.location.api_erased'));
    }
}
