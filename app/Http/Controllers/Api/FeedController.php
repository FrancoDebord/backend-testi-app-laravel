<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Http\Resources\PrayerRequestResource;
use App\Http\Resources\PrayerSessionResource;
use App\Http\Resources\PublicProphecyResource;
use App\Services\FeedInserts;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Éléments insérés dans « Mon fil » (GET /feed/inserts) : événements à venir, sessions de prière
 * programmées, requêtes de prière, paroles prophétiques publiques — comptes suivis d'abord.
 * Les témoignages restent servis par GET /feed. Voir docs/fonctionnalites/recommandations.md
 */
class FeedController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly FeedInserts $inserts) {}

    public function inserts(Request $request): JsonResponse
    {
        $lists = $this->inserts->lists($request->user());
        $format = fn (array $group) => [
            'events'         => EventResource::collection($group['events'])->resolve($request),
            'prayerSessions' => PrayerSessionResource::collection($group['prayerSessions'])->resolve($request),
            'prayerRequests' => PrayerRequestResource::collection($group['prayerRequests'])->resolve($request),
            'prophecies'     => PublicProphecyResource::collection($group['prophecies'])->resolve($request),
        ];

        return $this->success([
            'following' => $format($lists['following']),
            'others'    => $format($lists['others']),
        ]);
    }
}
