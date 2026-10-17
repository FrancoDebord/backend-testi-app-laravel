<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Prophecy;
use App\Models\Testimony;
use App\Services\Journal;
use App\Services\Prophecies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Carnet privé sur le site : témoignages gardés pour soi (texte, audio, vidéo).
 * Visible de son seul auteur ; règles communes avec l'API : App\Services\Journal.
 * Voir docs/fonctionnalites/carnet-prive.md
 */
class JournalController extends Controller
{
    public const TYPE_KEYS = ['text', 'audio', 'video'];

    /** Filtres par type : clé => libellé traduit. */
    public static function types(): array
    {
        return collect(self::TYPE_KEYS)->mapWithKeys(fn ($k) => [$k => __('journal.types.' . $k)])->all();
    }

    public function __construct(private readonly Journal $journal) {}

    /** Mes entrées, les plus récentes d'abord (?type, ?q). */
    public function index(Request $request, Prophecies $prophecies): View
    {
        $type = in_array($request->query('type'), self::TYPE_KEYS, true) ? $request->query('type') : null;
        $q    = trim((string) $request->query('q', ''));

        $entries = $this->journal->entries($request->user()->id, $type, $q)->paginate(12)->withQueryString();
        $prophecyCount = array_sum($prophecies->counts($request->user()));

        return view('journal.index', compact('entries', 'type', 'q', 'prophecyCount'));
    }

    /** Partager une entrée : elle devient publique et passe en modération. */
    public function share(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(
            ['category' => ['required', 'string', 'exists:categories,slug']],
            [
                'category.required' => __('journal.share.category_required'),
                'category.exists'   => __('journal.share.category_exists'),
            ]
        );
        $testimony = $this->entry($request, $id);
        abort_unless($request->user()->canPublish(), 403, __('journal.share.forbidden'));

        $this->journal->share($testimony, $data['category']);

        return redirect()->route('testimonies.show', $testimony->id)
            ->with('success', __('journal.flash.shared'));
    }

    /** Retirer un de ses témoignages du public et le ranger dans le carnet. */
    public function store(Request $request, string $id): RedirectResponse
    {
        $testimony = Testimony::find($id);
        abort_if(!$testimony || $testimony->user_id !== $request->user()->id, 404);

        $this->journal->store($testimony);
        // La parole prophétique liée n'est plus montrée publiquement.
        Prophecy::where('testimony_id', $testimony->id)->update(['is_public' => false]);

        return redirect()->route('testimonies.show', $testimony->id)
            ->with('success', __('journal.flash.stored'));
    }

    /** Supprimer une entrée du carnet (les témoignages publics ne se suppriment pas ici). */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $testimony = $this->entry($request, $id);
        // Supprimer le témoignage laisse la parole accomplie (docs/fonctionnalites/paroles-prophetiques.md).
        Prophecy::where('testimony_id', $testimony->id)->update(['testimony_id' => null, 'is_public' => false]);
        $testimony->delete();

        return redirect()->route('journal.index')->with('success', __('journal.flash.deleted'));
    }

    /** Entrée du carnet de l'utilisateur ; 404 pour toute autre (son existence n'est pas révélée). */
    private function entry(Request $request, string $id): Testimony
    {
        $testimony = Testimony::where('user_id', $request->user()->id)->journal()->find($id);
        abort_if(!$testimony, 404);

        return $testimony;
    }
}
