<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Locations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Site : réglage « Utiliser ma position pour personnaliser mes fils » (Paramètres du compte).
 * La position vient de navigator.geolocation (HTTPS), envoyée seulement après un clic de la personne.
 * Voir docs/fonctionnalites/localisation.md
 */
class LocationController extends Controller
{
    public function __construct(private readonly Locations $locations) {}

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'use_gps'   => ['nullable', 'boolean'],
            'latitude'  => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'home_city' => ['nullable', 'string', 'max:120'],
        ]);
        $user = $request->user();

        $this->locations->setHomeCity($user, $data['home_city'] ?? null);
        if (!$request->boolean('use_gps')) {
            $this->locations->setUseGps($user, false);
        } elseif (isset($data['latitude'], $data['longitude'])) {
            $this->locations->updateFromCoordinates($user, (float) $data['latitude'], (float) $data['longitude']);
        } else {
            $this->locations->setUseGps($user, true);
        }

        if ($request->expectsJson()) return response()->json(['data' => $this->locations->payload($user)]);

        return redirect()->to(route('profile.settings') . '#localisation')->with('success', __('profile.location.updated'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->locations->erase($request->user());

        return redirect()->to(route('profile.settings') . '#localisation')->with('success', __('profile.location.erased'));
    }
}
