<?php

namespace App\Services;

use App\Enums\LiveStatus;
use App\Models\ContentLocation;
use App\Models\Event;
use App\Models\Prophecy;
use App\Models\User;
use App\Support\Countries;
use Illuminate\Support\Collection;

/**
 * Éléments insérés entre les témoignages de « Mon fil » (site et application), comme sur l'accueil :
 * événements à venir, sessions de prière programmées, requêtes de prière et paroles prophétiques
 * rendues publiques avec leur témoignage d'accomplissement.
 *
 * Deux groupes : `following` (comptes suivis et ses propres publications) puis `others` (les autres comptes),
 * PER_KIND éléments au plus par type et par groupe, du plus proche au plus lointain de la personne
 * (zone : village, ville, région, pays, continent) puis selon ses goûts (docs/fonctionnalites/localisation.md). Ordre d'insertion (mixed()) : les sources
 * alternent (événement, session, requête, parole…), les comptes suivis d'abord.
 * Même ordre dans l'application (lib/shared/content/feed_mix.dart, mixInserts).
 * Voir docs/fonctionnalites/recommandations.md
 */
class FeedInserts
{
    public const PER_KIND = 5;

    /**
     * Clés des listes, dans l'ordre d'alternance. `events` : à venir et en cours ; `pastEvents` :
     * terminés depuis moins de PAST_EVENT_DAYS jours (« revivre » : témoignages et commentaires).
     */
    public const KINDS = ['events', 'prayerSessions', 'prayerRequests', 'prophecies', 'pastEvents'];

    public const PAST_EVENT_DAYS = 90;

    public function __construct(
        private readonly Recommendations $recommendations,
        private readonly PrayerRequests $prayers,
        private readonly PrayerSessions $sessions,
        private readonly Locations $locations,
        private readonly ContentInteractions $interactions,
    ) {}

    /** Relations d'un événement attendues par EventResource et events.partials.card. */
    public static function eventRelations(): array
    {
        return [
            'organizer', 'images', 'managers',
            'lives' => fn ($q) => $q->whereIn('status', [LiveStatus::Live->value, LiveStatus::Preparing->value])->latest(),
        ];
    }

    /** Candidats lus par type et par groupe avant le classement par zone (PER_KIND gardés). */
    public const CANDIDATES = 20;

    /**
     * Listes par groupe, chacune classée du plus proche au plus lointain de la personne puis selon
     * ses goûts (auteurs aimés), l'ordre d'origine (date) départageant (rankByPlace).
     * @return array{following: array<string, Collection>, others: array<string, Collection>}
     */
    public function lists(User $viewer): array
    {
        $followed = $this->recommendations->followedIds($viewer);
        $exclude  = [...$followed, $viewer->id];
        $n = self::CANDIDATES;
        $place = $this->locations->placeFor($viewer);

        $events = fn () => Event::query()->published()->upcoming()->with(self::eventRelations());
        // Terminés récemment (pas les annulés) : revivre l'événement, ses témoignages, ses commentaires.
        $pastEvents = fn () => Event::query()->published()->past()
            ->where('status', '!=', \App\Enums\EventStatus::Cancelled->value)
            ->where('ends_at', '>=', now()->subDays(self::PAST_EVENT_DAYS))
            ->with(self::eventRelations());
        $sessions = fn () => $this->sessions->list($viewer, 'upcoming');
        $prophecies = fn () => Prophecy::query()->where('is_public', true)->whereNotNull('testimony_id')
            ->whereHas('testimony', fn ($t) => $t->published())
            ->with(['user', 'testimony.user'])
            ->orderByDesc('fulfilled_on')->orderByDesc('updated_at');
        // Présélection par pays (sans calcul sur toute la table) : ajoutée aux plus proches dans le temps.
        $inCountry = function ($query, string $kind) use ($place, $n) {
            if (!$place->countryCode || $kind === 'prophecies') return collect();
            if ($kind === 'events') {
                return $query->whereIn('country', array_filter([Countries::nameOf($place->countryCode), $place->countryCode]))->limit($n)->get();
            }
            $type = $kind === 'prayerSessions' ? ContentLocation::PRAYER_SESSION : ContentLocation::PRAYER_REQUEST;
            return $query->whereIn('id', ContentLocation::query()->select('subject_id')
                ->where('subject_type', $type)->where('country_code', $place->countryCode))->limit($n)->get();
        };
        $pick = fn (string $kind, \Closure $query) => $this->rankByPlace(
            $kind, $query()->limit($n)->get()->concat($inCountry($query(), $kind))->unique('id')->values(), $viewer, self::PER_KIND);

        // Partagés par les comptes suivis (30 derniers jours) : rejoignent le groupe « suivis »
        // avec le bandeau « X a partagé » (docs/fonctionnalites/partage-interne.md).
        $shared = [
            'events'         => $this->interactions->repostedByFollowed('event', $followed)->all(),
            'prayerSessions' => $this->interactions->repostedByFollowed('prayer_session', $followed)->all(),
            'prayerRequests' => $this->interactions->repostedByFollowed('prayer_request', $followed)->all(),
        ];
        // Comptes suivis et soi-même : ses propres événements, sessions, requêtes et paroles y figurent aussi.
        $authors = [...$followed, $viewer->id];
        $byFollowedOrShared = fn (string $column, string $kind) => fn ($q) => $q->whereIn($column, $authors)->orWhereIn('id', $shared[$kind]);

        $followingPrayers = $pick('prayerRequests', fn () => $this->prayers->list($viewer, 'following'))
            // Ses propres requêtes (même privées : lui seul les voit).
            ->concat($this->prayers->list($viewer, 'mine')->limit(self::PER_KIND)->get())
            ->concat($shared['prayerRequests']
                ? $this->prayers->list($viewer, 'feed')->whereIn('id', $shared['prayerRequests'])->limit($n)->get()
                : collect())
            ->unique('id')->sortByDesc('created_at')->take(self::PER_KIND)->values();

        $following = [
            'events'         => $pick('events', fn () => $events()->where($byFollowedOrShared('organizer_id', 'events'))),
            'prayerSessions' => $pick('prayerSessions', fn () => $sessions()->where($byFollowedOrShared('host_id', 'prayerSessions'))),
            'prayerRequests' => $followingPrayers,
            'prophecies'     => $pick('prophecies', fn () => $prophecies()->whereIn('user_id', $authors)),
            'pastEvents'     => $pick('events', fn () => $pastEvents()->where($byFollowedOrShared('organizer_id', 'events'))),
        ];
        $notShown = fn (string $kind) => $following[$kind]->pluck('id')->all();

        return [
            'following' => $following,
            'others' => [
                'events'         => $pick('events', fn () => $events()->whereNotIn('organizer_id', $exclude)->whereNotIn('id', $notShown('events'))),
                'prayerSessions' => $pick('prayerSessions', fn () => $sessions()->whereNotIn('host_id', $exclude)->whereNotIn('id', $notShown('prayerSessions'))),
                // Requêtes publiques (les anonymes restent anonymes), sans celles déjà montrées.
                'prayerRequests' => $pick('prayerRequests', fn () => $this->prayers->list($viewer, 'feed')
                    ->where('user_id', '!=', $viewer->id)
                    ->whereNotIn('id', $notShown('prayerRequests'))),
                'prophecies'     => $pick('prophecies', fn () => $prophecies()->whereNotIn('user_id', $exclude)),
                'pastEvents'     => $pick('events', fn () => $pastEvents()->whereNotIn('organizer_id', $exclude)->whereNotIn('id', $notShown('pastEvents'))),
            ],
        ];
    }

    /**
     * Classe des éléments insérés du plus proche au plus lointain de la personne, puis selon ses goûts
     * (auteurs aimés), l'ordre reçu départageant ; garde les $limit premiers.
     * Lieu : événement → ses coordonnées / ville / pays ; session ou requête liée à un événement → le lieu
     * de l'événement ; sinon la copie enregistrée à la création, à défaut le lieu actuel de l'auteur.
     * Sans zone connue ni goût, l'ordre reçu est conservé.
     */
    public function rankByPlace(string $kind, Collection $items, ?User $viewer, int $limit): Collection
    {
        $place = $this->locations->placeFor($viewer);
        $authorTaste = $this->recommendations->tastes($viewer)['authors'];
        if ($items->isEmpty() || ($place->isEmpty() && !$authorTaste)) return $items->take($limit)->values();

        $authorKey = match ($kind) { 'events' => 'organizer_id', 'prayerSessions' => 'host_id', default => 'user_id' };
        $places = [];
        if (!$place->isEmpty()) {
            if ($kind === 'events') {
                foreach ($items as $e) $places[$e->id] = Locations::eventPlace($e);
            } elseif ($kind === 'prophecies') {
                $byAuthor = $this->locations->authorPlaces($items->pluck($authorKey)->all());
                foreach ($items as $i) $places[$i->id] = $byAuthor[$i->{$authorKey}] ?? null;
            } else {
                $type = $kind === 'prayerSessions' ? ContentLocation::PRAYER_SESSION : ContentLocation::PRAYER_REQUEST;
                $places = $this->locations->contentPlaces($type, $items, $authorKey);
                foreach ($items as $i) {
                    if (($i->event_id ?? null) && ($ep = Locations::eventPlace($i->event))) $places[$i->id] = $ep;
                }
            }
        }

        $w = config('recommendations.weights');

        return $items->values()
            ->map(fn ($item, $index) => [$item, round(
                ($w['proximity'] ?? 1.6) * $place->levelTo($places[$item->id] ?? null)
                + ($w['author'] ?? 2) * ($authorTaste[$item->{$authorKey}] ?? 0), 3), $index])
            ->sort(fn ($a, $b) => [$b[1], $a[2]] <=> [$a[1], $b[2]])
            ->take($limit)
            ->map(fn ($r) => $r[0])
            ->values();
    }

    /** Liste unique dans l'ordre d'insertion : les comptes suivis d'abord, les sources en alternance. */
    public function mixed(User $viewer): Collection
    {
        $lists = $this->lists($viewer);

        return self::interleave(array_map(fn ($k) => $lists['following'][$k], self::KINDS))
            ->concat(self::interleave(array_map(fn ($k) => $lists['others'][$k], self::KINDS)))
            ->values();
    }

    /** a0, b0, c0, a1, b1… (les plus longues continuent seules). */
    public static function interleave(array $lists): Collection
    {
        $lists = array_map(fn ($l) => collect($l)->values(), $lists);
        $out = collect();
        $longest = max([0, ...array_map(fn ($l) => $l->count(), $lists)]);
        for ($i = 0; $i < $longest; $i++) {
            foreach ($lists as $l) {
                if ($l->has($i)) $out->push($l[$i]);
            }
        }

        return $out;
    }
}
