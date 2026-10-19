<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DailyVerse;
use App\Models\PrayerRequest;
use App\Services\DailyVerses;
use App\Services\PrayerActionException;
use App\Services\PrayerRequests;
use App\Services\VerseDesignOfTheDay;
use App\Services\VerseShareImage;
use App\Services\VerseStyles;
use App\Support\VerseDesigns;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Page « Verset du jour » en plein écran : versions de la Bible, réactions, requêtes de prière,
 * commentaires et témoignages liés. Règles communes avec l'API : App\Services\DailyVerses.
 * Voir docs/fonctionnalites/verset-du-jour.md
 */
class DailyVerseController extends Controller
{
    /** Version mémorisée (session) quand le visiteur en choisit une. */
    private const SESSION_KEY = 'daily_verse_translation';

    public function __construct(private readonly DailyVerses $verses) {}

    /** GET /verset-du-jour/{date?}  ?version=KJV  ?comparer=1  ?onglet=prieres|commentaires|temoignages */
    public function show(Request $request, ?string $date = null): View
    {
        $verse = $this->verses->forDate($date);
        abort_if(!$verse, 404, 'Aucun verset pour cette date.');

        $data = $this->verses->versions($verse);
        $versions = collect($data['versions']);

        $chosen = strtoupper((string) $request->query('version', ''));
        if ($chosen !== '' && ($chosen === 'ORIGINAL' || $versions->contains('code', $chosen))) {
            $request->session()->put(self::SESSION_KEY, $chosen);
        }
        $preferred = $chosen !== '' ? $chosen : $request->session()->get(self::SESSION_KEY, 'ORIGINAL');
        $current   = $preferred === 'ORIGINAL' ? null : $versions->firstWhere('code', $preferred);

        $viewer = Auth::user();
        $designDate = $date ?? VerseDesignOfTheDay::todayDate();
        $tab    = in_array($request->query('onglet'), ['prieres', 'commentaires', 'temoignages'], true) ? $request->query('onglet') : 'prieres';

        return view('daily-verse.show', [
            'verse'        => $verse,
            'date'         => $date,
            'parsed'       => $data['parsed'],
            'versions'     => $versions,
            'preferred'    => $preferred,
            'current'      => $current,
            'compare'      => $request->boolean('comparer'),
            'tab'          => $tab,
            'viewer'       => $viewer,
            'myReactions'  => $viewer ? $verse->userReactions($viewer->id) : [],
            'prayers'      => app(PrayerRequests::class)->list($viewer, 'verse', null, null, $verse->id)->limit(30)->get(),
            'comments'     => $this->verses->comments($verse, $viewer)->limit(50)->get(),
            'commentCount' => $this->verses->commentCount($verse),
            'testimonies'  => $this->verses->linkedTestimonies($verse, 12),
            'reasons'      => DailyVerses::REPORT_REASONS,
            // Designs : docs/fonctionnalites/designs-du-verset.md
            'designDate'   => $designDate,
            ...app(VerseStyles::class)->forView($viewer, $designDate),
        ]);
    }

    /**
     * GET /verset-du-jour/{date}/image.png?design=  Image de partage 1080 × 1350 (design du jour par
     * défaut ; design inconnu ou désactivé → design du jour). Générée une fois puis servie du cache.
     */
    public function image(Request $request, string $date): BinaryFileResponse
    {
        $verse = $this->verses->forDate($date);
        abort_if(!$verse, 404);
        $design = $request->query('design');
        $design = is_string($design) && VerseDesigns::isActive($design) ? $design : VerseDesignOfTheDay::forDate($date);

        return response()->file(app(VerseShareImage::class)->path($verse, $date, $design), [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /** PUT /verset-du-jour/style  Design et réglages du texte du membre (mêmes règles que PUT /api/v1/me/verse-style). */
    public function updateStyle(Request $request, VerseStyles $styles): JsonResponse
    {
        $data = $request->validate(VerseStyles::rules(), VerseStyles::messages());

        return response()->json(['success' => true, 'data' => $styles->update(Auth::user(), $data), 'message' => __('api.preferences_saved')]);
    }

    public function react(Request $request, int $id): RedirectResponse
    {
        $request->validate(['type' => 'required|in:like,pray,amen']);

        return $this->attempt(function () use ($request, $id) {
            $active = $this->verses->setReaction($this->find($id), Auth::user(), $request->input('type'));

            return back()->with('success', $active ? 'Merci, votre réaction est enregistrée.' : 'Réaction retirée.');
        });
    }

    /**
     * Temps de prière (fetch) avant « Je prie » : même réponse JSON que l'API POST daily-verse/{id}/pray/start.
     * Voir docs/fonctionnalites/temps-de-priere.md
     */
    public function startPrayer(int $id): JsonResponse
    {
        try {
            $data = $this->verses->startPrayer($this->find($id), Auth::user());
        } catch (PrayerActionException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->status());
        }

        return response()->json(['success' => true, 'data' => $data, 'message' => __('prayer_focus.started')]);
    }

    public function storePrayer(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(PrayerRequests::rules(), PrayerRequests::MESSAGES);
        $data['is_anonymous']   = $request->boolean('is_anonymous');
        $data['daily_verse_id'] = $this->find($id)->id;

        return $this->attempt(function () use ($data) {
            app(PrayerRequests::class)->create(Auth::user(), $data);

            return back()->withFragment('verse-tabs')->with('success', 'Votre requête est publiée. Que Dieu vous réponde !');
        }, withInput: true);
    }

    public function storeComment(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['body' => 'required|string|min:2|max:1000'], [
            'body.required' => 'Écrivez votre commentaire.',
            'body.min'      => 'Votre commentaire est trop court.',
            'body.max'      => '1 000 caractères au plus.',
        ]);

        return $this->attempt(function () use ($data, $id) {
            $this->verses->addComment($this->find($id), Auth::user(), $data['body']);

            return back()->withFragment('verse-tabs')->with('success', 'Merci pour votre commentaire !');
        }, withInput: true);
    }

    public function destroyComment(int $id, string $comment): RedirectResponse
    {
        return $this->attempt(function () use ($id, $comment) {
            $this->verses->deleteComment($this->verses->findComment($this->find($id), $comment), Auth::user());

            return back()->withFragment('verse-tabs')->with('success', 'Commentaire supprimé.');
        });
    }

    public function reportComment(Request $request, int $id, string $comment): RedirectResponse
    {
        $request->validate(['reason' => ['required', Rule::in(array_keys(DailyVerses::REPORT_REASONS))]]);

        return $this->attempt(function () use ($request, $id, $comment) {
            $this->verses->reportComment($this->verses->findComment($this->find($id), $comment), Auth::user(), $request->input('reason'));

            return back()->withFragment('verse-tabs')->with('success', 'Merci, le commentaire a été signalé à la modération.');
        });
    }

    private function find(int $id): DailyVerse
    {
        $verse = DailyVerse::where('is_active', true)->find($id);
        abort_if(!$verse, 404);

        return $verse;
    }

    private function attempt(callable $action, bool $withInput = false): RedirectResponse
    {
        try {
            return $action();
        } catch (PrayerActionException $e) {
            abort_if($e->status() === 404, 404);
            $back = back()->with('error', $e->getMessage());

            return $withInput ? $back->withInput() : $back;
        }
    }
}
