<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestimonyResource;
use App\Models\User;
use App\Services\ConversionTestimonies;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Témoignage de conversion d'un compte (docs/fonctionnalites/temoignage-de-conversion.md).
 * Création et modification : POST / PUT /testimonies avec kind = conversion ; liste : GET /testimonies?kind=conversion.
 */
class ConversionTestimonyController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ConversionTestimonies $conversions) {}

    // GET /users/{id}/conversion-testimony  (public) : le témoignage publié, 404 s'il n'y en a pas.
    public function ofUser(Request $request, string $id): JsonResponse
    {
        $author = User::find($id);
        if (!$author) {
            return $this->notFound();
        }
        $testimony = $this->conversions->publishedOf($id);
        // Témoignage réservé aux abonnés : seulement pour qui peut le voir.
        if (!$testimony || !$testimony->isVisibleTo($request->user() ?? $request->user('sanctum'))) {
            return $this->notFound(ConversionTestimonies::label('not_found', $author));
        }

        return $this->success(new TestimonyResource($testimony->load('user')));
    }

    /**
     * GET /me/conversion-testimony : le mien, quel que soit son statut (en attente, refusé…) ; data = null s'il
     * n'existe pas. can_create : compte sans témoignage de conversion (genèse du ministère pour une organisation).
     */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        $testimony = $this->conversions->of($user);

        return response()->json([
            'success'    => true,
            'data'       => $testimony ? (new TestimonyResource($testimony->load('user')))->resolve($request) : null,
            'can_create' => !$testimony && ConversionTestimonies::canPublish($user),
            'message'    => $testimony ? '' : ConversionTestimonies::label('none_yet', $user),
        ]);
    }
}
