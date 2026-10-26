<?php

namespace App\Services;

use App\Enums\UserAccountStatus;
use App\Models\BibleBook;
use App\Models\BibleVerse;
use App\Models\DailyVerse;
use App\Models\DailyVerseComment;
use App\Models\DailyVerseCommentReport;
use App\Models\DailyVerseReaction;
use App\Models\Testimony;
use App\Models\User;
use App\Support\BibleReference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Verset du jour : versions de la Bible, réactions, commentaires, témoignages liés.
 * Règles communes à l'API mobile et au site. Voir docs/fonctionnalites/verset-du-jour.md
 */
class DailyVerses
{
    public const REACTIONS = ['like' => 'like_count', 'pray' => 'prayer_count', 'amen' => 'amen_count'];

    public const REPORT_REASONS = [
        'inappropriate_content' => 'Contenu inapproprié',
        'hate_speech'           => 'Propos haineux',
        'spam'                  => 'Publicité ou spam',
        'other'                 => 'Autre raison',
    ];

    /** Version proposée par défaut (texte du verset saisi par l'équipe = Louis Segond). */
    public const DEFAULT_TRANSLATION = 'LSG';

    /** Verset d'une date (AAAA-MM-JJ) ou du jour ; null si la date est invalide ou sans verset. */
    public function forDate(?string $date = null): ?DailyVerse
    {
        if (blank($date)) {
            return DailyVerse::today();
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
            return null;
        }

        return DailyVerse::forDate($date);
    }

    // ─── Versions de la Bible ────────────────────────────────────────────────

    /** Traductions installées : [code => ['code', 'name', 'language', 'langName']]. */
    public function translations(): Collection
    {
        $registry = config('bible.translations', []);

        return BibleBook::query()->select('translation')->distinct()->orderBy('translation')->pluck('translation')
            ->mapWithKeys(fn (string $code) => [$code => [
                'code'     => $code,
                'name'     => $registry[$code]['name'] ?? $code,
                'language' => $registry[$code]['language'] ?? null,
                'langName' => $registry[$code]['lang_name'] ?? null,
            ]])
            // Le français d'abord, puis l'ordre alphabétique.
            ->sortBy(fn ($t) => ($t['language'] === 'fr' ? '0' : '1') . $t['code']);
    }

    /**
     * Le verset (ou la plage) dans chaque traduction installée. `available` = false si la traduction
     * n'a pas ce passage (texte null). Sans référence reconnue : seule la version saisie est renvoyée.
     */
    public function versions(DailyVerse $verse, ?string $only = null): array
    {
        $ref = $verse->parsedReference();
        $translations = $this->translations();
        if ($only !== null) {
            $translations = $translations->only([strtoupper($only)]);
        }

        $rows = $ref
            ? BibleVerse::query()
                ->whereIn('translation', $translations->keys())
                ->where('book', $ref['book'])->where('chapter', $ref['chapter'])
                ->whereBetween('verse', [$ref['verseStart'], $ref['verseEnd']])
                ->orderBy('verse')->get()->groupBy('translation')
            : collect();

        // Nom du livre dans la langue de chaque traduction (« Jean » en LSG, « John » en KJV).
        $bookNames = $ref
            ? BibleBook::whereIn('translation', $translations->keys())->where('number', $ref['book'])->pluck('name', 'translation')
            : collect();

        $versions = $translations->values()->map(function (array $t) use ($rows, $ref, $bookNames, $verse) {
            $verses = ($rows[$t['code']] ?? collect())->map(fn (BibleVerse $v) => ['verse' => $v->verse, 'text' => self::clean($v->text)])->values();
            $bookName = $ref ? ($bookNames[$t['code']] ?? BibleReference::bookName($ref['book'])) : null;

            return [
                ...$t,
                'bookName'  => $bookName,
                'reference' => $ref ? BibleReference::label($ref, $bookName) : $verse->reference,
                'available' => $verses->isNotEmpty(),
                'text'      => $verses->isNotEmpty() ? $verses->pluck('text')->implode(' ') : null,
                'verses'    => $verses->all(),
            ];
        })->all();

        return [
            'reference' => $verse->reference,
            'parsed'    => $ref ? [...$ref, 'bookName' => BibleReference::bookName($ref['book']), 'label' => BibleReference::label($ref)] : null,
            // Texte saisi par l'équipe, toujours disponible (repli hors traductions).
            'original'  => ['code' => null, 'name' => 'Texte du jour', 'text' => $verse->verse_text],
            'defaultTranslation' => self::DEFAULT_TRANSLATION,
            'versions'  => $versions,
        ];
    }

    // ─── Réactions ───────────────────────────────────────────────────────────

    /**
     * Ajoute (true) ou retire (false) une réaction ; sans précision, bascule. Renvoie l'état final.
     * Ajouter « Je prie » (`pray`) exige un temps de prière écoulé (PrayerFocus, 422) ; `like` et `amen`
     * ne sont pas concernés, retirer n'est jamais bloqué (docs/fonctionnalites/temps-de-priere.md).
     */
    public function setReaction(DailyVerse $verse, User $user, string $type, ?bool $on = null, bool $again = false): bool
    {
        $field = self::REACTIONS[$type] ?? throw new PrayerActionException('Réaction inconnue.', 422);
        $this->ensureActive($user);
        $again = $again && $type === 'pray';

        return DB::transaction(function () use ($verse, $user, $type, $on, $field, $again) {
            $existing = DailyVerseReaction::where(['daily_verse_id' => $verse->id, 'user_id' => $user->id, 'type' => $type])->first();
            $target   = $again ?: ($on ?? !$existing);
            if ($target && $existing && $again) {
                // Prier encore : une prière de plus (prayer_count, nombre de personnes, ne change pas).
                // Voir docs/fonctionnalites/prier-plusieurs-fois.md
                app(PrayerFocus::class)->ensureCompleted($user, 'daily_verse', (string) $verse->id);
                $existing->increment('prayer_times');
            } elseif ($target && !$existing) {
                if ($type === 'pray') {
                    app(PrayerFocus::class)->ensureCompleted($user, 'daily_verse', (string) $verse->id);
                }
                DailyVerseReaction::create(['daily_verse_id' => $verse->id, 'user_id' => $user->id, 'type' => $type]);
                $verse->increment($field);
            } elseif (!$target && $existing) {
                $existing->delete();
                DailyVerse::whereKey($verse->id)->where($field, '>', 0)->decrement($field);
            }

            return $target;
        });
    }

    /** Retire la dernière prière (« Je prie ») de la personne : une de moins, ou plus de réaction. Renvoie les prières restantes. */
    public function removeLastPrayer(DailyVerse $verse, User $user): int
    {
        $existing = DailyVerseReaction::where(['daily_verse_id' => $verse->id, 'user_id' => $user->id, 'type' => 'pray'])->first();
        if (!$existing || $existing->prayer_times <= 1) {
            $this->setReaction($verse, $user, 'pray', false);
            return 0;
        }
        $existing->decrement('prayer_times');

        return $existing->prayer_times;
    }

    /** Nombre de fois que la personne a prié sur le verset (0 si elle ne prie pas). */
    public function prayerTimes(DailyVerse $verse, ?User $user): int
    {
        return $user ? (int) DailyVerseReaction::where(['daily_verse_id' => $verse->id, 'user_id' => $user->id, 'type' => 'pray'])->value('prayer_times') : 0;
    }

    /**
     * Commence (ou recommence) le temps de prière avant « Je prie » sur le verset (compte actif).
     *
     * @return array{startedAt: string, requiredSeconds: int, confirmableAt: string}
     */
    public function startPrayer(DailyVerse $verse, User $user): array
    {
        $this->ensureActive($user);

        return app(PrayerFocus::class)->start($user, 'daily_verse', (string) $verse->id);
    }

    // ─── Commentaires ────────────────────────────────────────────────────────

    /** Commentaires visibles (retirés : visibles de leur auteur et de la modération), récents d'abord. */
    public function comments(DailyVerse $verse, ?User $viewer): Builder
    {
        $q = DailyVerseComment::query()->with('user')->where('daily_verse_id', $verse->id)->latest();
        if (!$viewer?->canModerate()) {
            $q->where(fn ($w) => $w->whereNull('hidden_at')->when($viewer, fn ($o) => $o->orWhere('user_id', $viewer->id)));
        }

        return $q;
    }

    /**
     * Mentions « @ » : jetons `@[Nom](user:id)` ou comptes choisis ($mentionIds) — App\Services\Mentions.
     * Les personnes mentionnées sont prévenues (notification « mention »).
     */
    public function addComment(DailyVerse $verse, User $user, string $body, array $mentionIds = []): DailyVerseComment
    {
        $this->ensureActive($user);
        $body = trim($body);
        if (mb_strlen(Mentions::plain($body)) < 2) {
            throw new PrayerActionException('Votre commentaire est trop court.', 422);
        }
        ['text' => $text, 'ids' => $ids] = Mentions::prepare($body, $mentionIds);

        $comment = DailyVerseComment::create(['daily_verse_id' => $verse->id, 'user_id' => $user->id, 'body' => $text])
            ->setRelation('user', $user);
        Mentions::notify($ids, $user, [
            'comment_id' => $comment->id,
            'where'      => 'un commentaire sous le verset du jour',
            'payload'    => ['daily_verse_id' => $verse->id, 'daily_verse_comment_id' => $comment->id],
        ]);

        return $comment;
    }

    public function findComment(DailyVerse $verse, string $commentId): DailyVerseComment
    {
        $comment = Str::isUuid($commentId) ? DailyVerseComment::where('daily_verse_id', $verse->id)->find($commentId) : null;
        if (!$comment) {
            throw new PrayerActionException('Commentaire introuvable.', 404);
        }

        return $comment;
    }

    public function deleteComment(DailyVerseComment $comment, User $user): void
    {
        if (!$comment->canBeDeletedBy($user)) {
            throw new PrayerActionException('Vous ne pouvez pas supprimer ce commentaire.', 403);
        }
        $comment->delete();
    }

    /** Un signalement par personne ; retrait automatique au 3e signalement distinct. */
    public function reportComment(DailyVerseComment $comment, User $user, string $reason): void
    {
        $this->ensureActive($user);
        if (!array_key_exists($reason, self::REPORT_REASONS)) {
            throw new PrayerActionException('Choisissez la raison du signalement.', 422);
        }
        if ($comment->user_id === $user->id) {
            throw new PrayerActionException('Vous ne pouvez pas signaler votre propre commentaire.', 422);
        }

        DB::transaction(function () use ($comment, $user, $reason) {
            $report = DailyVerseCommentReport::firstOrNew(['daily_verse_comment_id' => $comment->id, 'reporter_id' => $user->id]);
            $isNew  = !$report->exists;
            $report->fill(['reason' => $reason])->save();
            if ($isNew) {
                $comment->increment('report_count');
            }
            if (!$comment->isHidden() && $comment->report_count >= DailyVerseComment::AUTO_HIDE_REPORTS) {
                $comment->update(['hidden_at' => now()]);
            }
        });
    }

    // ─── Historique ──────────────────────────────────────────────────────────

    /** Jours remontés au plus dans l'historique. */
    public const HISTORY_MAX_DAYS = 366;

    /**
     * Historique des versets du jour (docs/fonctionnalites/verset-du-jour.md) : un élément par
     * jour, du plus récent (aujourd'hui) au plus ancien, depuis le premier verset enregistré.
     * Chaque jour a le verset affiché ce jour-là (programmé, sinon choisi d'après la date).
     *
     * @return array{items: list<array{date: string, verse: DailyVerse}>, page: int, lastPage: int, total: int, perPage: int}
     */
    public function history(int $page = 1, int $perPage = 15): array
    {
        $perPage = max(1, min($perPage, 50));
        $today   = \Carbon\CarbonImmutable::parse(VerseDesignOfTheDay::todayDate());
        $first   = DailyVerse::query()->whereNotNull('scheduled_date')->min('scheduled_date')
            ?? DailyVerse::query()->min('created_at');
        $start   = $first ? \Carbon\CarbonImmutable::parse($first)->startOfDay() : $today;
        $oldest  = $today->subDays(self::HISTORY_MAX_DAYS - 1);
        if ($start->lessThan($oldest)) {
            $start = $oldest;
        }
        $total    = $start->greaterThan($today) ? 1 : (int) $start->diffInDays($today) + 1;
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = max(1, min($page, $lastPage));

        $items = [];
        for ($i = ($page - 1) * $perPage; $i < min($total, $page * $perPage); $i++) {
            $date  = $today->subDays($i)->toDateString();
            $verse = DailyVerse::forDate($date);
            if ($verse) {
                $items[] = ['date' => $date, 'verse' => $verse];
            }
        }

        return ['items' => $items, 'page' => $page, 'lastPage' => $lastPage, 'total' => $total, 'perPage' => $perPage];
    }

    /** Nombre de témoignages publiés qui citent ce passage (mis en cache 10 minutes). */
    public function testimonyCount(DailyVerse $verse): int
    {
        return (int) Cache::remember("daily_verse_testimonies:{$verse->id}", 600,
            fn () => $this->linkedTestimonies($verse, 100)->count());
    }

    public function commentCount(DailyVerse $verse): int
    {
        return DailyVerseComment::where('daily_verse_id', $verse->id)->whereNull('hidden_at')->count();
    }

    // ─── Témoignages liés ────────────────────────────────────────────────────

    /**
     * Témoignages publiés et publics qui citent ce passage (référence ou verset saisis), récents
     * d'abord. Les références sont écrites à la main : on présélectionne par le numéro du chapitre
     * puis on analyse chaque citation.
     */
    public function linkedTestimonies(DailyVerse $verse, int $limit = 20): Collection
    {
        $ref = $verse->parsedReference();
        if (!$ref) {
            return collect();
        }

        $needle = '%' . $ref['chapter'] . '%';

        return Testimony::query()->published()->with('user')
            ->where(fn ($q) => $q->where('bible_ref', 'like', $needle)->orWhere('bible_verse', 'like', $needle))
            ->latest()->limit(300)->get()
            ->filter(function (Testimony $t) use ($ref) {
                foreach ([$t->bible_ref, $t->bible_verse] as $text) {
                    foreach (BibleReference::findAll($text) as $cited) {
                        if (BibleReference::overlaps($ref, $cited)) {
                            return true;
                        }
                    }
                }

                return false;
            })
            ->take($limit)->values();
    }

    /** Marques d'édition de certaines sources (KJV : mots ajoutés entre accolades). */
    private static function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace(['{', '}'], '', $text)) ?? $text);
    }

    private function ensureActive(User $user): void
    {
        if ($user->status !== UserAccountStatus::Active) {
            throw new PrayerActionException('Votre compte ne permet pas cette action.', 403);
        }
    }
}
