<?php

namespace App\Http\Controllers\Web;

use App\Enums\LiveStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\LiveSession;
use App\Models\User;
use App\Services\PrayerRequests;
use App\Services\Recommendations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * « Mon fil » : témoignages des comptes suivis, avec une suggestion tous les trois
 * (App\Services\Recommendations::personalFeed, partagé avec l'API mobile),
 * les directs en cours en tête et les événements à venir des comptes suivis insérés dans la liste.
 */
class FeedController extends Controller
{
    private const PER_PAGE = 24;

    public function __construct(
        private readonly Recommendations $recommendations,
        private readonly PrayerRequests $prayers,
    ) {}

    public function personal(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $page = max(1, (int) $request->query('page', 1));
        [$items, $reasons] = $this->recommendations->personalFeed($user, $page, self::PER_PAGE);
        $items->withPath(route('feed.personal'));

        // Insérés entre les témoignages (videos/partials/cards). Liste complète à chaque page :
        // l'élément affiché dépend de la position dans le fil entier.
        $inserts = $this->followedInserts($user);

        // « Afficher plus » : seules les cartes suivantes sont renvoyées (resources/js/videos.js).
        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('videos.partials.cards', [
                    'items' => $items, 'routeName' => 'testimonies.show', 'encourage' => true, 'reasons' => $reasons,
                    'inserts' => $inserts,
                ])->render(),
                'next' => $items->nextPageUrl(),
            ]);
        }

        $followsSomeone = $user->following()->exists();
        $lives = LiveSession::with('host')->onAir()->latest('started_at')->limit(6)->get()
            ->filter(fn ($l) => $l->isVisibleTo($user));

        return view('feed.personal', compact('items', 'reasons', 'followsSomeone', 'inserts', 'lives'));
    }

    /**
     * Éléments insérés, en alternance : événements publiés à venir et requêtes de prière
     * (non anonymes) des comptes suivis — 10 de chaque au plus. Même ordre que l'application.
     */
    private function followedInserts(User $user): Collection
    {
        $events = Event::query()->published()->upcoming()->followedBy($user)
            ->with(['organizer', 'images', 'lives' => fn ($l) => $l->where('status', LiveStatus::Live->value)->latest()])
            ->limit(10)->get()->values();
        $prayers = $this->prayers->list($user, 'following')->limit(10)->get()->values();

        $mixed = collect();
        for ($i = 0, $n = max($events->count(), $prayers->count()); $i < $n; $i++) {
            if (isset($events[$i]))  $mixed->push($events[$i]);
            if (isset($prayers[$i])) $mixed->push($prayers[$i]);
        }

        return $mixed;
    }
}
