<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DailyVerseCommentResource;
use App\Http\Resources\PrayerRequestResource;
use App\Http\Resources\TestimonyResource;
use App\Http\Resources\ThemeSemaineResource;
use App\Models\DailyVerse;
use App\Models\ThemeSemaine;
use App\Models\User;
use App\Services\DailyVerses;
use App\Services\PrayerActionException;
use App\Services\PrayerRequests;
use App\Services\VerseDesignOfTheDay;
use App\Services\VerseStyles;
use App\Support\BibleReference;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Rules\VisibleLength;

/**
 * Verset du jour : verset, versions de la Bible, réactions, commentaires, requêtes de prière et
 * témoignages liés. Voir docs/fonctionnalites/verset-du-jour.md
 */
class DailyVerseController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly DailyVerses $verses) {}

    // GET /daily-verse?date=AAAA-MM-JJ&translation=KJV  (public)
    public function today(Request $request): JsonResponse
    {
        $verse = $this->verses->forDate($request->query('date'));
        if (!$verse) {
            return $this->error('Aucun verset programmé.', 404);
        }

        return $this->success($this->payload($verse, $request));
    }

    /**
     * GET /daily-verse/weeks/{year}/{week}  (public) : thème de la semaine et ses versets (dimanche → samedi).
     * Voir docs/fonctionnalites/verset-du-jour.md
     */
    public function week(int $year, int $week): JsonResponse
    {
        $theme = ThemeSemaine::with(['versets' => fn ($q) => $q->where('is_active', true)])
            ->where('annee', $year)->where('numero_semaine', $week)->first();
        if (!$theme) {
            return $this->error("Aucun thème pour la semaine {$week} de {$year}.", 404);
        }

        return $this->success(new ThemeSemaineResource($theme));
    }

    /**
     * GET /daily-verse/history?page=&limit=  (public) : versets des jours passés, du plus récent au
     * plus ancien, avec leurs compteurs (j'aime, prières, commentaires, témoignages, requêtes) et
     * les réactions de la personne connectée.
     */
    public function history(Request $request): JsonResponse
    {
        $h    = $this->verses->history((int) $request->query('page', 1), (int) $request->query('limit', 15));
        $user = $this->user($request);

        $data = array_map(function (array $item) use ($user) {
            /** @var DailyVerse $verse */
            $verse = $item['verse'];
            // Dans la langue de l'application (Accept-Language) : docs/fonctionnalites/verset-du-jour.md
            $localized = $this->verses->localized($verse);

            return [
                'date'        => $item['date'],
                'id'          => $verse->id,
                'reference'   => $localized['reference'],
                'verseText'   => $localized['text'],
                'language'    => $localized['language'],
                'theme'       => $verse->theme,
                'themeLabel'  => $verse->themeLabel(),
                'counts'      => [
                    'likes'          => $verse->like_count,
                    'prayers'        => $verse->prayer_count,
                    'comments'       => $this->verses->commentCount($verse),
                    'testimonies'    => $this->verses->testimonyCount($verse),
                    'prayerRequests' => $verse->prayerRequests()->where('visibility', 'public')->whereNull('hidden_at')->count(),
                ],
                'myReactions' => $user ? $verse->userReactions($user->id) : [],
                'webUrl'      => route('daily-verse.show', ['date' => $item['date']]),
            ];
        }, $h['items']);

        return response()->json([
            'success' => true,
            'data'    => $data,
            'meta'    => ['currentPage' => $h['page'], 'lastPage' => $h['lastPage'], 'total' => $h['total'], 'perPage' => $h['perPage']],
        ]);
    }

    // GET /daily-verse/{id}  (public)
    public function show(Request $request, int $id): JsonResponse
    {
        return $this->success($this->payload($this->find($id), $request));
    }

    // GET /daily-verse/versions?date=  et  /daily-verse/{id}/versions  (?translation= : une seule version)
    public function versions(Request $request, ?int $id = null): JsonResponse
    {
        $verse = $id ? $this->find($id) : $this->verses->forDate($request->query('date'));
        if (!$verse) {
            return $this->error('Aucun verset programmé.', 404);
        }
        $only = $request->query('translation');

        return $this->success([
            'id' => $verse->id,
            ...$this->verses->versions($verse, is_string($only) && $only !== '' ? $only : null),
        ]);
    }

    // POST /daily-verse/react  { type: "like"|"pray"|"amen" }  (authentifié, verset du jour)
    public function react(Request $request): JsonResponse
    {
        $request->validate(['type' => 'required|in:like,pray,amen']);

        $verse = DailyVerse::today();
        if (!$verse) return $this->notFound();

        // Idempotent : une réaction déjà présente (état local de l'application périmé) n'est pas une erreur.
        return $this->attempt(function () use ($verse, $request) {
            $this->verses->setReaction($verse, $request->user(), $request->type, true);

            return $this->success(null, 'Réaction enregistrée');
        });
    }

    // DELETE /daily-verse/react  { type: "like"|"pray"|"amen" }  (authentifié, verset du jour)
    public function unreact(Request $request): JsonResponse
    {
        $request->validate(['type' => 'required|in:like,pray,amen']);

        $verse = DailyVerse::today();
        if (!$verse) return $this->notFound();

        return $this->attempt(function () use ($verse, $request) {
            $this->verses->setReaction($verse, $request->user(), $request->type, false);

            return $this->success(null, 'Réaction retirée');
        });
    }

    // POST /daily-verse/{id}/react  { type, active?, again?, last? }  (authentifié, n'importe quel verset ; sans active : bascule)
    // again (pray) : prier encore ; { active: false, last: true } : retirer la dernière prière (docs/fonctionnalites/prier-plusieurs-fois.md)
    public function reactTo(Request $request, int $id): JsonResponse
    {
        $request->validate(['type' => 'required|in:like,pray,amen', 'active' => 'sometimes|boolean', 'again' => 'sometimes|boolean', 'last' => 'sometimes|boolean']);
        $verse = $this->find($id);

        return $this->attempt(function () use ($verse, $request) {
            $active = $request->type === 'pray' && $request->boolean('last') && $request->has('active') && !$request->boolean('active')
                ? $this->verses->removeLastPrayer($verse, $request->user()) > 0
                : $this->verses->setReaction($verse, $request->user(), $request->type,
                    $request->has('active') ? $request->boolean('active') : null, $request->boolean('again'));

            return $this->success($this->payload($verse->refresh(), $request), $active ? 'Réaction enregistrée' : 'Réaction retirée');
        });
    }

    /**
     * POST /daily-verse/{id}/pray/start : commence (ou recommence) le temps de prière. « Je prie » (`pray`)
     * n'est ajouté qu'après config('prayer.focus_seconds') secondes. Voir docs/fonctionnalites/temps-de-priere.md
     */
    public function startPrayer(Request $request, int $id): JsonResponse
    {
        $verse = $this->find($id);

        return $this->attempt(fn () => $this->success($this->verses->startPrayer($verse, $request->user()), __('prayer_focus.started')));
    }

    // POST /daily-verse/share  (authentifié)
    public function share(): JsonResponse
    {
        $verse = DailyVerse::today();
        if (!$verse) return $this->notFound();

        $verse->increment('share_count');

        return $this->success(['shareUrl' => DailyVerses::shareUrl()], 'Partage enregistré');
    }

    // ─── Commentaires ────────────────────────────────────────────────────────

    // GET /daily-verse/{id}/comments?limit=30  (public ; récents d'abord)
    public function comments(Request $request, int $id): JsonResponse
    {
        $items = $this->verses->comments($this->find($id), $this->user($request))
            ->paginate(min(max((int) $request->query('limit', 30), 1), 50));
        // « J'aime » de la personne : une requête pour toute la page (docs/fonctionnalites/jaime-commentaires.md).
        app(\App\Services\CommentLikes::class)->preload(\App\Services\CommentLikes::VERSE, $items->items(), $this->user($request));

        return $this->paginated(DailyVerseCommentResource::collection($items->items()), [
            'currentPage' => $items->currentPage(),
            'lastPage'    => $items->lastPage(),
            'total'       => $items->total(),
            'perPage'     => $items->perPage(),
        ]);
    }

    // POST /daily-verse/{id}/comments  { body, mention_ids? }  (mentions : jetons @[Nom](user:id))
    public function storeComment(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', new VisibleLength(2000, '2 000 caractères au plus.')],
            'mention_ids' => 'sometimes|array|max:10', 'mention_ids.*' => 'string',
        ], [
            'body.required' => 'Écrivez votre commentaire.',
            'body.min'      => 'Votre commentaire est trop court.',
        ]);
        $verse = $this->find($id);

        return $this->attempt(fn () => $this->created(
            new DailyVerseCommentResource($this->verses->addComment($verse, $request->user(), $data['body'], $data['mention_ids'] ?? [])),
            'Merci pour votre commentaire !'
        ));
    }

    // DELETE /daily-verse/{id}/comments/{commentId}  (auteur ou modération)
    public function destroyComment(Request $request, int $id, string $commentId): JsonResponse
    {
        $verse = $this->find($id);

        return $this->attempt(function () use ($request, $verse, $commentId) {
            $this->verses->deleteComment($this->verses->findComment($verse, $commentId), $request->user());

            return $this->success(null, 'Commentaire supprimé.');
        });
    }

    // POST /daily-verse/{id}/comments/{commentId}/report  { reason }
    public function reportComment(Request $request, int $id, string $commentId): JsonResponse
    {
        $request->validate(['reason' => ['required', Rule::in(array_keys(DailyVerses::REPORT_REASONS))]],
            ['reason.required' => 'Choisissez la raison du signalement.', 'reason.in' => 'Choisissez la raison du signalement.']);
        $verse = $this->find($id);

        return $this->attempt(function () use ($request, $verse, $commentId) {
            $this->verses->reportComment($this->verses->findComment($verse, $commentId), $request->user(), $request->input('reason'));

            return $this->success(null, 'Merci, le commentaire a été signalé à la modération.');
        });
    }

    // ─── Requêtes de prière et témoignages liés ──────────────────────────────

    // GET /daily-verse/{id}/prayer-requests?limit=20  (public, selon la visibilité de chaque requête)
    // Création : POST /prayer/requests { body, …, daily_verse_id }.
    public function prayerRequests(Request $request, PrayerRequests $requests, int $id): JsonResponse
    {
        $verse = $this->find($id);
        $user  = $this->user($request);
        $items = $requests->list($user, 'verse', null, null, $verse->id)
            ->paginate(min(max((int) $request->query('limit', 20), 1), 50));

        return $this->paginated(PrayerRequestResource::collection($items->items()), [
            'currentPage' => $items->currentPage(),
            'lastPage'    => $items->lastPage(),
            'total'       => $items->total(),
            'perPage'     => $items->perPage(),
            'canCreate'   => $user !== null,
        ]);
    }

    // GET /daily-verse/{id}/testimonies  (public : publiés et publics qui citent ce passage)
    public function testimonies(int $id): JsonResponse
    {
        return $this->success(TestimonyResource::collection($this->verses->linkedTestimonies($this->find($id))));
    }

    // ─── Outils ──────────────────────────────────────────────────────────────

    private function payload(DailyVerse $verse, Request $request): array
    {
        $user        = $this->user($request);
        $myReactions = $user ? $verse->userReactions($user->id) : [];
        $ref         = $verse->parsedReference();
        $translation = $request->query('translation');
        $version     = is_string($translation) && $translation !== ''
            ? ($this->verses->versions($verse, $translation)['versions'][0] ?? null)
            : null;

        // Verset dans la langue de l'application (Accept-Language, langue choisie ou du système) : texte du jour en
        // français, traduction installée en anglais (KJV…), texte du jour à défaut. Voir docs/fonctionnalites/verset-du-jour.md
        $localized = $this->verses->localized($verse);

        return [
            'id'           => $verse->id,
            'verseText'    => $localized['text'],
            'reference'    => $localized['reference'],
            'language'        => $localized['language'],
            'textTranslation' => $localized['translation'],      // null : texte du jour
            'textTranslationName' => $localized['translationName'],
            'originalText'      => $verse->verse_text,
            'originalReference' => $verse->reference,
            'theme'        => $verse->theme,
            // Thème de la semaine (verset clé, période) et version du texte, importés par versets:import.
            'weekTheme'    => $verse->themeSemaine ? new ThemeSemaineResource($verse->themeSemaine) : null,
            'version'      => $verse->version,
            'dayName'      => $verse->dayName(),
            'themeLabel'   => $verse->themeLabel(),
            'weekNumber'   => $verse->week_number,
            'date'         => $verse->scheduled_date?->toDateString(),
            // Livre / chapitre / versets reconnus dans la référence (null si non reconnue).
            'parsed'       => $ref ? [...$ref, 'bookName' => BibleReference::bookName($ref['book'])] : null,
            // Avec ?translation= : le passage dans cette version (null si la version n'est pas installée).
            'translation'  => $version,
            'counts'       => [
                'likes'          => $verse->like_count,
                'prayers'        => $verse->prayer_count,
                'amens'          => $verse->amen_count,
                'shares'         => $verse->share_count,
                'comments'       => $this->verses->commentCount($verse),
                'prayerRequests' => $verse->prayerRequests()->where('visibility', 'public')->whereNull('hidden_at')->count(),
            ],
            'myReactions'  => $myReactions,
            // Nombre de fois que la personne a prié (docs/fonctionnalites/prier-plusieurs-fois.md).
            'myPrayerTimes' => $this->verses->prayerTimes($verse, $user),
            'webUrl'       => route('daily-verse.show', $verse->scheduled_date ? ['date' => $verse->scheduled_date->toDateString()] : []),
            // Lien à partager : page datée du site (SHARE_URL), même verset quel que soit le jour d'ouverture.
            'shareUrl'     => DailyVerses::shareUrl(
                preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('date')) ? $request->query('date') : $verse->scheduled_date?->toDateString()
            ),
            ...$this->designFields($verse, $request, $user),
        ];
    }

    /**
     * Designs (docs/fonctionnalites/designs-du-verset.md) : design du jour de la date demandée,
     * préférences du membre (null pour un visiteur) et design à afficher.
     */
    private function designFields(DailyVerse $verse, Request $request, ?User $user): array
    {
        $date = $request->query('date');
        $date = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            ? $date
            : ($verse->scheduled_date?->toDateString() ?? VerseDesignOfTheDay::todayDate());
        $ofTheDay = VerseDesignOfTheDay::forDate($date);
        $style = $user ? app(VerseStyles::class)->forUser($user) : null;

        return [
            'design_of_the_day'   => $ofTheDay,
            'user_style'          => $style,
            'effective_design_id' => VerseStyles::effectiveDesignId($style, $ofTheDay),
            'share_image_url'     => route('daily-verse.image', ['date' => $date]),
        ];
    }

    private function find(int $id): DailyVerse
    {
        $verse = DailyVerse::where('is_active', true)->find($id);
        abort_if(!$verse, 404, 'Verset introuvable.');

        return $verse;
    }

    private function user(Request $request): ?User
    {
        return $request->user() ?? $request->user('sanctum');
    }

    private function attempt(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (PrayerActionException $e) {
            return $this->error($e->getMessage(), $e->status());
        }
    }
}
