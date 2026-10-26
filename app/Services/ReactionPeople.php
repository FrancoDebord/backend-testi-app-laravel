<?php

namespace App\Services;

use App\Enums\ReactionType;
use App\Enums\UserAccountStatus;
use App\Models\Comment;
use App\Models\DailyVerse;
use App\Models\DailyVerseComment;
use App\Models\EventComment;
use App\Models\PrayerRequestMessage;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * « Qui a réagi ? » : effectif de chaque réaction (j'aime, je prie…) d'une publication et liste des
 * personnes, pour l'API et le popup du site. Seuls les comptes actifs sont comptés et listés.
 * Requête de prière : le « Je prie » dédié (prayer_request_prayers) est compté comme la réaction `pray`.
 * Onglet « Commentaires » (filtre `comments`) : commentaires visibles de comptes actifs, les plus récents d'abord
 * (requête de prière : ses encouragements). Voir docs/fonctionnalites/qui-a-reagi.md
 */
class ReactionPeople
{
    public const PER_PAGE = 30;

    /** Types de publication acceptés (API et site). */
    public const KINDS = ['testimony', 'event', 'prayer_request', 'prayer_session', 'daily_verse'];

    /** Icône Font Awesome de chaque réaction (site : pas d'émoji dans l'interface). */
    public const ICONS = [
        'like'    => 'fa-thumbs-up',
        'love'    => 'fa-heart',
        'fire'    => 'fa-fire',
        'glory'   => 'fa-star',
        'strong'  => 'fa-hand-fist',
        'touched' => 'fa-face-smile-beam',
        'pray'    => 'fa-hands-praying',
        'amen'    => 'fa-hands',
        'worship' => 'fa-hands',
    ];

    /** Publication visible de $viewer, sinon null (404). */
    public function find(string $kind, string $id, ?User $viewer): ?Model
    {
        $subject = match ($kind) {
            'testimony'   => Str::isUuid($id) ? Testimony::find($id) : null,
            'daily_verse' => ctype_digit($id) ? DailyVerse::find($id) : null,
            'event', 'prayer_request', 'prayer_session' => Str::isUuid($id) ? ContentInteractions::KINDS[$kind][0]::find($id) : null,
            default       => null,
        };
        if ($subject && method_exists($subject, 'isVisibleTo') && !$subject->isVisibleTo($viewer)) {
            return null;
        }

        return $subject;
    }

    /** Filtre valide : null (toutes), un groupe (appreciation, prayer), un type de réaction ou `comments`. */
    public static function isFilter(?string $filter): bool
    {
        return $filter === null || in_array($filter, ['all', 'appreciation', 'prayer', 'comments'], true) || ReactionType::tryFrom($filter) !== null;
    }

    /**
     * Effectifs : personnes au total, par groupe et par réaction (réactions présentes seulement, dans l'ordre de ReactionType).
     *
     * `commentsCount` : commentaires visibles (onglet « Commentaires »).
     *
     * @return array{total: int, likesCount: int, prayersCount: int, commentsCount: int, types: list<array{type: string, group: string, label: string, emoji: string, icon: string, count: int}>}
     */
    public function summary(string $kind, Model $subject): array
    {
        $counts = $this->base($kind, $subject)->selectRaw('r.type, COUNT(DISTINCT r.user_id) as total')->groupBy('r.type')->pluck('total', 'type');
        $groupCount = fn (string $group) => $this->base($kind, $subject)->whereIn('r.type', ReactionType::valuesOfGroup($group))->distinct()->count('r.user_id');

        $types = [];
        foreach (ReactionType::cases() as $type) {
            if ((int) ($counts[$type->value] ?? 0) === 0) continue;
            $types[] = [
                'type'  => $type->value,
                'group' => $type->group(),
                'label' => self::label($type),
                'emoji' => $type->emoji(),
                'icon'  => self::ICONS[$type->value],
                'count' => (int) $counts[$type->value],
            ];
        }

        return [
            'total'        => $this->base($kind, $subject)->distinct()->count('r.user_id'),
            'likesCount'   => $groupCount('appreciation'),
            'prayersCount' => $groupCount('prayer'),
            'commentsCount' => $this->commentQuery($kind, $subject)?->count() ?? 0,
            'types'        => $types,
        ];
    }

    /**
     * Personnes ayant réagi (la plus récente d'abord), une ligne par personne avec ses réactions.
     * `$filter` : null / 'all', un groupe ou un type.
     *
     * `prayerTimes` : nombre de fois que la personne a prié (0 si elle ne prie pas ; docs/fonctionnalites/prier-plusieurs-fois.md).
     *
     * @return LengthAwarePaginator<array{user: User, types: list<ReactionType>, prayerTimes: int, reactedAt: ?string}>
     */
    public function people(string $kind, Model $subject, ?string $filter = null, int $page = 1, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $types = match (true) {
            $filter === null || $filter === 'all'           => null,
            in_array($filter, ['appreciation', 'prayer'], true) => ReactionType::valuesOfGroup($filter),
            default                                         => [$filter],
        };

        $paginator = $this->base($kind, $subject)
            ->when($types, fn ($q) => $q->whereIn('r.type', $types))
            ->selectRaw('r.user_id, MAX(r.reacted_at) as reacted_at')
            ->groupBy('r.user_id')
            ->orderByDesc('reacted_at')->orderBy('r.user_id')
            ->paginate($perPage, ['*'], 'page', max(1, $page));

        $ids = collect($paginator->items())->pluck('user_id')->all();
        $users = User::whereIn('id', $ids)->get()->keyBy('id');
        // Toutes les réactions de ces personnes (même filtrées) : l'onglet « J'aime » montre aussi qu'elles prient.
        $theirRows = $this->base($kind, $subject)->whereIn('r.user_id', $ids)->get(['r.user_id', 'r.type', 'r.prayer_times'])->groupBy('user_id');
        $prayerTypes = ReactionType::valuesOfGroup('prayer');

        return $paginator->setCollection(collect($paginator->items())
            ->filter(fn ($row) => $users->has($row->user_id))
            ->map(fn ($row) => [
                'user'      => $users[$row->user_id],
                'types'     => collect(ReactionType::cases())->filter(fn ($t) => ($theirRows[$row->user_id] ?? collect())->contains('type', $t->value))->values()->all(),
                'prayerTimes' => (int) ($theirRows[$row->user_id] ?? collect())->whereIn('type', $prayerTypes)->max('prayer_times'),
                'reactedAt' => $row->reacted_at ? \Illuminate\Support\Carbon::parse($row->reacted_at)->toIso8601String() : null,
            ])->values());
    }

    /**
     * Commentaires de la publication, les plus récents d'abord (onglet « Commentaires »).
     *
     * @return LengthAwarePaginator<array{id: string, user: User, body: string, createdAt: ?string}>
     */
    public function comments(string $kind, Model $subject, int $page = 1, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $query = $this->commentQuery($kind, $subject);
        if (!$query) {
            return new LengthAwarePaginator([], 0, $perPage, max(1, $page));
        }

        $paginator = $query->with('user')->latest()->paginate($perPage, ['*'], 'page', max(1, $page));

        return $paginator->setCollection($paginator->getCollection()->map(fn (Model $c) => [
            'id'        => (string) $c->id,
            'user'      => $c->user,
            'body'      => Mentions::plain((string) $c->body),
            'createdAt' => $c->created_at?->toIso8601String(),
        ]));
    }

    /** Commentaires visibles de comptes actifs (null : pas de commentaires pour ce type, ex. session de prière). */
    private function commentQuery(string $kind, Model $subject): ?\Illuminate\Database\Eloquent\Builder
    {
        $query = match ($kind) {
            'testimony'      => Comment::where('testimony_id', $subject->id),
            'daily_verse'    => DailyVerseComment::where('daily_verse_id', $subject->id)->whereNull('hidden_at'),
            'prayer_request' => PrayerRequestMessage::where('prayer_request_id', $subject->id),
            'event'          => EventComment::where('event_id', $subject->id),
            default          => null,
        };

        return $query?->whereHas('user', fn ($u) => $u->where('status', UserAccountStatus::Active->value));
    }

    /** Libellé d'une réaction (« Je prie » plutôt que « Prière »). */
    public static function label(ReactionType $type): string
    {
        return $type === ReactionType::Pray ? __('reactions.i_pray') : $type->label();
    }

    /** Réactions de la publication (user_id, type, prayer_times, reacted_at) de comptes actifs. */
    private function base(string $kind, Model $subject): Builder
    {
        return DB::query()->fromSub($this->rows($kind, $subject), 'r')
            ->join('users', 'users.id', '=', 'r.user_id')
            ->where('users.status', UserAccountStatus::Active->value)
            ->whereNull('users.deleted_at');
    }

    private function rows(string $kind, Model $subject): Builder
    {
        return match ($kind) {
            'testimony'   => DB::table('reactions')->where('testimony_id', $subject->id)->select(['user_id', 'type', 'prayer_times', 'created_at as reacted_at']),
            'daily_verse' => DB::table('daily_verse_reactions')->where('daily_verse_id', $subject->id)->select(['user_id', 'type', 'prayer_times', 'created_at as reacted_at']),
            'prayer_request' => $this->contentRows($kind, $subject)->unionAll(
                DB::table('prayer_request_prayers')->where('prayer_request_id', $subject->id)->selectRaw("user_id, 'pray' as type, prayer_times, created_at as reacted_at")
            ),
            default       => $this->contentRows($kind, $subject),
        };
    }

    private function contentRows(string $kind, Model $subject): Builder
    {
        return DB::table('content_reactions')->where('subject_type', $kind)->where('subject_id', $subject->id)
            ->select(['user_id', 'type', 'prayer_times', 'created_at as reacted_at']);
    }
}
