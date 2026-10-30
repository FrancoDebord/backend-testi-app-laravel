<?php

namespace App\Http\Controllers\Web;

use App\Enums\TestimonyType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\LiveSession;
use App\Models\Testimony;
use App\Services\ViewCounter;
use App\Services\WatchPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Page publique « Vidéos » : témoignages vidéo, shorts, directs, audios et textes.
 * Voir docs/fonctionnalites/videos.md
 */
class VideoController extends Controller
{
    private const TAB_KEYS  = ['all', 'videos', 'shorts', 'lives', 'audio', 'text'];
    private const SORT_KEYS = ['recent', 'views', 'popular'];

    /** Onglets de la page (clé => libellé traduit). */
    public static function tabs(): array
    {
        return array_combine(self::TAB_KEYS, array_map(fn ($k) => __('videos.tabs.' . $k), self::TAB_KEYS));
    }

    /** Tris proposés (clé => libellé traduit). */
    public static function sorts(): array
    {
        return array_combine(self::SORT_KEYS, array_map(fn ($k) => __('videos.sorts.' . $k), self::SORT_KEYS));
    }

    private const PER_PAGE          = 24;

    public function __construct(
        private readonly ViewCounter $views,
        private readonly WatchPage $watch,
    ) {}

    // ─── Liste ───────────────────────────────────────────────────────────

    public function index(Request $request): View|JsonResponse
    {
        $tab  = in_array((string) $request->query('tab'), self::TAB_KEYS, true) ? $request->query('tab') : 'all';
        $sort = in_array((string) $request->query('sort'), self::SORT_KEYS, true) ? $request->query('sort') : 'recent';
        $q    = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $categories = Category::active()->get(['id', 'name', 'name_en', 'slug']);
        $category   = $categories->firstWhere('slug', $request->query('category'))?->slug;

        $query = Testimony::with(['user', 'category', 'liveSession:id,testimony_id'])
            ->published()
            ->search($q)
            ->when($category, fn ($query) => $query->ofCategory($category));

        match ($tab) {
            'videos' => $query->longVideos(),
            'shorts' => $query->shorts(),
            'lives'  => $query->liveReplays(),
            'audio'  => $query->where('type', TestimonyType::Audio),
            'text'   => $query->where('type', TestimonyType::Text),
            default  => null,
        };

        match ($sort) {
            'views'   => $query->orderByDesc('views_count')->latestPublished(),
            'popular' => $query->orderByDesc('like_count')->latestPublished(),
            default   => $query->latestPublished(),
        };

        $items = $query->paginate(self::PER_PAGE)->withQueryString();

        // « Afficher plus » : seules les cartes suivantes sont renvoyées.
        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('videos.partials.cards', ['items' => $items, 'tab' => $tab, 'encourage' => true])->render(),
                'next' => $items->nextPageUrl(),
            ]);
        }

        // Directs en cours : en tête de « Tout » et « Directs », hors recherche et filtre.
        $lives = in_array($tab, ['all', 'lives'], true) && $q === '' && !$category && $items->onFirstPage()
            ? LiveSession::with('host')->onAir()->latest('started_at')->limit(8)->get()
            : collect();

        return view('videos.index', compact('items', 'lives', 'categories', 'tab', 'sort', 'q', 'category'));
    }

    // ─── Lecture ─────────────────────────────────────────────────────────

    public function show(Request $request, string $id): View|JsonResponse
    {
        $testimony = $this->findVisible($request, $id, ['user', 'category', 'liveSession:id,testimony_id,started_at', 'mediaFile:id,url,processing_status']);

        // « Afficher plus de commentaires »
        if ($request->expectsJson()) {
            return $this->watch->commentsResponse($testimony, 'videos.show');
        }

        // Texte : lu dès l'ouverture. Audio et vidéo : comptés au lancement de la lecture (recordView).
        if ($testimony->type === TestimonyType::Text) {
            $this->views->record($testimony, $request);
        }

        return view('videos.show', $this->watch->data($testimony, $request->user(), 'videos.show', $request->query('list')));
    }

    /** Appelé par le lecteur au premier lancement de la lecture. */
    public function recordView(Request $request, string $id): JsonResponse
    {
        $data      = $request->validate(['duration' => ['nullable', 'numeric', 'min:0', 'max:86400']]);
        $testimony = $this->findVisible($request, $id);

        $counted = $this->views->record($testimony, $request);

        // Durée inconnue (fichier envoyé depuis le site) : on retient celle lue par le lecteur, une seule fois.
        if ($testimony->type !== TestimonyType::Text && $testimony->duration_sec === 0 && !empty($data['duration'])) {
            $testimony->forceFill(['duration_sec' => (int) round($data['duration'])])->saveQuietly();
        }

        return response()->json([
            'counted' => $counted,
            'views'   => $testimony->views_count,
            'label'   => $testimony->viewsLabel(),
        ]);
    }

    // ─── Outils ──────────────────────────────────────────────────────────

    /**
     * Témoignage consultable sur le site par la personne connectée (ou non), sinon 404 ; 410 Gone s'il a été
     * supprimé ou retiré du web par son auteur (Testimony::webStatusFor, docs/fonctionnalites/referencement.md).
     */
    public static function findVisible(Request $request, string $id, array $with = []): Testimony
    {
        $testimony = \Illuminate\Support\Str::isUuid($id) ? Testimony::withTrashed()->with($with)->find($id) : null;
        abort_unless($testimony, 404);
        $status = $testimony->webStatusFor($request->user());
        abort_if($status !== 200, $status);

        return $testimony;
    }
}
