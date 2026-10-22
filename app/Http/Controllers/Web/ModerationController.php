<?php

namespace App\Http\Controllers\Web;

use App\Enums\RejectionReason;
use App\Enums\TestimonyStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\ModerationLog;
use App\Models\Testimony;
use App\Services\ContentActionException;
use App\Services\TestimonyModeration;
use App\Services\TestimonyStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Back-office des témoignages (modérateurs et administrateurs) : liste par onglets, recherche, filtres,
 * actions groupées, fiche (aperçu, historique, statistiques), tableau de bord.
 * Règles : App\Services\TestimonyModeration ; droits : App\Policies\TestimonyPolicy.
 * Voir docs/fonctionnalites/gestion-des-temoignages.md
 */
class ModerationController extends Controller
{
    public const TAB_KEYS = ['pending', 'approved', 'rejected', 'featured', 'trash'];

    /** Onglets de la liste : clé => libellé traduit. */
    public static function tabs(): array
    {
        return collect(self::TAB_KEYS)->mapWithKeys(fn ($k) => [$k => __('moderation.tabs.' . $k)])->all();
    }

    public function __construct(private readonly TestimonyModeration $moderation) {}

    /** GET /moderation  ?onglet=pending|approved|rejected|featured|trash  ?q=  ?type=  ?categorie=  ?label=1  ?conversion=1 */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Testimony::class);
        $tab = in_array((string) $request->query('onglet', $request->query('status')), self::TAB_KEYS, true)
            ? (string) $request->query('onglet', $request->query('status')) : 'pending';

        $query = Testimony::with(['user', 'moderator'])->withoutJournal();
        match ($tab) {
            'trash'    => $query->onlyTrashed()->latest('deleted_at'),
            'featured' => $query->where('is_featured', true)->where('status', TestimonyStatus::Approved)
                ->orderByRaw('CASE WHEN featured_order IS NULL THEN 1 ELSE 0 END')->orderBy('featured_order'),
            'pending'  => $query->where('status', TestimonyStatus::Pending)->oldest(),
            default    => $query->where('status', $tab)->latest('moderated_at')->latest(),
        };
        if ($q = trim((string) $request->query('q'))) {
            $query->search($q);
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($category = $request->query('categorie')) {
            $query->where('category_slug', $category);
        }
        if ($request->boolean('label')) {
            $query->where('is_verified', true);
        }
        // Témoignages de conversion seulement (docs/fonctionnalites/temoignage-de-conversion.md)
        if ($request->boolean('conversion')) {
            $query->conversion();
        }

        $today = now()->toDateString();

        return view('moderation.index', [
            'items'      => $query->paginate(20)->withQueryString(),
            'tab'        => $tab,
            'tabs'       => self::tabs(),
            'counts'     => [
                'pending'  => Testimony::pending()->count(),
                'trash'    => Testimony::onlyTrashed()->withoutJournal()->count(),
                'featured' => Testimony::manuallyFeatured()->count(),
            ],
            'stats'      => [
                'pending'        => Testimony::pending()->count(),
                'approvedToday'  => ModerationLog::where('action', 'approved')->whereDate('created_at', $today)->count(),
                'rejectedToday'  => ModerationLog::where('action', 'rejected')->whereDate('created_at', $today)->count(),
                'totalThisMonth' => ModerationLog::whereIn('action', ['approved', 'rejected'])->where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'categories' => Category::orderBy('name')->get(['slug', 'name', 'name_en']),
            'reasons'    => RejectionReason::cases(),
            'trashDays'  => (int) config('testimonies.trash_days', 30),
        ]);
    }

    public function show(string $id): View
    {
        $testimony = Testimony::withTrashed()->with(['user', 'moderationLogs.moderator', 'proofs', 'moderator', 'verifier'])->withoutJournal()->findOrFail($id);
        Gate::authorize('viewStats', $testimony);

        if ($testimony->status === TestimonyStatus::Pending && !$testimony->trashed()) {
            ModerationLog::create(['testimony_id' => $id, 'moderator_id' => Auth::id(), 'action' => 'in_review', 'created_at' => now()]);
        }

        return view('moderation.show', [
            'testimony' => $testimony,
            'reasons'   => RejectionReason::cases(),
            'audit'     => AdminAuditLog::where('subject_type', 'testimony')->where('subject_id', $id)->latest('created_at')->limit(50)->get(),
            'stats'     => app(TestimonyStats::class)->series($testimony, 30),
            'video'     => app(\App\Services\BunnyStreamVideos::class)->payload($testimony),
        ]);
    }

    /** GET /moderation/{id}/statistiques  ?jours=7|30|90 */
    public function stats(Request $request, string $id): View
    {
        $testimony = Testimony::withTrashed()->with('user')->withoutJournal()->findOrFail($id);
        Gate::authorize('viewStats', $testimony);
        $days = in_array((int) $request->query('jours'), [7, 30, 90], true) ? (int) $request->query('jours') : 30;

        return view('moderation.stats', [
            'testimony' => $testimony,
            'days'      => $days,
            'series'    => app(TestimonyStats::class)->series($testimony, $days),
        ]);
    }

    /** GET /moderation/tableau-de-bord  ?jours=7|30|90 */
    public function dashboard(Request $request): View
    {
        Gate::authorize('viewAny', Testimony::class);
        $days = in_array((int) $request->query('jours'), [7, 30, 90], true) ? (int) $request->query('jours') : 30;
        $stats = app(TestimonyStats::class);

        return view('moderation.dashboard', [
            'days'    => $days,
            'series'  => $stats->series(null, $days),
            'top'     => $stats->top($days, 10),
            'pending' => Testimony::with('user')->pending()->oldest()->limit(8)->get(),
            'pendingCount' => Testimony::pending()->count(),
            'counts'  => [
                'approved' => Testimony::published()->count(),
                'verified' => Testimony::published()->where('is_verified', true)->count(),
                'featured' => Testimony::manuallyFeatured()->count(),
                'trash'    => Testimony::onlyTrashed()->withoutJournal()->count(),
            ],
        ]);
    }

    // ─── Actions sur un témoignage ───────────────────────────────────────────

    /** Aussi pour un témoignage rejeté. `return=back` : retour à la page d'origine (liste, Administration › Contenu). */
    public function approve(Request $request, string $id): RedirectResponse
    {
        $testimony = $this->find($id);
        Gate::authorize('moderate', $testimony);
        $this->moderation->approve($testimony, Auth::user());

        if ($request->input('return') === 'back') {
            return back()->with('success', __('moderation.flash.approved'));
        }

        return $this->back(__('moderation.flash.approved'));
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'reason'           => ['required', Rule::enum(RejectionReason::class)],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
            'moderator_note'   => ['nullable', 'string', 'max:1000'],
        ], ['reason.required' => __('moderation.choose_reason')]);
        $testimony = $this->find($id);
        Gate::authorize('moderate', $testimony);

        $code = RejectionReason::from($data['reason']);
        $text = trim(implode(' — ', array_filter([$code->label(), trim((string) ($data['rejection_reason'] ?? $data['moderator_note'] ?? ''))])));
        $this->moderation->reject($testimony, Auth::user(), $text, $code, $data['moderator_note'] ?? $data['rejection_reason'] ?? null);

        return $this->back(__('moderation.flash.rejected'));
    }

    /**
     * Retire l'affichage public des preuves (document trop personnel, illisible…). Seul l'auteur
     * peut de nouveau donner son accord (en modifiant son témoignage). Voir docs/fonctionnalites/preuves.md
     */
    public function hideProofs(string $id): RedirectResponse
    {
        Testimony::withoutJournal()->findOrFail($id)->update(['proofs_public' => false]);

        return back()->with('success', __('moderation.flash.proofs_hidden'));
    }

    /** POST /moderation/{id}/label  { verified: 0|1, reason? } (administrateurs) */
    public function verify(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(['verified' => ['required', 'boolean'], 'reason' => ['nullable', 'string', 'max:500']]);
        $testimony = $this->find($id);
        Gate::authorize('verify', $testimony);

        return $this->attempt(fn () => $this->moderation->setVerified($testimony, Auth::user(), (bool) $data['verified'], $data['reason'] ?? null),
            $data['verified'] ? __('moderation.flash.label_granted') : __('moderation.flash.label_removed'));
    }

    /** POST /moderation/{id}/une  { featured: 0|1, featured_order?, featured_from?, featured_until? } */
    public function feature(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'featured'       => ['required', 'boolean'],
            'featured_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'featured_from'  => ['nullable', 'date'],
            'featured_until' => ['nullable', 'date'],
        ]);
        $testimony = $this->find($id);
        Gate::authorize('feature', $testimony);

        return $this->attempt(fn () => $this->moderation->setFeatured($testimony, Auth::user(), (bool) $data['featured'],
            isset($data['featured_order']) ? (int) $data['featured_order'] : null,
            !empty($data['featured_from']) ? Carbon::parse($data['featured_from']) : null,
            !empty($data['featured_until']) ? Carbon::parse($data['featured_until']) : null,
        ), $data['featured'] ? __('moderation.flash.featured') : __('moderation.flash.unfeatured'));
    }

    /** DELETE /moderation/{id}  Corbeille (restaurable pendant 30 jours). */
    public function trash(Request $request, string $id): RedirectResponse
    {
        $testimony = $this->find($id);
        Gate::authorize('trash', $testimony);
        $this->moderation->trash($testimony, Auth::user(), $request->input('reason'));

        return redirect()->route('moderation.index', ['onglet' => 'trash'])->with('success', __('moderation.flash.trashed'));
    }

    public function restore(string $id): RedirectResponse
    {
        $testimony = Testimony::onlyTrashed()->withoutJournal()->findOrFail($id);
        Gate::authorize('restore', $testimony);

        return $this->attempt(fn () => $this->moderation->restore($testimony, Auth::user()), __('moderation.flash.restored'));
    }

    /** DELETE /moderation/{id}/definitif  Super-administrateur seulement (vidéo Bunny comprise). */
    public function forceDelete(Request $request, string $id): RedirectResponse
    {
        $testimony = Testimony::withTrashed()->withoutJournal()->findOrFail($id);
        Gate::authorize('forceDelete', $testimony);
        $this->moderation->forceDelete($testimony, Auth::user(), $request->input('reason'));

        return redirect()->route('moderation.index', ['onglet' => 'trash'])->with('success', __('moderation.flash.force_deleted'));
    }

    /**
     * POST /moderation/actions-groupees  { ids[], action: approve|reject|feature|unfeature|trash|restore, reason?, rejection_reason? }
     * Chaque témoignage est vérifié par la policy ; ceux qui ne s'y prêtent pas sont ignorés et comptés.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids'              => ['required', 'array', 'min:1', 'max:100'],
            'ids.*'            => ['string'],
            'action'           => ['required', Rule::in(['approve', 'reject', 'feature', 'unfeature', 'trash', 'restore'])],
            'reason'           => ['nullable', 'required_if:action,reject', Rule::enum(RejectionReason::class)],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ], [
            'ids.required'       => __('moderation.bulk.ids_required'),
            'reason.required_if' => __('moderation.choose_reason'),
        ]);
        $user = Auth::user();
        $done = 0;
        $skipped = 0;

        foreach (Testimony::withTrashed()->withoutJournal()->whereIn('id', $data['ids'])->get() as $t) {
            $ability = match ($data['action']) {
                'approve', 'reject'      => 'moderate',
                'feature', 'unfeature'   => 'feature',
                default                  => $data['action'],
            };
            if (!Gate::forUser($user)->allows($ability, $t)) {
                $skipped++;
                continue;
            }
            try {
                match ($data['action']) {
                    'approve'   => $t->status === TestimonyStatus::Approved ? throw new ContentActionException('déjà approuvé') : $this->moderation->approve($t, $user),
                    'reject'    => $this->moderation->reject($t, $user, trim(implode(' — ', array_filter([RejectionReason::from($data['reason'])->label(), trim((string) ($data['rejection_reason'] ?? ''))]))), RejectionReason::from($data['reason']), $data['rejection_reason'] ?? null),
                    'feature'   => $this->moderation->setFeatured($t, $user, true),
                    'unfeature' => $this->moderation->setFeatured($t, $user, false),
                    'trash'     => $this->moderation->trash($t, $user),
                    'restore'   => $this->moderation->restore($t, $user),
                };
                $done++;
            } catch (ContentActionException) {
                $skipped++;
            }
        }

        $message = trans_choice('moderation.bulk.done', $done, ['count' => $done]);
        if ($skipped) {
            $message .= ' ' . trans_choice('moderation.bulk.skipped', $skipped, ['count' => $skipped]);
        }

        return back()->with($done ? 'success' : 'error', $message);
    }

    // ─── Outils ──────────────────────────────────────────────────────────────

    private function find(string $id): Testimony
    {
        return Testimony::with('user')->withoutJournal()->findOrFail($id);
    }

    private function attempt(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (ContentActionException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', $success);
    }

    private function back(string $message): RedirectResponse
    {
        return redirect()->route('moderation.index')->with('success', $message);
    }
}
