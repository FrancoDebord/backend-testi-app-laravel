<?php

namespace App\Services;

use App\Enums\LiveStatus;
use App\Models\Event;
use App\Models\Prophecy;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Éléments insérés entre les témoignages de « Mon fil » (site et application), comme sur l'accueil :
 * événements à venir, sessions de prière programmées, requêtes de prière et paroles prophétiques
 * rendues publiques avec leur témoignage d'accomplissement.
 *
 * Deux groupes : `following` (comptes suivis) puis `others` (les autres comptes, jamais les siens),
 * PER_KIND éléments au plus par type et par groupe. Ordre d'insertion (mixed()) : les sources
 * alternent (événement, session, requête, parole…), les comptes suivis d'abord.
 * Même ordre dans l'application (lib/shared/content/feed_mix.dart, mixInserts).
 * Voir docs/fonctionnalites/recommandations.md
 */
class FeedInserts
{
    public const PER_KIND = 5;

    /** Clés des listes, dans l'ordre d'alternance. */
    public const KINDS = ['events', 'prayerSessions', 'prayerRequests', 'prophecies'];

    public function __construct(
        private readonly Recommendations $recommendations,
        private readonly PrayerRequests $prayers,
        private readonly PrayerSessions $sessions,
    ) {}

    /** Relations d'un événement attendues par EventResource et events.partials.card. */
    public static function eventRelations(): array
    {
        return [
            'organizer', 'images', 'managers',
            'lives' => fn ($q) => $q->whereIn('status', [LiveStatus::Live->value, LiveStatus::Preparing->value])->latest(),
        ];
    }

    /** @return array{following: array<string, Collection>, others: array<string, Collection>} */
    public function lists(User $viewer): array
    {
        $followed = $this->recommendations->followedIds($viewer);
        $exclude  = [...$followed, $viewer->id];
        $n = self::PER_KIND;

        $events = fn () => Event::query()->published()->upcoming()->with(self::eventRelations());
        $sessions = fn () => $this->sessions->list($viewer, 'upcoming');
        $prophecies = fn () => Prophecy::query()->where('is_public', true)->whereNotNull('testimony_id')
            ->whereHas('testimony', fn ($t) => $t->published())
            ->with(['user', 'testimony.user'])
            ->orderByDesc('fulfilled_on')->orderByDesc('updated_at');

        $followingPrayers = $followed ? $this->prayers->list($viewer, 'following')->limit($n)->get() : collect();

        return [
            'following' => [
                'events'         => $followed ? $events()->whereIn('organizer_id', $followed)->limit($n)->get() : collect(),
                'prayerSessions' => $followed ? $sessions()->whereIn('host_id', $followed)->limit($n)->get() : collect(),
                'prayerRequests' => $followingPrayers,
                'prophecies'     => $followed ? $prophecies()->whereIn('user_id', $followed)->limit($n)->get() : collect(),
            ],
            'others' => [
                'events'         => $events()->whereNotIn('organizer_id', $exclude)->limit($n)->get(),
                'prayerSessions' => $sessions()->whereNotIn('host_id', $exclude)->limit($n)->get(),
                // Requêtes publiques (les anonymes restent anonymes), sans celles déjà montrées.
                'prayerRequests' => $this->prayers->list($viewer, 'feed')
                    ->where('user_id', '!=', $viewer->id)
                    ->whereNotIn('id', $followingPrayers->pluck('id'))
                    ->limit($n)->get(),
                'prophecies'     => $prophecies()->whereNotIn('user_id', $exclude)->limit($n)->get(),
            ],
        ];
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
